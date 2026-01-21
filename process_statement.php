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

// --- Custom PDF Class for Professional Statement ---
class StatementPDF extends TCPDF {
    private $userName;
    private $accountNumber;
    private $statementPeriod;

    public function setUserDetails($name, $acc, $period) {
        $this->userName = $name;
        $this->accountNumber = $acc;
        $this->statementPeriod = $period;
    }

    public function Header() {
        // Use modern, clean fonts
        $this->SetFont('helvetica', '', 10);

        // Logo
        $this->Image('assets/images/poa_light.png', 15, 10, 30, 0, 'PNG');

        // Member Details (aligned to the right)
        $this->SetXY(120, 12);
        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(0, 6, $this->userName, 0, 1, 'R');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 6, 'Account: ' . $this->accountNumber, 0, 1, 'R');

        // Statement Title and Period
        $this->SetXY(15, 35);
        $this->SetFont('helvetica', 'B', 18);
        $this->Cell(0, 10, 'Account Statement', 0, 1, 'L');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 6, 'Period: ' . $this->statementPeriod, 0, 1, 'L');

        // Header bottom border
        $this->Line(15, 55, $this->getPageWidth() - 15, 55);
    }

    public function Footer() {
        $this->SetY(-20);
        // Footer top border
        $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128);

        // Generated On Timestamp
        $this->Cell(0, 10, 'Generated on ' . date('M j, Y, g:i a'), 0, 0, 'L');

        // Page Number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().' of '.$this->getAliasNbPages(), 0, 0, 'R');
    }

    public function FancyTransactionTable($header, $data, $openingBalance) {
        // Set colors
        $this->SetFillColor(33, 37, 41); // Dark grey for header
        $this->SetTextColor(255);
        $this->SetDrawColor(222, 226, 230); // Light grey for borders
        $this->SetFont('helvetica', 'B', 10);
        $this->SetLineWidth(0.3);

        // Header
        $w = array(30, 75, 25, 25, 30); // Column widths
        for($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 10, $header[$i], 1, 0, 'C', 1);
        }
        $this->Ln();

        // Color and font restoration
        $this->SetTextColor(0);
        $this->SetFont('helvetica', '', 9);

        // Data
        $fill = false; // For zebra-striping
        $balance = $openingBalance;
        if (empty($data)) {
            $this->Cell(array_sum($w), 15, 'No transactions in this period.', 'LRB', 0, 'C', $fill);
            $this->Ln();
        } else {
            foreach($data as $row) {
                $debit = is_numeric($row['debit']) ? $row['debit'] : 0;
                $credit = is_numeric($row['credit']) ? $row['credit'] : 0;
                $balance += $credit - $debit;

                $this->SetFillColor(248, 249, 250); // Zebra-stripe color
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
    foreach ($savings_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['debit'] = ''; $row['credit'] = $row['amount']; $transactions[] = $row;
    }
    $withdrawal_stmt = $pdo->prepare("SELECT 'Withdrawal' as type, amount, processed_at as date FROM withdrawals WHERE user_id = ? AND status = 'approved' AND processed_at BETWEEN ? AND ?");
    $withdrawal_stmt->execute([$user_id, $start_date, $end_date_time]);
    foreach ($withdrawal_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['debit'] = $row['amount']; $row['credit'] = ''; $transactions[] = $row;
    }
    $loans_stmt = $pdo->prepare("SELECT 'Loan Disbursed' as type, amount, approved_at as date FROM loans WHERE user_id = ? AND status = 'approved' AND approved_at BETWEEN ? AND ?");
    $loans_stmt->execute([$user_id, $start_date, $end_date_time]);
     foreach ($loans_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['debit'] = $row['amount']; $row['credit'] = ''; $transactions[] = $row;
    }
    $repayment_stmt = $pdo->prepare("SELECT 'Loan Repayment' as type, lp.amount, lp.paid_at as date FROM loan_payments lp JOIN loans l ON lp.loan_id = l.id WHERE l.user_id = ? AND lp.paid_at BETWEEN ? AND ?");
    $repayment_stmt->execute([$user_id, $start_date, $end_date_time]);
    foreach ($repayment_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['debit'] = ''; $row['credit'] = $row['amount']; $transactions[] = $row;
    }
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

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('POA Savings and Credit Society');
    $pdf->SetTitle('Account Statement for ' . $user['username']);
    $pdf->setUserDetails(
        htmlspecialchars($user['username']),
        htmlspecialchars($user['account_no']),
        date('M j, Y', strtotime($start_date)) . " to " . date('M j, Y', strtotime($end_date))
    );

    // Set margins and add a page
    $pdf->SetMargins(15, 60, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(20);
    $pdf->SetAutoPageBreak(TRUE, 25);
    $pdf->AddPage();

    // Draw main content frame
    $pdf->SetDrawColor(222, 226, 230);
    $pdf->Rect(15, 60, $pdf->getPageWidth() - 30, $pdf->getPageHeight() - 85, 'D');


    // --- Balance Summary Cards ---
    $formatted_opening_balance = number_format($opening_balance, 2) . " UGX";
    $formatted_closing_balance = number_format($closing_balance, 2) . " UGX";

    $balance_cards_html = <<<EOD
<style>
    .card {
        border: 1px solid #e9ecef;
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 4px;
    }
    .card-title {
        font-size: 11pt;
        font-weight: bold;
        color: #495057;
    }
    .card-value {
        font-size: 15pt;
        font-weight: bold;
        color: #212529;
    }
</style>
<table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
    <tr>
        <td style="width: 48%;">
            <div class="card" style="border-left: 4px solid #0d6efd;">
                <div class="card-title">Opening Balance</div>
                <div class="card-value">{$formatted_opening_balance}</div>
            </div>
        </td>
        <td style="width: 4%;"></td> <!-- Spacer -->
        <td style="width: 48%;">
            <div class="card" style="border-left: 4px solid #198754;">
                <div class="card-title">Closing Balance</div>
                <div class="card-value">{$formatted_closing_balance}</div>
            </div>
        </td>
    </tr>
</table>
EOD;
    $pdf->SetY(65);
    $pdf->writeHTML($balance_cards_html, true, false, true, false, '');

    // --- Transaction Table ---
    $pdf->SetY($pdf->GetY() + 10);
    $table_header = ['Date', 'Description', 'Debit', 'Credit', 'Balance (UGX)'];
    $pdf->FancyTransactionTable($table_header, $transactions, $opening_balance);

    // --- Output PDF ---
    $pdf->Output('Account_Statement_' . $user['username'] . '.pdf', 'I');

} catch (Exception $e) {
    error_log("PDF Generation Failed: " . $e->getMessage());
    header('Location: generate_statement.php?error=' . urlencode('An error occurred generating the PDF.'));
    exit;
}
