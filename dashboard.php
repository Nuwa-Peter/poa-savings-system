<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

// --- Redirect administrators to the admin dashboard (unless viewing personal) ---
$user_role = $_SESSION['role_id'] ?? 0;
$view_mode = $_GET['view'] ?? '';
if (in_array($user_role, [1, 2, 3, 4]) && $view_mode !== 'personal') {
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

// --- Loan Due Date Notification Logic ---
try {
    // Find approved loans for this user that are due within 7 days
    $due_soon_stmt = $pdo->prepare("
        SELECT id, balance, due_date
        FROM loans
        WHERE user_id = ? AND status = 'approved' AND due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY) AND balance > 0
    ");
    $due_soon_stmt->execute([$user_id]);
    $loans_due = $due_soon_stmt->fetchAll();

    foreach ($loans_due as $loan) {
        $days_left = ceil((strtotime($loan['due_date']) - time()) / 86400);
        $msg_prefix = ($days_left <= 0) ? "Your loan is OVERDUE" : "Your loan is due in $days_left days";
        $message = "$msg_prefix. Please make a repayment of " . number_format($loan['balance'], 0) . " UGX by " . date('M j, Y', strtotime($loan['due_date'])) . ".";

        // Check if we already sent a reminder today for this loan
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND message LIKE ? AND created_at >= CURDATE()");
        $check_stmt->execute([$user_id, "Loan #{$loan['id']}%"]);
        if ($check_stmt->fetchColumn() == 0) {
             // Prefix message with Loan ID for tracking
             $full_message = "Loan #{$loan['id']}: $message";
             $insert_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
             $insert_stmt->execute([$user_id, $full_message]);
        }
    }
} catch (PDOException $e) {
    error_log("Could not process loan due reminders: " . $e->getMessage());
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

    $personal_cumulative_values = [];
    $personal_running_total = 0;
    foreach ($savings_for_chart as $saving) {
        $personal_running_total += $saving['amount'];
        $savings_dates[] = date('M j, Y', strtotime($saving['created_at']));
        $personal_cumulative_values[] = $personal_running_total;
    }

    // --- Fetch Society Cumulative Savings (Each deposit) ---
    $society_savings_stmt = $pdo->query(
        "SELECT s.amount, s.created_at FROM savings s JOIN users u ON s.user_id = u.id ORDER BY s.created_at ASC"
    );
    $society_savings_history = $society_savings_stmt->fetchAll(PDO::FETCH_ASSOC);

    $society_cumulative_labels = [];
    $society_cumulative_values = [];
    $society_running_total = 0;
    foreach ($society_savings_history as $row) {
        $society_running_total += $row['amount'];
        $society_cumulative_labels[] = date("M j, Y H:i", strtotime($row['created_at']));
        $society_cumulative_values[] = $society_running_total;
    }

    // --- Fetch all transaction types for the history list ---
    $transactions = [];

    // 1. Savings & Withdrawals (Deductions)
    $savings_records = $pdo->prepare("SELECT amount, description, created_at FROM savings WHERE user_id = ?");
    $savings_records->execute([$user_id]);
    foreach ($savings_records->fetchAll() as $row) {
        $type = ($row['amount'] < 0) ? 'Withdrawal (Deduction)' : 'Saving';
        $transactions[] = ['date' => strtotime($row['created_at']), 'type' => $type, 'amount' => $row['amount'], 'status' => 'Approved'];
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

<h2 class="text-3xl font-bold mb-6 text-gray-800 hidden lg:block">Dashboard</h2>

<?php if (isset($db_error)): ?>
    <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
        <span class="font-medium">Database Error!</span> <?php echo htmlspecialchars($db_error); ?>
    </div>
<?php endif; ?>

<!-- Mobile Dashboard View -->
<div class="lg:hidden space-y-6 -mt-4 pb-20">
    <!-- Mobile Header -->
    <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 -mx-4 px-6 pt-10 pb-16 rounded-b-[3rem] shadow-lg relative overflow-hidden">
        <div class="relative z-10 text-white">
            <p class="text-indigo-100 text-sm font-medium opacity-80 mb-1">Total Balance</p>
            <h1 class="text-4xl font-bold tracking-tight mb-4"><?php echo number_format($total_savings, 0); ?> <span class="text-lg font-normal opacity-70">UGX</span></h1>

            <div class="flex gap-4">
                <div class="bg-white/10 backdrop-blur-md px-4 py-2 rounded-2xl border border-white/10 flex-1">
                    <p class="text-[10px] uppercase font-bold text-indigo-200 mb-0.5">Loan Debt</p>
                    <p class="text-lg font-bold"><?php echo number_format($active_loan_balance, 0); ?></p>
                </div>
                <div class="bg-white/10 backdrop-blur-md px-4 py-2 rounded-2xl border border-white/10 flex-1">
                    <p class="text-[10px] uppercase font-bold text-indigo-200 mb-0.5">Next Pay</p>
                    <p class="text-sm font-bold truncate"><?php echo $next_loan_payment !== 'N/A' ? date('M j', strtotime($next_loan_payment)) : 'None'; ?></p>
                </div>
            </div>
        </div>
        <!-- Decorative blobs -->
        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/5 rounded-full blur-3xl"></div>
        <div class="absolute -left-10 -top-10 w-40 h-40 bg-indigo-400/10 rounded-full blur-3xl"></div>
    </div>

    <!-- Mobile Quick Actions Grid -->
    <div class="grid grid-cols-4 gap-4 px-2">
        <a href="withdraw.php" class="flex flex-col items-center gap-2 group">
            <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 shadow-sm border border-orange-100 group-active:scale-95 transition-transform">
                <i data-lucide="arrow-up-right" class="w-6 h-6"></i>
            </div>
            <span class="text-[11px] font-bold text-slate-600">Withdraw</span>
        </a>
        <a href="request_loan.php" class="flex flex-col items-center gap-2 group">
            <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 shadow-sm border border-blue-100 group-active:scale-95 transition-transform">
                <i data-lucide="landmark" class="w-6 h-6"></i>
            </div>
            <span class="text-[11px] font-bold text-slate-600">Loan</span>
        </a>
        <a href="repay_loan.php" class="flex flex-col items-center gap-2 group">
            <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 shadow-sm border border-indigo-100 group-active:scale-95 transition-transform">
                <i data-lucide="wallet" class="w-6 h-6"></i>
            </div>
            <span class="text-[11px] font-bold text-slate-600">Repay</span>
        </a>
        <a href="view_savings.php" class="flex flex-col items-center gap-2 group">
            <div class="w-14 h-14 bg-green-50 rounded-2xl flex items-center justify-center text-green-600 shadow-sm border border-green-100 group-active:scale-95 transition-transform">
                <i data-lucide="plus-circle" class="w-6 h-6"></i>
            </div>
            <span class="text-[11px] font-bold text-slate-600">Save</span>
        </a>
    </div>

    <!-- Mobile Progress Summary -->
    <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 mx-1">
        <h3 class="text-sm font-bold text-slate-800 mb-4 flex justify-between items-center">
            <span>Savings Goal</span>
            <span class="text-indigo-600 text-xs font-semibold">View All</span>
        </h3>
        <div class="relative pt-1">
            <div class="flex mb-2 items-center justify-between">
                <div>
                    <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-indigo-600 bg-indigo-200">
                        Task Progress
                    </span>
                </div>
                <div class="text-right">
                    <span class="text-xs font-semibold inline-block text-indigo-600">
                        75%
                    </span>
                </div>
            </div>
            <div class="overflow-hidden h-2 mb-4 text-xs flex rounded-full bg-indigo-100">
                <div style="width:75%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-500 transition-all duration-500"></div>
            </div>
            <p class="text-[10px] text-slate-500">You've saved 750,000 UGX of your 1,000,000 UGX goal.</p>
        </div>
    </div>

    <!-- Mobile Recent Activity (Simplified) -->
    <div class="space-y-3 px-1">
        <h3 class="text-sm font-bold text-slate-800 px-1">Recent Activity</h3>
        <?php foreach (array_slice($transactions, 0, 5) as $transaction): ?>
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between group active:bg-slate-50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center <?php
                        echo $transaction['type'] === 'Saving' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600';
                    ?>">
                        <i data-lucide="<?php echo $transaction['type'] === 'Saving' ? 'arrow-down-left' : 'arrow-up-right'; ?>" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($transaction['type']); ?></p>
                        <p class="text-[10px] text-slate-400 font-medium"><?php echo date('M j, Y', $transaction['date']); ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-bold <?php echo $transaction['type'] === 'Saving' ? 'text-green-600' : 'text-slate-800'; ?>">
                        <?php echo ($transaction['type'] === 'Saving' ? '+' : '-') . number_format($transaction['amount'], 0); ?>
                    </p>
                    <p class="text-[10px] font-bold uppercase tracking-wider opacity-60"><?php echo htmlspecialchars($transaction['status']); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
        <a href="view_savings.php?view_mode=history" class="block text-center py-3 text-sm font-bold text-indigo-600 bg-indigo-50 rounded-2xl">
            See All Transactions
        </a>
    </div>
</div>

<!-- Desktop Dashboard View -->
<div class="hidden lg:block space-y-6">
    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                    <i data-lucide="piggy-bank" class="w-6 h-6"></i>
                </div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Savings</span>
            </div>
            <p class="text-3xl font-bold text-slate-900"><?php echo number_format($total_savings, 0); ?> <span class="text-sm font-normal text-slate-500">UGX</span></p>
            <p class="text-xs text-slate-500 mt-1">~ $<?php echo number_format(convert_ugx_to_usd($total_savings ?? 0), 2); ?> USD</p>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-rose-50 rounded-lg text-rose-600">
                    <i data-lucide="landmark" class="w-6 h-6"></i>
                </div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Loan Balance</span>
            </div>
            <p class="text-3xl font-bold text-slate-900"><?php echo number_format($active_loan_balance, 0); ?> <span class="text-sm font-normal text-slate-500">UGX</span></p>
            <p class="text-xs text-slate-500 mt-1">~ $<?php echo number_format(convert_ugx_to_usd($active_loan_balance ?? 0), 2); ?> USD</p>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-amber-50 rounded-lg text-amber-600">
                    <i data-lucide="calendar" class="w-6 h-6"></i>
                </div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Next Payment</span>
            </div>
            <p class="text-3xl font-bold text-slate-900"><?php echo $next_loan_payment; ?></p>
            <p class="text-xs text-slate-500 mt-1">Scheduled date</p>
        </div>
    </div>

    <!-- Quick Actions -->
     <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Quick Actions</h3>
        <div class="flex flex-wrap gap-3">
            <a href="withdraw.php" class="flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-semibold py-2.5 px-5 rounded-xl transition-all shadow-sm">
                <i data-lucide="arrow-up-circle" class="w-4 h-4 text-orange-500"></i>
                <span>Request Withdrawal</span>
            </a>
            <a href="request_loan.php" class="flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-semibold py-2.5 px-5 rounded-xl transition-all shadow-sm">
                <i data-lucide="landmark" class="w-4 h-4 text-blue-500"></i>
                <span>Apply for Loan</span>
            </a>
            <a href="repay_loan.php" class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-5 rounded-xl transition-all shadow-sm">
                <i data-lucide="wallet" class="w-4 h-4"></i>
                <span>Repay Loan</span>
            </a>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 gap-8">
        <div class="bg-white p-8 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-2xl font-bold text-slate-800 mb-6">Savings Progress</h3>
            <canvas id="savingsLineChart"></canvas>
        </div>
        <div class="bg-white p-8 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-2xl font-bold text-slate-800 mb-6">Society Savings Trend</h3>
            <canvas id="societyCumulativeChart"></canvas>
        </div>
    </div>

    <!-- History -->
    <div class="grid grid-cols-1 gap-6">
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
</div> <!-- End Desktop View -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Personal Savings Chart ---
    const lineCtx = document.getElementById('savingsLineChart').getContext('2d');
    if (lineCtx) {
        new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($savings_dates); ?>,
                datasets: [{
                    label: 'My Balance',
                    data: <?php echo json_encode($personal_cumulative_values); ?>,
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                aspectRatio: 3,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return 'UGX ' + value.toLocaleString(); }
                        }
                    }
                }
            }
        });
    }

    // --- Society Cumulative Chart ---
    const societyCtx = document.getElementById('societyCumulativeChart').getContext('2d');
    if (societyCtx) {
        new Chart(societyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($society_cumulative_labels); ?>,
                datasets: [{
                    label: 'Society Total',
                    data: <?php echo json_encode($society_cumulative_values); ?>,
                    borderColor: 'rgba(79, 70, 229, 1)',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    fill: true,
                    tension: 0.1,
                    pointRadius: 1
                }]
            },
            options: {
                responsive: true,
                aspectRatio: 3,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return 'UGX ' + value.toLocaleString(); }
                        }
                    },
                    x: { display: false }
                }
            }
        });
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>
