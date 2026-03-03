<?php
require_once 'includes/auth_check.php';
require_once 'config/db_connect.php';
// Ensure dependencies are loaded
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

if (!class_exists('TCPDF')) {
    die("Error: TCPDF library not found. Please run 'composer install' to install dependencies.");
}

// All logged-in users can generate their own statements
check_permissions([1, 2, 3, 4, 5]); // Assuming 5 roles exist

$user_id = $_SESSION['user_id'];
$statement_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $user_id;

// --- Security Check ---
// Members can only view their own statements.
// Admins (Root, Chairman, Secretary, Treasurer) can view anyone's statement.
$is_admin = in_array($_SESSION['role_id'], [1, 2, 3, 4]);
if (!$is_admin && $statement_user_id !== $user_id) {
    die('Unauthorized access to statements.');
}


try {
    // Fetch user details
    $user_stmt = $pdo->prepare("SELECT username, account_no FROM users WHERE id = ?");
    $user_stmt->execute([$statement_user_id]);
    $user = $user_stmt->fetch();

    if (!$user) {
        die('User not found.');
    }

    // Fetch user's savings history
    $savings_stmt = $pdo->prepare("SELECT amount, created_at FROM savings WHERE user_id = ? ORDER BY created_at DESC");
    $savings_stmt->execute([$statement_user_id]);
    $savings = $savings_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// --- PDF Generation using TCPDF ---

// 1. Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// 2. Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('POA Savings System');
$pdf->SetTitle('Savings Statement for ' . $user['username']);
$pdf->SetSubject('Account Statement');

// 3. Set header and footer data
// TODO: Customize header with Organization Logo/Name
$pdf->SetHeaderData('', 0, 'POA Savings Statement', 'Account: ' . $user['account_no']);
$pdf->setFooterData(array(0,64,0), array(0,64,128));

// 4. Set fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
$pdf->SetDefaultMonospacedFont(PDF_FONT_NAME_MONOSPACED);

// 5. Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// 6. Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// 7. Add a page
$pdf->AddPage();

// 8. Set some content to print
$html = '
<h1>Account Statement</h1>
<p><b>Username:</b> ' . htmlspecialchars($user['username']) . '</p>
<p><b>Account Number:</b> ' . htmlspecialchars($user['account_no']) . '</p>
<br>
<table border="1" cellpadding="4">
    <thead>
        <tr style="background-color:#cccccc;">
            <th width="10%"><b>#</b></th>
            <th width="50%"><b>Date</b></th>
            <th width="40%"><b>Amount</b></th>
        </tr>
    </thead>
    <tbody>';

$total_savings = 0;
$count = 1;
if (count($savings) > 0) {
    foreach ($savings as $saving) {
        $html .= '<tr>';
        $html .= '<td width="10%">' . $count++ . '</td>';
        $html .= '<td width="50%">' . date('Y-m-d H:i:s', strtotime($saving['created_at'])) . '</td>';
        $html .= '<td width="40%" align="right">$' . number_format($saving['amount'], 0) . '</td>';
        $html .= '</tr>';
        $total_savings += $saving['amount'];
    }
} else {
    $html .= '<tr><td colspan="3" align="center">No transactions found.</td></tr>';
}

$html .= '
    </tbody>
    <tfoot>
        <tr style="background-color:#f0f0f0;">
            <td colspan="2" align="right"><b>Total Savings:</b></td>
            <td align="right"><b>$' . number_format($total_savings, 0) . '</b></td>
        </tr>
    </tfoot>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');

// --- QR Code Generation (Using TCPDF internal functionality) ---
$qr_data = "Verification Code:\nUser: {$user['username']}\nAccount: {$user['account_no']}\nDate: " . date('Y-m-d');
$style = array(
    'border' => false,
    'padding' => 0,
    'fgcolor' => array(0,0,0),
    'bgcolor' => false
);

// Add QR code to PDF
$pdf->write2DBarcode($qr_data, 'QRCODE,L', 170, 250, 25, 25, $style, 'N');
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Text(168, 275, 'Scan for verification');


// 9. Close and output PDF document
$pdf->Output('statement_' . $user['account_no'] . '.pdf', 'I');

?>
