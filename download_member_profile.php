<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4]);

require_once 'config/db_connect.php';
require_once 'includes/user_functions.php';
require_once 'includes/StatementPDF.php';
require_once 'includes/CreditScoreHelper.php';

$member_id = $_GET['id'] ?? null;
if (!$member_id) {
    die("Member ID is required.");
}

try {
    // Fetch member details (Excluding root and chairman)
    $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();

    if (!$member) {
        die("Member not found.");
    }

    // Fallback if roles table is missing or role_name is NULL
    if (!isset($member['role_name']) || is_null($member['role_name'])) {
        $member['role_name'] = getRoleName($member['role_id']);
    }

    // Fetch savings summary
    $savings_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_saved, COUNT(*) as transaction_count FROM savings WHERE user_id = ?");
    $savings_stmt->execute([$member_id]);
    $savings_summary = $savings_stmt->fetch();

    // Fetch recent savings
    $recent_savings_stmt = $pdo->prepare("SELECT amount, created_at FROM savings WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
    $recent_savings_stmt->execute([$member_id]);
    $recent_savings = $recent_savings_stmt->fetchAll();

    $scoreHelper = new CreditScoreHelper($pdo);
    $score = $scoreHelper->getScore($member_id);
    $scoreLabel = $scoreHelper->getScoreLabel($score);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// --- PDF Generation ---
$pdf = new StatementPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('POA Savings and Credit Society');
$pdf->SetTitle('Member Profile - ' . $member['first_name'] . ' ' . $member['surname']);

$pdf->setUserDetails(
    $member['first_name'] . ' ' . $member['surname'],
    $member['account_no'],
    '', // Period
    date('M j, Y, g:i a'),
    'MEMBER PROFILE'
);

$pdf->SetMargins(15, 60, 15);
$pdf->SetHeaderMargin(15);
$pdf->SetFooterMargin(20);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();

$pdf->SetY(65);

// Profile Summary Section
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Personal Information', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);

$info_html = '
<table border="0" cellpadding="4" cellspacing="0" width="100%">
    <tr>
        <td width="20%"><b>Full Name:</b></td>
        <td width="30%">' . htmlspecialchars($member['first_name'] . ' ' . $member['surname']) . '</td>
        <td width="20%"><b>Role:</b></td>
        <td width="30%">' . htmlspecialchars($member['role_name']) . '</td>
    </tr>
    <tr>
        <td width="20%"><b>Username:</b></td>
        <td width="30%">@' . htmlspecialchars($member['username']) . '</td>
        <td width="20%"><b>Account No:</b></td>
        <td width="30%">' . htmlspecialchars($member['account_no']) . '</td>
    </tr>
    <tr>
        <td width="20%"><b>Email:</b></td>
        <td width="30%">' . htmlspecialchars($member['email']) . '</td>
        <td width="20%"><b>Phone:</b></td>
        <td width="30%">' . htmlspecialchars($member['phone']) . '</td>
    </tr>
    <tr>
        <td width="20%"><b>Joined Date:</b></td>
        <td width="30%">' . date('M j, Y', strtotime($member['created_at'])) . '</td>
        <td width="20%"><b>Status:</b></td>
        <td width="30%">ACTIVE</td>
    </tr>
</table>';
$pdf->writeHTML($info_html, true, false, true, false, '');

$pdf->Ln(5);

// Financial Summary Boxes
$pdf->SetFillColor(245, 247, 249);
$pdf->RoundedRect(15, $pdf->GetY(), 180, 25, 3, '1111', 'F');

$pdf->SetY($pdf->GetY() + 5);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(60, 5, 'TOTAL SAVINGS', 0, 0, 'C');
$pdf->Cell(60, 5, 'CREDIT SCORE', 0, 0, 'C');
$pdf->Cell(60, 5, 'TRANSACTIONS', 0, 1, 'C');

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(60, 10, number_format($savings_summary['total_saved'], 0) . ' UGX', 0, 0, 'C');
$pdf->Cell(60, 10, $score . ' (' . $scoreLabel['label'] . ')', 0, 0, 'C');
$pdf->Cell(60, 10, $savings_summary['transaction_count'], 0, 1, 'C');

$pdf->Ln(10);

// Savings History Table
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Recent Savings History', 0, 1, 'L');

$header = ['Date & Time', 'Transaction Type', 'Amount (UGX)'];
$w = [80, 50, 50];

$table_data = [];
foreach ($recent_savings as $row) {
    $table_data[] = [
        date('M j, Y, g:i a', strtotime($row['created_at'])),
        'Savings Deposit',
        $row['amount']
    ];
}

$pdf->FancyTable($header, $table_data, $w);

// QR Code for Verification
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?: 'localhost';
$public_url = $protocol . "://" . $host . "/public_profile.php?id=" . $member['id'];

$style = array(
    'border' => false,
    'padding' => 0,
    'fgcolor' => array(0,0,0),
    'bgcolor' => false
);

$pdf->write2DBarcode($public_url, 'QRCODE,L', 165, 240, 30, 30, $style, 'N');
$pdf->SetFont('helvetica', 'I', 7);
$pdf->SetXY(160, 270);
$pdf->Cell(40, 5, 'Scan to verify member', 0, 0, 'C');

$pdf->Output('Member_Profile_' . $member['username'] . '.pdf', 'I');
?>
