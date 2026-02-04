<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

// --- Redirect administrators to the admin dashboard ---
$user_role = $_SESSION['role_id'] ?? 0;
if (in_array($user_role, [1, 2, 3, 4])) {
    $query_string = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: admin_dashboard.php' . $query_string);
    exit;
}

require_once 'templates/header.php';
require_once 'includes/currency_converter.php';
?>

<?php
require_once 'config/db_connect.php';

$user_id = $_SESSION['user_id'];

// --- Weekly Savings Notification Logic ---
// Check if today is Sunday and if a notification needs to be sent.
// To prevent spamming, we'll also check if a similar notification has been sent in the last 6 days.
if (date('N') == 7) { // 7 = Sunday
    try {
        // 1. Check total savings for the current week (Monday to Sunday)
        $start_of_week = date('Y-m-d H:i:s', strtotime('monday this week'));
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM savings WHERE user_id = ? AND created_at >= ?");
        $stmt->execute([$user_id, $start_of_week]);
        $weekly_savings = $stmt->fetchColumn() ?: 0;

        if ($weekly_savings < 10000) {
            // 2. Check if a reminder was already sent this week to avoid duplicates
            $reminder_grace_period = date('Y-m-d H:i:s', strtotime('-6 days'));
            $notification_stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND message LIKE ? AND created_at >= ?"
            );
            $notification_stmt->execute([$user_id, '%Save 10,000 UGX today%', $reminder_grace_period]);
            $has_recent_reminder = $notification_stmt->fetchColumn() > 0;

            if (!$has_recent_reminder) {
                // 3. Insert the new notification
                $message = "Save 10,000 UGX today to stay on track for loan eligibility!";
                $insert_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $insert_stmt->execute([$user_id, $message]);
            }
        }
    } catch (PDOException $e) {
        // Log this error or handle it silently so it doesn't crash the dashboard
        error_log("Could not process weekly savings notification: " . $e->getMessage());
    }
}

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
            <p class="text-4xl font-bold text-indigo-600"><?php echo number_format($total_savings, 0); ?> <span class="text-2xl">UGX</span></p>
            <p class="text-lg text-gray-500 mt-2">~ $<?php echo number_format(convert_ugx_to_usd($total_savings), 2); ?> USD</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Active Loan Balance</h3>
            <p class="text-4xl font-bold text-red-600"><?php echo number_format($active_loan_balance, 0); ?> <span class="text-2xl">UGX</span></p>
            <p class="text-lg text-gray-500 mt-2">~ $<?php echo number_format(convert_ugx_to_usd($active_loan_balance), 2); ?> USD</p>
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
                                                        <span class="text-green-600">+<?php echo number_format($transaction['amount'], 0); ?> UGX</span>
                                                    <?php else: ?>
                                                        <span class="text-red-600">-<?php echo number_format($transaction['amount'], 0); ?> UGX</span>
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
                                    label += 'UGX ' + ugxValue.toLocaleString();
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
