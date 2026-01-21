<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
// Use the modern Composer autoloader
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
    public function Header() {
        // Logo
        $this->Image('assets/images/poa_light.png', 10, 10, 25, 0, 'PNG');

        // Colors from brand palette
        $navyBlue = [25, 35, 45];

        // Title
        $this->SetFont('helvetica', 'B', 20);
        $this->SetTextColor($navyBlue[0], $navyBlue[1], $navyBlue[2]);
        $this->Cell(0, 15, 'Account Statement', 0, false, 'C', 0, '', 0, false, 'M', 'M');

        // Line break
        $this->Ln(20);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}


try {
    // --- Data Fetching ---
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

    // Sort by date
    usort($transactions, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

    // --- Balance Calculation ---
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


    // --- PDF Generation ---
    $pdf = new POAPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Metadata
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('POA Savings and Credit Society');
    $pdf->SetTitle('Account Statement');
    $pdf->AddPage();

    // --- Modern HTML Layout ---
    $navyBlue = 'rgb(25, 35, 45)';
    $leafGreen = 'rgb(46, 182, 125)';

    $html = <<<EOD
<style>
    body { font-family: helvetica, sans-serif; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; }
    .summary-cards { display: flex; justify-content: space-between; margin: 20px 0; }
    .card { border: 1px solid #e0e0e0; border-left: 5px solid {$leafGreen}; padding: 15px; width: 48%; }
    .card h3 { font-size: 12pt; color: #666; }
    .card p { font-size: 18pt; font-weight: bold; color: {$navyBlue}; }
    .styled-table { border-collapse: collapse; width: 100%; font-size: 9pt; }
    .styled-table th { background-color: {$navyBlue}; color: white; font-weight: bold; padding: 10px; }
    .styled-table td { padding: 8px; border-bottom: 1px solid #e0e0e0; }
    .styled-table .even { background-color: #f9f9f9; }
    .currency { text-align: right; }
</style>

<!-- Member Info Section -->
<table style="width: 100%;">
    <tr>
        <td style="width: 50%;">
            <b>Member:</b> {$user['username']}<br>
            <b>Account Number:</b> {$user['account_no']}
        </td>
        <td style="width: 50%; text-align: right;">
            <b>Period:</b> {date('M j, Y', strtotime($start_date))} to {date('M j, Y', strtotime($end_date))}
        </td>
    </tr>
</table>
<br><br>

<!-- Summary Cards -->
<table style="width: 100%;">
    <tr>
        <td style="width: 48%;">
            <div style="border: 1px solid #e0e0e0; border-left: 5px solid {$leafGreen}; padding: 15px;">
                <h3 style="font-size: 12pt; color: #666;">Opening Balance</h3>
                <p style="font-size: 18pt; font-weight: bold; color: {$navyBlue};">{number_format($opening_balance, 2)} UGX</p>
            </div>
        </td>
        <td style="width: 4%;"></td> <!-- Spacer -->
        <td style="width: 48%;">
            <div style="border: 1px solid #e0e0e0; border-left: 5px solid {$navyBlue}; padding: 15px;">
                <h3 style="font-size: 12pt; color: #666;">Closing Balance</h3>
                <p style="font-size: 18pt; font-weight: bold; color: {$navyBlue};">{number_format($opening_balance + array_reduce($transactions, fn($sum, $t) => $sum + (is_numeric($t['credit']) ? $t['credit'] : 0) - (is_numeric($t['debit']) ? $t['debit'] : 0), 0), 2)} UGX</p>
            </div>
        </td>
    </tr>
</table>
<br><br>

<!-- Transaction Table -->
<table class="styled-table" cellpadding="8">
    <thead>
        <tr style="background-color:{$navyBlue}; color:white;">
            <th style="width: 15%;">Date</th>
            <th style="width: 35%;">Description</th>
            <th style="width: 15%;" align="right">Debit (UGX)</th>
            <th style="width: 15%;" align="right">Credit (UGX)</th>
            <th style="width: 20%;" align="right">Balance (UGX)</th>
        </tr>
    </thead>
    <tbody>
EOD;

$balance = $opening_balance;
if (empty($transactions)) {
    $html .= '<tr><td colspan="5" align="center" style="padding: 20px;">No transactions in this period.</td></tr>';
} else {
    foreach ($transactions as $i => $t) {
        $debit = is_numeric($t['debit']) ? $t['debit'] : 0;
        $credit = is_numeric($t['credit']) ? $t['credit'] : 0;
        $balance += $credit - $debit;
        $rowClass = ($i % 2 === 0) ? '' : 'even';

        $html .= '<tr class="' . $rowClass . '">
                    <td>' . date('Y-m-d', strtotime($t['date'])) . '</td>
                    <td>' . htmlspecialchars($t['type']) . '</td>
                    <td align="right">' . ($debit > 0 ? number_format($debit, 2) : '') . '</td>
                    <td align="right">' . ($credit > 0 ? number_format($credit, 2) : '') . '</td>
                    <td align="right">' . number_format($balance, 2) . '</td>
                  </tr>';
    }
}

$html .= <<<EOD
    </tbody>
</table>
EOD;

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('statement.pdf', 'I');

} catch (Exception $e) {
    header('Location: generate_statement.php?error=' . urlencode('An error occurred during PDF generation: ' . $e->getMessage()));
    exit;
}
