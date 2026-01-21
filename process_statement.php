<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
// Use the standard Composer autoloader as required
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

// --- Custom PDF Class for Branded Header ---
class POAPDF extends TCPDF {
    // Brand Colors
    private $navyBlue = [25, 35, 45];

    public function Header() {
        // Logo
        $this->Image('assets/images/poa_light.png', 10, 10, 30, 0, 'PNG');

        // Title
        $this->SetFont('helvetica', 'B', 22);
        $this->SetTextColor($this->navyBlue[0], $this->navyBlue[1], $this->navyBlue[2]);
        $this->Cell(0, 15, 'Account Statement', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(20);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

try {
    // --- 1. Data Fetching ---
    $user_stmt = $pdo->prepare("SELECT username, account_no FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();

    // --- Transaction Data Gathering ---
    $transactions = [];
    // 1. Savings (Deposits)
    $savings_stmt = $pdo->prepare("SELECT 'Saving (Deposit)' as type, amount, created_at as date FROM savings WHERE user_id = ? AND created_at BETWEEN ? AND ?");
    $savings_stmt->execute([$user_id, $start_date, $end_date_time]);
    foreach ($savings_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['debit'] = '';
        $row['credit'] = $row['amount'];
        $transactions[] = $row;
    }

    // 2. Withdrawals
    $withdrawal_stmt = $pdo->prepare("SELECT 'Withdrawal' as type, amount, processed_at as date FROM withdrawals WHERE user_id = ? AND status = 'approved' AND processed_at BETWEEN ? AND ?");
    $withdrawal_stmt->execute([$user_id, $start_date, $end_date_time]);
    foreach ($withdrawal_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['debit'] = $row['amount'];
        $row['credit'] = '';
        $transactions[] = $row;
    }

    // 3. Loans Given
    $loans_stmt = $pdo->prepare("SELECT 'Loan Disbursed' as type, amount, approved_at as date FROM loans WHERE user_id = ? AND status = 'approved' AND approved_at BETWEEN ? AND ?");
    $loans_stmt->execute([$user_id, $start_date, $end_date_time]);
     foreach ($loans_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['debit'] = $row['amount'];
        $row['credit'] = '';
        $transactions[] = $row;
    }

    // 4. Loan Repayments
    $repayment_stmt = $pdo->prepare(
        "SELECT 'Loan Repayment' as type, lp.amount, lp.paid_at as date
         FROM loan_payments lp JOIN loans l ON lp.loan_id = l.id
         WHERE l.user_id = ? AND lp.paid_at BETWEEN ? AND ?"
    );
    $repayment_stmt->execute([$user_id, $start_date, $end_date_time]);
    foreach ($repayment_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['debit'] = '';
        $row['credit'] = $row['amount'];
        $transactions[] = $row;
    }

    // Sort transactions by date
    usort($transactions, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

    // --- 2. Logic Extraction ---
    $navyBlue = 'rgb(25, 35, 45)';
    $leafGreen = 'rgb(46, 182, 125)';

    // Calculate opening balance
    $credits_before_stmt = $pdo->prepare(
        "(SELECT SUM(amount) FROM savings WHERE user_id = ? AND created_at < ?)
         UNION ALL
         (SELECT SUM(lp.amount) FROM loan_payments lp JOIN loans l ON lp.loan_id = l.id WHERE l.user_id = ? AND lp.paid_at < ?)"
    );
    $credits_before_stmt->execute([$user_id, $start_date, $user_id, $start_date]);
    $total_credits_before = array_sum($credits_before_stmt->fetchAll(PDO::FETCH_COLUMN));

    $debits_before_stmt = $pdo->prepare(
        "(SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND status = 'approved' AND processed_at < ?)
         UNION ALL
         (SELECT SUM(amount) FROM loans WHERE user_id = ? AND status = 'approved' AND approved_at < ?)"
    );
    $debits_before_stmt->execute([$user_id, $start_date, $user_id, $start_date]);
    $total_debits_before = array_sum($debits_before_stmt->fetchAll(PDO::FETCH_COLUMN));

    $opening_balance = $total_credits_before - $total_debits_before;

    // Calculate closing balance
    $net_change = array_reduce($transactions, fn($sum, $t) => $sum + (is_numeric($t['credit']) ? $t['credit'] : 0) - (is_numeric($t['debit']) ? $t['debit'] : 0), 0);
    $closing_balance = $opening_balance + $net_change;

    // Format all dynamic data before the HTML block
    $formatted_member = htmlspecialchars($user['username']);
    $formatted_account_no = htmlspecialchars($user['account_no']);
    $formatted_period = date('M j, Y', strtotime($start_date)) . " to " . date('M j, Y', strtotime($end_date));
    $formatted_opening_balance = number_format($opening_balance, 2) . " UGX";
    $formatted_closing_balance = number_format($closing_balance, 2) . " UGX";

    // --- 3. PDF Generation ---
    $pdf = new POAPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('POA Savings and Credit Society');
    $pdf->SetTitle('Account Statement for ' . $formatted_member);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    // --- 4. TCPDF-Compatible HTML Layout ---
    $html = <<<EOD
<style>
    .summary-card { border: 1px solid #e0e0e0; padding: 15px; }
    .summary-card h3 { font-size: 12pt; color: #555; font-weight: bold; }
    .summary-card p { font-size: 16pt; font-weight: bold; }
    .transaction-table { border-collapse: collapse; width: 100%; font-size: 9pt; }
    .transaction-table th { font-weight: bold; padding: 8px; }
    .transaction-table td { padding: 8px; border-bottom: 1px solid #eeeeee; }
</style>

<table border="0" cellpadding="5" cellspacing="0" style="width: 100%;">
    <tr>
        <td style="width: 50%;">
            <b>Member:</b> {$formatted_member}<br>
            <b>Account Number:</b> {$formatted_account_no}
        </td>
        <td style="width: 50%; text-align: right;">
            <b>Period:</b> {$formatted_period}
        </td>
    </tr>
</table>
<br><br><br>

<table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
    <tr>
        <td style="width: 48%;">
            <div class="summary-card" style="border-left: 4px solid {$leafGreen};">
                <h3>Opening Balance</h3>
                <p style="color:{$navyBlue};">{$formatted_opening_balance}</p>
            </div>
        </td>
        <td style="width: 4%;"></td> <!-- Spacer -->
        <td style="width: 48%;">
            <div class="summary-card" style="border-left: 4px solid {$navyBlue};">
                <h3>Closing Balance</h3>
                <p style="color:{$navyBlue};">{$formatted_closing_balance}</p>
            </div>
        </td>
    </tr>
</table>
<br><br><br>

<table class="transaction-table" cellpadding="6">
    <thead>
        <tr style="background-color:{$navyBlue}; color:white;">
            <th style="width:15%;">Date</th>
            <th style="width:35%;">Description</th>
            <th style="width:15%; text-align:right;">Debit (UGX)</th>
            <th style="width:15%; text-align:right;">Credit (UGX)</th>
            <th style="width:20%; text-align:right;">Balance (UGX)</th>
        </tr>
    </thead>
    <tbody>
EOD;

if (empty($transactions)) {
    $html .= '<tr><td colspan="5" style="text-align:center; padding: 20px;">No transactions in this period.</td></tr>';
} else {
    $balance = $opening_balance;
    $is_even = false;
    foreach ($transactions as $t) {
        $debit = is_numeric($t['debit']) ? $t['debit'] : 0;
        $credit = is_numeric($t['credit']) ? $t['credit'] : 0;
        $balance += $credit - $debit;
        $row_style = $is_even ? ' style="background-color:#f9f9f9;"' : '';

        $html .= '<tr' . $row_style . '>
                    <td>' . date('Y-m-d', strtotime($t['date'])) . '</td>
                    <td>' . htmlspecialchars($t['type']) . '</td>
                    <td style="text-align:right;">' . ($debit > 0 ? number_format($debit, 2) : '') . '</td>
                    <td style="text-align:right;">' . ($credit > 0 ? number_format($credit, 2) : '') . '</td>
                    <td style="text-align:right;">' . number_format($balance, 2) . '</td>
                  </tr>';
        $is_even = !$is_even;
    }
}

$html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('Account_Statement.pdf', 'I');

} catch (Exception $e) {
    // Log the actual error for debugging
    error_log("PDF Generation Failed: " . $e->getMessage());
    // Redirect with a generic error
    header('Location: generate_statement.php?error=' . urlencode('An unexpected error occurred during PDF generation.'));
    exit;
}
