<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
require_once 'includes/StatementPDF.php';

// --- Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo "This page must be accessed via a POST request.";
    exit;
}

$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$start_date || !$end_date) {
    header('Location: generate_statement.php?error=' . urlencode('Please select both a start and end date.'));
    exit;
}

$end_date_time = $end_date . ' 23:59:59';

try {
    // --- Data Fetching (reusing existing, correct logic) ---
    $user_stmt = $pdo->prepare("SELECT username, account_no FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();

    $transactions = [];
    $savings_stmt = $pdo->prepare("SELECT 'Saving (Deposit)' as type, amount, created_at as date FROM savings WHERE user_id = ? AND created_at BETWEEN ? AND ?");
    $savings_stmt->execute([$user_id, $start_date, $end_date_time]);
    foreach ($savings_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $row['debit'] = ''; $row['credit'] = $row['amount']; $transactions[] = $row; }
    $withdrawal_stmt = $pdo->prepare("SELECT 'Withdrawal' as type, amount, processed_at as date FROM withdrawals WHERE user_id = ? AND status = 'approved' AND processed_at BETWEEN ? AND ?");
    $withdrawal_stmt->execute([$user_id, $start_date, $end_date_time]);
    foreach ($withdrawal_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $row['debit'] = $row['amount']; $row['credit'] = ''; $transactions[] = $row; }
    $loans_stmt = $pdo->prepare("SELECT 'Loan Disbursed' as type, amount, approved_at as date FROM loans WHERE user_id = ? AND status = 'approved' AND approved_at BETWEEN ? AND ?");
    $loans_stmt->execute([$user_id, $start_date, $end_date_time]);
    foreach ($loans_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $row['debit'] = $row['amount']; $row['credit'] = ''; $transactions[] = $row; }
    $repayment_stmt = $pdo->prepare("SELECT 'Loan Repayment' as type, lp.amount, lp.paid_at as date FROM loan_payments lp JOIN loans l ON lp.loan_id = l.id WHERE l.user_id = ? AND lp.paid_at BETWEEN ? AND ?");
    $repayment_stmt->execute([$user_id, $start_date, $end_date_time]);
    foreach ($repayment_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $row['debit'] = ''; $row['credit'] = $row['amount']; $transactions[] = $row; }
    usort($transactions, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

    $credits_before_stmt = $pdo->prepare("(SELECT SUM(amount) FROM savings WHERE user_id = ? AND created_at < ?) UNION ALL (SELECT SUM(lp.amount) FROM loan_payments lp JOIN loans l ON lp.loan_id = l.id WHERE l.user_id = ? AND lp.paid_at < ?)");
    $credits_before_stmt->execute([$user_id, $start_date, $user_id, $start_date]);
    $total_credits_before = array_sum($credits_before_stmt->fetchAll(PDO::FETCH_COLUMN));
    $debits_before_stmt = $pdo->prepare("(SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND status = 'approved' AND processed_at < ?) UNION ALL (SELECT SUM(amount) FROM loans WHERE user_id = ? AND status = 'approved' AND approved_at < ?)");
    $debits_before_stmt->execute([$user_id, $start_date, $user_id, $start_date]);
    $total_debits_before = array_sum($debits_before_stmt->fetchAll(PDO::FETCH_COLUMN));
    $opening_balance = $total_credits_before - $total_debits_before;
    $net_change = array_reduce($transactions, fn($sum, $t) => $sum + (is_numeric($t['credit']) ? $t['credit'] : 0) - (is_numeric($t['debit']) ? $t['debit'] : 0), 0);
    $closing_balance = $opening_balance + $net_change;

    // --- PDF Generation ---
    $pdf = new StatementPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('POA Savings and Credit Society');
    $pdf->SetTitle('Account Statement for ' . $user['username']);
    $pdf->setUserDetails(
        htmlspecialchars($user['username']),
        htmlspecialchars($user['account_no']),
        date('M j, Y', strtotime($start_date)) . " to " . date('M j, Y', strtotime($end_date)),
        date('M j, Y, g:i a')
    );

    $pdf->SetMargins(15, 60, 15);
    $pdf->SetHeaderMargin(15);
    $pdf->SetFooterMargin(20);
    $pdf->SetAutoPageBreak(TRUE, 25);
    $pdf->AddPage();

    // --- Balance Summary Box ---
    $formatted_opening_balance = "<b>Opening Balance:</b> " . number_format($opening_balance, 0) . " UGX";
    $formatted_closing_balance = "<b>Closing Balance:</b> " . number_format($closing_balance, 0) . " UGX";

    $summary_html = <<<EOD
<style>
    .summary-box {
        background-color: #f1f3f5;
        border: 1px solid #dee2e6;
        padding: 10px;
        font-size: 10pt;
    }
</style>
<table class="summary-box" width="100%" cellpadding="5">
    <tr>
        <td width="50%" align="center">{$formatted_opening_balance}</td>
        <td width="50%" align="center">{$formatted_closing_balance}</td>
    </tr>
</table>
EOD;
    $pdf->SetY(65);
    $pdf->writeHTML($summary_html, true, false, true, false, '');

    // --- Transaction Table ---
    $pdf->SetY($pdf->GetY() + 5);
    $table_header = ['Date', 'Description', 'Debit', 'Credit', 'Balance'];
    $pdf->FancyTable($table_header, $transactions, [30, 74, 28, 28, 25], $opening_balance);

    $pdf->Output('Account_Statement_' . $user['username'] . '.pdf', 'I');

} catch (Exception $e) {
    error_log("PDF Generation Failed: " . $e->getMessage());
    header('Location: generate_statement.php?error=' . urlencode('An error occurred generating the PDF.'));
    exit;
}
