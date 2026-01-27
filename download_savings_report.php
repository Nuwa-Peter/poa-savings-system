<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4]); // Admins only

require_once 'config/db_connect.php';
require_once 'vendor/autoload.php';

$selected_member_id = $_GET['member_id'] ?? 'all';
$savings = [];
$report_title = 'Savings Report for All Members';

try {
    // Fetch savings data based on selection
    $sql = "SELECT s.id, s.amount, s.created_at, u.first_name, u.surname FROM savings s JOIN users u ON s.user_id = u.id";
    if ($selected_member_id !== 'all' && is_numeric($selected_member_id)) {
        $sql .= " WHERE s.user_id = ?";
        $savings_stmt = $pdo->prepare($sql);
        $savings_stmt->execute([$selected_member_id]);

        $user_stmt = $pdo->prepare("SELECT first_name, surname FROM users WHERE id = ?");
        $user_stmt->execute([$selected_member_id]);
        $user = $user_stmt->fetch();
        $report_title = 'Savings Report for ' . htmlspecialchars($user['first_name'] . ' ' . $user['surname']);

    } else {
        $savings_stmt = $pdo->query($sql);
    }
    $savings = $savings_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// --- PDF Generation ---
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('POA Savings and Credit Society');
$pdf->SetTitle($report_title);
$pdf->SetSubject('Member Savings Report');

$pdf->SetHeaderData('', 0, $report_title, 'Generated on: ' . date('M j, Y'));
$pdf->setFooterData([0,64,0], [0,64,128]);

$pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
$pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

$pdf->AddPage();

$html = '
<style>
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        border: 1px solid #ddd;
        padding: 8px;
    }
    th {
        background-color: #f2f2f2;
        font-weight: bold;
    }
</style>
<h1>' . $report_title . '</h1>
<table>
    <thead>
        <tr>
            <th>Member</th>
            <th align="right">Amount (UGX)</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>';

$total_savings = 0;
foreach ($savings as $saving) {
    $html .= '<tr>
        <td>' . htmlspecialchars($saving['first_name'] . ' ' . $saving['surname']) . '</td>
        <td align="right">' . number_format($saving['amount'], 2) . '</td>
        <td>' . date('M j, Y, g:i a', strtotime($saving['created_at'])) . '</td>
    </tr>';
    $total_savings += $saving['amount'];
}

$html .= '
    </tbody>
    <tfoot>
        <tr>
            <th colspan="1" align="right">Total Savings:</th>
            <th align="right">' . number_format($total_savings, 2) . ' UGX</th>
            <th></th>
        </tr>
    </tfoot>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('savings_report.pdf', 'I');
?>
