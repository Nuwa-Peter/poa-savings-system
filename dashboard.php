<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

// --- Redirect administrators to the admin dashboard ---
$user_role = $_SESSION['role_id'] ?? 0;
if (in_array($user_role, [1, 2, 3, 4])) {
    header('Location: admin_dashboard.php');
    exit;
}

require_once 'config/app_config.php'; // Include currency config
require_once 'templates/header.php';
?>

<?php
require_once 'config/db_connect.php';

$user_id = $_SESSION['user_id'];
$total_savings = 0;
$active_loan_balance = 0;
$next_loan_payment = 'N/A';
$savings_dates = [];
$savings_amounts = [];

try {
    // Fetch total savings
    $total_stmt = $pdo->prepare("SELECT SUM(amount) as total FROM savings WHERE user_id = ?");
    $total_stmt->execute([$user_id]);
    $total_savings = $total_stmt->fetchColumn() ?: 0;

    // Fetch active loan details
    $loan_stmt = $pdo->prepare("SELECT balance, due_date FROM loans WHERE user_id = ? AND status = 'approved'");
    $loan_stmt->execute([$user_id]);
    $active_loan = $loan_stmt->fetch();
    if ($active_loan) {
        $active_loan_balance = $active_loan['balance'];
        $next_loan_payment = date('M j, Y', strtotime($active_loan['due_date']));
    }

    // Fetch savings history for the chart
    $chart_stmt = $pdo->prepare("SELECT amount, created_at FROM savings WHERE user_id = ? ORDER BY created_at ASC");
    $chart_stmt->execute([$user_id]);
    $savings_for_chart = $chart_stmt->fetchAll();

    foreach ($savings_for_chart as $saving) {
        $savings_dates[] = date('M j, Y', strtotime($saving['created_at']));
        $savings_amounts[] = $saving['amount'];
    }

    // --- Fetch all transaction types for the history list ---
    $transactions = [];

    // 1. Savings
    $savings_records = $pdo->prepare("SELECT amount, created_at FROM savings WHERE user_id = ?");
    $savings_records->execute([$user_id]);
    foreach ($savings_records->fetchAll() as $row) {
        $transactions[] = ['date' => strtotime($row['created_at']), 'type' => 'Saving', 'amount' => $row['amount'], 'status' => 'Approved'];
    }

    // 2. Withdrawals
    $withdrawal_records = $pdo->prepare("SELECT amount, status, requested_at FROM withdrawals WHERE user_id = ?");
    $withdrawal_records->execute([$user_id]);
    foreach ($withdrawal_records->fetchAll() as $row) {
        $transactions[] = ['date' => strtotime($row['requested_at']), 'type' => 'Withdrawal', 'amount' => $row['amount'], 'status' => $row['status']];
    }

    // 3. Loans
    $loan_records = $pdo->prepare("SELECT amount, status, requested_at FROM loans WHERE user_id = ?");
    $loan_records->execute([$user_id]);
    foreach ($loan_records->fetchAll() as $row) {
        $transactions[] = ['date' => strtotime($row['requested_at']), 'type' => 'Loan', 'amount' => $row['amount'], 'status' => $row['status']];
    }

    // Sort transactions by date descending
    usort($transactions, function($a, $b) {
        return $b['date'] - $a['date'];
    });


} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
}

// Fetch user's own logs
$user_logs = [];
try {
    $log_stmt = $pdo->prepare(
        "SELECT action, timestamp FROM logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 10"
    );
    $log_stmt->execute([$user_id]);
    $user_logs = $log_stmt->fetchAll();
} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
}
?>

<h2 class="text-3xl font-bold mb-6 text-gray-800">Dashboard</h2>

<?php if (isset($db_error)): ?>
    <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
        <span class="font-medium">Database Error!</span> <?php echo htmlspecialchars($db_error); ?>
    </div>
<?php endif; ?>

<!-- Main content grid -->
<div class="space-y-6">
    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Total Savings</h3>
            <p class="text-4xl font-bold text-indigo-600"><?php echo format_currency($total_savings, 'UGX'); ?></p>
            <p class="text-lg text-gray-500 mt-1"><?php echo format_currency(convert_ugx_to_usd($total_savings), 'USD'); ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Active Loan Balance</h3>
            <p class="text-4xl font-bold text-red-600"><?php echo number_format($active_loan_balance, 2); ?> <span class="text-2xl">UGX</span></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Next Loan Payment</h3>
            <p class="text-4xl font-bold text-gray-600"><?php echo $next_loan_payment; ?></p>
        </div>
    </div>

    <!-- Quick Actions -->
     <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Quick Actions</h3>
        <div class="flex flex-wrap gap-4">
            <a href="withdraw.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Request Withdrawal</a>
            <a href="request_loan.php" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">Apply for Loan</a>
            <a href="repay_loan.php" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">Repay Loan</a>
        </div>
    </div>

    <!-- Charts and History -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Savings Trend</h3>
            <canvas id="savingsLineChart"></canvas>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Transaction History</h3>
            <div class="overflow-auto max-h-96">
                <table class="min-w-full leading-normal">
                    <tbody class="text-gray-600 text-sm">
                        <?php if (count($transactions) > 0): ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr class="border-b border-gray-200">
                                    <td class="py-3 px-4">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($transaction['type']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo date('M j, Y, g:i a', $transaction['date']); ?></p>
                                            </div>
                                            <div class="text-right">
                                                <p class="font-semibold">
                                                    <?php if ($transaction['type'] === 'Saving'): ?>
                                                        <span class="text-green-600">+<?php echo format_currency($transaction['amount'], 'UGX'); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-red-600">-<?php echo format_currency($transaction['amount'], 'UGX'); ?></span>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="text-xs capitalize <?php
                                                    switch (strtolower($transaction['status'])) {
                                                        case 'approved': echo 'text-green-500'; break;
                                                        case 'pending': echo 'text-yellow-500'; break;
                                                        case 'rejected': echo 'text-red-500'; break;
                                                        case 'paid': echo 'text-blue-500'; break;
                                                        default: echo 'text-gray-500';
                                                    }
                                                ?>">
                                                    <?php echo htmlspecialchars($transaction['status']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td class="py-4 text-center text-gray-500">No transactions yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Line Chart for Savings Trend ---
    const lineCtx = document.getElementById('savingsLineChart').getContext('2d');
    if (lineCtx) {
        new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($savings_dates); ?>,
                datasets: [{
                    label: 'Savings Amount',
                    data: <?php echo json_encode($savings_amounts); ?>,
                    borderColor: 'rgba(79, 70, 229, 1)',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    const ugxValue = context.parsed.y;
                                    const usdValue = ugxValue * <?php echo EXCHANGE_RATE_UGX_TO_USD; ?>;
                                    label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'UGX', currencyDisplay: 'code' }).format(ugxValue);
                                    label += ` (${new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(usdValue)})`;
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'UGX ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>
