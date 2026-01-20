<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
require_once 'vendor/tecnickcom/tcpdf/tcpdf.php'; // Ensure you have TCPDF library

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

// Ensure end date includes the full day
$end_date_time = $end_date . ' 23:59:59';

try {
    // --- Fetch User and Transaction Data ---
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

    // 3. Loans Given (Credit to user's perspective, but debit from savings)
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

    // --- PDF Generation ---
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Document metadata
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('POA Savings and Credit Society');
    $pdf->SetTitle('Account Statement');
    $pdf->SetSubject('Account Statement for ' . $user['username']);

    // Header
    $pdf->SetHeaderData('', 0, 'POA Savings and Credit Society', "Account Statement\n" . date('M j, Y'));

    $pdf->AddPage();

    // Content
    $html = "<h1>Account Statement</h1>";
    $html .= "<p><b>Member:</b> " . htmlspecialchars($user['username']) . "</p>";
    $html .= "<p><b>Account Number:</b> " . htmlspecialchars($user['account_no']) . "</p>";
    $html .= "<p><b>Period:</b> " . date('M j, Y', strtotime($start_date)) . " to " . date('M j, Y', strtotime($end_date)) . "</p>";
    $html .= "<hr>";

    // --- Balance Calculation ---
    // A more complete calculation that includes all credits and debits before the start date.
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

    $html .= '<table border="1" cellpadding="4">
                <thead>
                    <tr style="background-color:#f0f0f0;">
                        <th>Date</th>
                        <th>Description</th>
                        <th align="right">Debit (UGX)</th>
                        <th align="right">Credit (UGX)</th>
                        <th align="right">Balance (UGX)</th>
                    </tr>
                </thead>
                <tbody>';

    $html .= '<tr><td colspan="4"><b>Opening Balance</b></td><td align="right"><b>' . number_format($opening_balance, 2) . '</b></td></tr>';

    $balance = $opening_balance;
    if (empty($transactions)) {
        $html .= '<tr><td colspan="5" align="center">No transactions in this period.</td></tr>';
    } else {
        foreach ($transactions as $t) {
            $debit = is_numeric($t['debit']) ? $t['debit'] : 0;
            $credit = is_numeric($t['credit']) ? $t['credit'] : 0;
            $balance += $credit - $debit;

            $html .= '<tr>
                        <td>' . date('Y-m-d', strtotime($t['date'])) . '</td>
                        <td>' . htmlspecialchars($t['type']) . '</td>
                        <td align="right">' . ($debit > 0 ? number_format($debit, 2) : '') . '</td>
                        <td align="right">' . ($credit > 0 ? number_format($credit, 2) : '') . '</td>
                        <td align="right">' . number_format($balance, 2) . '</td>
                      </tr>';
        }
    }

    $html .= '<tr><td colspan="4"><b>Closing Balance</b></td><td align="right"><b>' . number_format($balance, 2) . '</b></td></tr>';

    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, true, false, '');

    // Close and output PDF document
    $pdf->Output('statement.pdf', 'I');

} catch (PDOException $e) {
    // In a real app, log this error
    header('Location: generate_statement.php?error=' . urlencode('A database error occurred.'));
    exit;
} catch (Exception $e) {
    header('Location: generate_statement.php?error=' . urlencode('An error occurred during PDF generation.'));
    exit;
}
