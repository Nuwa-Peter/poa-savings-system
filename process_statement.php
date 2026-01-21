<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
require_once 'vendor/autoload.php';

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

// --- Custom PDF Class for A4 Financial Statement ---
class StatementPDF extends TCPDF {
    private $userName;
    private $accountNumber;
    private $statementPeriod;
    private $generatedDate;

    public function setUserDetails($name, $acc, $period, $generated) {
        $this->userName = $name;
        $this->accountNumber = $acc;
        $this->statementPeriod = $period;
        $this->generatedDate = $generated;
    }

    public function Header() {
        // Set Font
        $this->SetFont('helvetica', '', 10);

        // Center Logo
        $this->Image('assets/images/poa_light.png', '', 10, 35, 0, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        $this->Ln(5);

        // Center Company Name
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'POA Savings and Credit Society', 0, 1, 'C');

        // Center Document Title
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 12, 'ACCOUNT STATEMENT', 0, 1, 'C');
        $this->Ln(5);

        // Account Information Section
        $this->SetFont('helvetica', '', 9);
        $html = '
<table border="0" cellpadding="2" cellspacing="0" width="100%">
    <tr>
        <td width="50%" align="left"><b>Account Name:</b> ' . $this->userName . '<br><b>Account Number:</b> ' . $this->accountNumber . '</td>
        <td width="50%" align="right"><b>Statement Period:</b> ' . $this->statementPeriod . '<br><b>Generated Date:</b> ' . $this->generatedDate . '</td>
    </tr>
</table>';
        $this->writeHTML($html, true, false, true, false, '');
        $this->Line(15, $this->GetY() + 2, $this->getPageWidth() - 15, $this->GetY() + 2);
    }

    public function Footer() {
        $this->SetY(-18);
        // Thin horizontal line
        $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128);

        // Generated On Timestamp (from user details for consistency)
        $this->Cell(0, 10, 'Generated on: ' . $this->generatedDate, 0, 0, 'L');

        // Page Number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().' of '.$this->getAliasNbPages(), 0, 0, 'R');
    }

    public function FancyTransactionTable($header, $data, $openingBalance) {
        $this->SetFillColor(229, 231, 235); // Light grey for header
        $this->SetTextColor(0);
        $this->SetDrawColor(209, 213, 219);
        $this->SetFont('helvetica', 'B', 10);
        $this->SetLineWidth(0.2);

        // Header
        // 40% for Description, remaining 60% for others
        $w = array(30, 74, 28, 28, 25);
        for($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 10, $header[$i], 1, 0, 'C', 1);
        }
        $this->Ln();

        // Color and font restoration
        $this->SetFont('helvetica', '', 9);

        // Data
        $fill = false; // For zebra-striping
        $balance = $openingBalance;
        if (empty($data)) {
            $this->Cell(array_sum($w), 15, 'No transactions recorded for this period', 'LRB', 0, 'C', $fill);
            $this->Ln();
        } else {
            foreach($data as $row) {
                $debit = is_numeric($row['debit']) ? $row['debit'] : 0;
                $credit = is_numeric($row['credit']) ? $row['credit'] : 0;
                $balance += $credit - $debit;

                $this->SetFillColor(248, 249, 250);
                $this->Cell($w[0], 9, date('Y-m-d', strtotime($row['date'])), 'LR', 0, 'L', $fill);
                $this->Cell($w[1], 9, htmlspecialchars($row['type']), 'R', 0, 'L', $fill);
                $this->Cell($w[2], 9, ($debit > 0) ? number_format($debit, 2) : '-', 'R', 0, 'R', $fill);
                $this->Cell($w[3], 9, ($credit > 0) ? number_format($credit, 2) : '-', 'R', 0, 'R', $fill);
                $this->Cell($w[4], 9, number_format($balance, 2), 'R', 0, 'R', $fill);
                $this->Ln();
                $fill = !$fill;
            }
        }
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

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
    $formatted_opening_balance = "<b>Opening Balance:</b> " . number_format($opening_balance, 2) . " UGX";
    $formatted_closing_balance = "<b>Closing Balance:</b> " . number_format($closing_balance, 2) . " UGX";

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
    $pdf->FancyTransactionTable($table_header, $transactions, $opening_balance);

    $pdf->Output('Account_Statement_' . $user['username'] . '.pdf', 'I');

} catch (Exception $e) {
    error_log("PDF Generation Failed: " . $e->getMessage());
    header('Location: generate_statement.php?error=' . urlencode('An error occurred generating the PDF.'));
    exit;
}
