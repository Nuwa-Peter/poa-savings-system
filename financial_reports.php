<?php
require_once 'includes/auth_check.php';
// Only Root, Chairman, Treasurer
check_permissions([1, 2, 4]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$error = '';

try {
    // --- ASSETS ---
    // --- ASSETS ---
    // 1. Cash in Hand (Total Savings + Subscriptions + Repayments - Withdrawals - Expenses - Investments)
    $total_savings = $pdo->query("SELECT SUM(amount) FROM savings")->fetchColumn() ?: 0;
    $total_subs = $pdo->query("SELECT SUM(amount_paid) FROM subscription_payments")->fetchColumn() ?: 0;
    $total_repayments = $pdo->query("SELECT SUM(amount) FROM loan_payments")->fetchColumn() ?: 0;
    $total_withdrawals = $pdo->query("SELECT SUM(amount) FROM withdrawals WHERE status = 'approved'")->fetchColumn() ?: 0;
    $total_expenses = $pdo->query("SELECT SUM(amount) FROM expenses")->fetchColumn() ?: 0;
    $total_invested = $pdo->query("SELECT SUM(amount_invested) FROM investments")->fetchColumn() ?: 0;
    $total_loans_disbursed = $pdo->query("SELECT SUM(amount) FROM loans WHERE status = 'approved'")->fetchColumn() ?: 0;

    $cash_in_hand = ($total_savings + $total_subs + $total_repayments) - ($total_withdrawals + $total_expenses + $total_invested + $total_loans_disbursed);

    // 2. Loans Outstanding
    $loans_outstanding = $pdo->query("SELECT SUM(balance) FROM loans WHERE status = 'approved'")->fetchColumn() ?: 0;

    // 3. Investments (Current Value)
    $investments_value = $pdo->query("SELECT SUM(current_value) FROM investments WHERE status = 'active'")->fetchColumn() ?: 0;

    $total_assets = $cash_in_hand + $loans_outstanding + $investments_value;

    // --- LIABILITIES ---
    // 1. Member Savings
    $member_savings = $total_savings - $total_withdrawals;

    // 2. Share Capital
    $share_capital = $pdo->query("SELECT SUM(amount) FROM share_capital")->fetchColumn() ?: 0;

    $total_liabilities = $member_savings + $share_capital;

    // --- EQUITY ---
    $retained_earnings = $total_assets - $total_liabilities;

    // --- INCOME (P&L for Current Year) ---
    $year = date('Y');
    $interest_income = $pdo->query("SELECT SUM(balance) - SUM(amount) FROM loans WHERE status='closed'")->fetchColumn() ?: 0;
    // Note: Simple interest income calculation above is a placeholder. Real SACCOs track interest separately.

    $fee_income = $total_subs;
    $investment_gain = $investments_value - $total_invested;

    $total_income = $interest_income + $fee_income + $investment_gain;

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold mb-8" style="color: var(--text-primary);">Advanced Financial Reporting</h1>

    <?php if ($error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Balance Sheet -->
        <div class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700">
            <h2 class="text-2xl font-bold mb-6 text-gray-800 dark:text-white border-b pb-4">Balance Sheet</h2>

            <div class="space-y-6">
                <!-- Assets -->
                <div>
                    <h3 class="text-lg font-bold text-indigo-600 mb-3 uppercase tracking-wider">Assets</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Cash & Bank Balances</span>
                            <span class="font-semibold text-gray-900 dark:text-white"><?php echo number_format($cash_in_hand, 0); ?> UGX</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Loans Receivable (Outstanding)</span>
                            <span class="font-semibold text-gray-900 dark:text-white"><?php echo number_format($loans_outstanding, 0); ?> UGX</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Investments (Market Value)</span>
                            <span class="font-semibold text-gray-900 dark:text-white"><?php echo number_format($investments_value, 0); ?> UGX</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t font-bold text-gray-900 dark:text-white">
                            <span>Total Assets</span>
                            <span><?php echo number_format($total_assets, 0); ?> UGX</span>
                        </div>
                    </div>
                </div>

                <!-- Liabilities -->
                <div>
                    <h3 class="text-lg font-bold text-rose-600 mb-3 uppercase tracking-wider">Liabilities</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Member Savings (Deposits)</span>
                            <span class="font-semibold text-gray-900 dark:text-white"><?php echo number_format($member_savings, 0); ?> UGX</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Share Capital</span>
                            <span class="font-semibold text-gray-900 dark:text-white"><?php echo number_format($share_capital, 0); ?> UGX</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t font-bold text-gray-900 dark:text-white">
                            <span>Total Liabilities</span>
                            <span><?php echo number_format($total_liabilities, 0); ?> UGX</span>
                        </div>
                    </div>
                </div>

                <!-- Equity -->
                <div class="pt-4 border-t-2 border-double">
                    <div class="flex justify-between text-lg font-bold text-emerald-600 uppercase">
                        <span>Retained Earnings (Equity)</span>
                        <span><?php echo number_format($retained_earnings, 0); ?> UGX</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Income Statement -->
        <div class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700">
            <h2 class="text-2xl font-bold mb-6 text-gray-800 dark:text-white border-b pb-4">Income Statement (P&L)</h2>

            <div class="space-y-6">
                <!-- Revenue -->
                <div>
                    <h3 class="text-lg font-bold text-emerald-600 mb-3 uppercase tracking-wider">Revenue</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Interest on Loans</span>
                            <span class="font-semibold text-gray-900 dark:text-white"><?php echo number_format($interest_income, 0); ?> UGX</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Membership Fees & Subscriptions</span>
                            <span class="font-semibold text-gray-900 dark:text-white"><?php echo number_format($fee_income, 0); ?> UGX</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Investment Gains</span>
                            <span class="font-semibold text-gray-900 dark:text-white"><?php echo number_format($investment_gain, 0); ?> UGX</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t font-bold text-gray-900 dark:text-white">
                            <span>Total Income</span>
                            <span><?php echo number_format($total_income, 0); ?> UGX</span>
                        </div>
                    </div>
                </div>

                <!-- Expenses -->
                <div>
                    <h3 class="text-lg font-bold text-rose-600 mb-3 uppercase tracking-wider">Expenses</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Operating Expenses</span>
                            <span class="font-semibold text-gray-900 dark:text-white"><?php echo number_format($total_expenses, 0); ?> UGX</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t font-bold text-gray-900 dark:text-white">
                            <span>Total Expenses</span>
                            <span><?php echo number_format($total_expenses, 0); ?> UGX</span>
                        </div>
                    </div>
                </div>

                <!-- Net Surplus -->
                <div class="pt-4 border-t-2 border-double">
                    <?php $surplus = $total_income - $total_expenses; ?>
                    <div class="flex justify-between text-xl font-black <?php echo ($surplus >= 0) ? 'text-blue-600' : 'text-red-600'; ?> uppercase">
                        <span>Net Surplus / (Deficit)</span>
                        <span><?php echo number_format($surplus, 0); ?> UGX</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
