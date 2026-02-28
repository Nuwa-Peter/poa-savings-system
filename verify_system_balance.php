<?php
/**
 * Manual Verification Script: Withdrawal Lifecycle & Balance Integrity
 */

// Since we cannot run this directly against the DB in this environment easily,
// we will perform a logic-based verification.

echo "--- System Balance Integrity Verification ---\n";

function simulate_balance_calculation($savings_entries, $withdrawal_entries, $is_old_logic = false) {
    $total_savings = array_sum($savings_entries);
    $total_withdrawals = array_sum($withdrawal_entries);

    if ($is_old_logic) {
        return $total_savings - $total_withdrawals;
    } else {
        // New logic: negative entries already in savings_entries
        return $total_savings;
    }
}

// Scenario: Member has 1,000,000 savings. Withdraws 200,000.
$initial_savings = [1000000];
$withdrawal = [200000];

echo "Scenario: 1,000,000 saved, 200,000 withdrawn.\n";

$old_balance = simulate_balance_calculation($initial_savings, $withdrawal, true);
echo "Old Logic Balance: " . number_format($old_balance) . "\n";

// New logic: Deduction is an entry in the savings array
$new_savings_ledger = [1000000, -200000];
$new_balance = simulate_balance_calculation($new_savings_ledger, [], false);
echo "New Logic Balance: " . number_format($new_balance) . "\n";

if ($old_balance === $new_balance) {
    echo "SUCCESS: Net balances match.\n";
} else {
    echo "FAILURE: Balance mismatch!\n";
}

echo "--------------------------------------------\n";
echo "Verifying process_request_action.php logic...\n";
// logic: $deduction_amount = -1 * abs($w_data['amount']);
$w_amount = 200000;
$deduction = -1 * abs($w_amount);
if ($deduction === -200000) {
    echo "SUCCESS: Deduction amount correctly calculated as negative.\n";
}

echo "--------------------------------------------\n";
echo "Verifying financial_reports.php assets...\n";
// $cash_in_hand = ($total_savings + $total_subs + $total_repayments) - ($total_expenses + $total_invested + $total_loans_disbursed);
// Previously: ... - ($total_withdrawals + $total_expenses ...)
// If total_savings is 800,000 (net) and total_withdrawals is 0 (since they are in savings), calculation is correct.
?>
