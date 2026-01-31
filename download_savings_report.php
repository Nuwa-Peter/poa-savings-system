<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4]); // Admins only

require_once 'config/db_connect.php';
require_once 'includes/StatementPDF.php';

$selected_member_id = $_GET['member_id'] ?? 'all';
$savings = [];
$report_title = 'SAVINGS SUMMARY REPORT';
$user_name = 'All Members';
$account_no = '';

try {
    if ($selected_member_id !== 'all' && is_numeric($selected_member_id)) {
        $user_stmt = $pdo->prepare("SELECT first_name, surname, account_no FROM users WHERE id = ?");
        $user_stmt->execute([$selected_member_id]);
        $user = $user_stmt->fetch();
        if ($user) {
            $user_name = $user['first_name'] . ' ' . $user['surname'];
            $account_no = $user['account_no'];
            $report_title = 'MEMBER SAVINGS REPORT';
        }

        $sql = "SELECT u.first_name, u.surname, COALESCE(SUM(s.amount), 0) as total_saved
                FROM users u
                LEFT JOIN savings s ON u.id = s.user_id
                WHERE u.id = ?
                GROUP BY u.id";
        $savings_stmt = $pdo->prepare($sql);
        $savings_stmt->execute([$selected_member_id]);
    } else {
        $sql = "SELECT u.first_name, u.surname, COALESCE(SUM(s.amount), 0) as total_saved
                FROM users u
                LEFT JOIN savings s ON u.id = s.user_id
                WHERE u.status = 'active'
                GROUP BY u.id
                ORDER BY total_saved DESC";
        $savings_stmt = $pdo->query($sql);
    }
    $savings = $savings_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// --- PDF Generation ---
$pdf = new StatementPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('POA Savings and Credit Society');
$pdf->SetTitle($report_title);

$pdf->setUserDetails(
    htmlspecialchars($user_name),
    htmlspecialchars($account_no),
    '', // Period
    date('M j, Y, g:i a'),
    $report_title
);

$pdf->SetMargins(15, 60, 15);
$pdf->SetHeaderMargin(15);
$pdf->SetFooterMargin(20);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();

// Prepare data for FancyTable
$table_data = [];
$grand_total = 0;
foreach ($savings as $row) {
    $table_data[] = [
        $row['first_name'] . ' ' . $row['surname'],
        $row['total_saved'],
        date('M j, Y')
    ];
    $grand_total += $row['total_saved'];
}

$header = ['Member', 'Total Saved (UGX)', 'Last Check Date'];
$w = [80, 50, 50];

$pdf->SetY(65);
$pdf->FancyTable($header, $table_data, $w);

// Add Grand Total row manually if multiple members
if ($selected_member_id === 'all') {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell($w[0], 10, 'GRAND TOTAL', 1, 0, 'R');
    $pdf->Cell($w[1], 10, number_format($grand_total, 0), 1, 0, 'R');
    $pdf->Cell($w[2], 10, '', 1, 1, 'R');
}

$pdf->Output('savings_report.pdf', 'I');
?>
