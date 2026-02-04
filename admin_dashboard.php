<?php
require_once 'includes/auth_check.php';
// Only admins can access this page
check_permissions([1, 2, 3, 4]);

require_once 'config/db_connect.php';
require_once 'includes/currency_converter.php';
require_once 'templates/header.php';

$interest_run_this_month = false;
try {
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'last_interest_run'");
    $last_run_timestamp = $stmt->fetchColumn();
    if ($last_run_timestamp && (new DateTime($last_run_timestamp))->format('Y-m') === (new DateTime())->format('Y-m')) {
        $interest_run_this_month = true;
    }
} catch (PDOException $e) {
    // Gracefully handle error, maybe log it, but don't block the dashboard
}

// --- Fetch Aggregate Data ---
$system_stats = [
    'total_savings' => 0,
    'total_loan_balance' => 0,
    'pending_withdrawals' => 0,
    'pending_loans' => 0
];
$admin_personal_stats = [
    'total_savings' => 0,
    'loan_balance' => 0
];
$admin_user_id = $_SESSION['user_id'];

try {
    // 1. System-wide stats (Excluding root user ID 1)
    $system_stats['total_savings'] = $pdo->query("SELECT SUM(amount) FROM savings WHERE user_id != 1")->fetchColumn() ?: 0;
    $system_stats['total_loan_balance'] = $pdo->query("SELECT SUM(balance) FROM loans WHERE status = 'approved' AND user_id != 1")->fetchColumn() ?: 0;

    // 3. Pending Withdrawals Count
    $system_stats['pending_withdrawals'] = $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'")->fetchColumn() ?: 0;

    // 4. Pending Loans Count
    $system_stats['pending_loans'] = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'pending'")->fetchColumn() ?: 0;

    // 2. Admin's personal stats
    $personal_savings_stmt = $pdo->prepare("SELECT SUM(amount) FROM savings WHERE user_id = ?");
    $personal_savings_stmt->execute([$admin_user_id]);
    $admin_personal_stats['total_savings'] = $personal_savings_stmt->fetchColumn() ?: 0;

    $personal_loan_stmt = $pdo->prepare("SELECT SUM(balance) FROM loans WHERE user_id = ? AND status = 'approved'");
    $personal_loan_stmt->execute([$admin_user_id]);
    $admin_personal_stats['loan_balance'] = $personal_loan_stmt->fetchColumn() ?: 0;

    // 5. Data for Savings Trend Chart (Aggregated for all members, excluding root)
    $savings_trend_stmt = $pdo->query(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total_savings
         FROM savings
         WHERE user_id != 1
         GROUP BY month
         ORDER BY month ASC"
    );
    $savings_trend_data = $savings_trend_stmt->fetchAll(PDO::FETCH_ASSOC);

    $savings_labels = [];
    $savings_values = [];
    foreach ($savings_trend_data as $row) {
        $savings_labels[] = date("M Y", strtotime($row['month'] . "-01"));
        $savings_values[] = $row['total_savings'];
    }

    // 6. Data for Admin's Personal Savings Trend Chart
    $personal_trend_stmt = $pdo->prepare(
        "SELECT amount, created_at FROM savings WHERE user_id = ? ORDER BY created_at ASC"
    );
    $personal_trend_stmt->execute([$admin_user_id]);
    $personal_history = $personal_trend_stmt->fetchAll(PDO::FETCH_ASSOC);

    $personal_labels = [];
    $personal_values = [];
    foreach ($personal_history as $row) {
        $personal_labels[] = date("M j, Y", strtotime($row['created_at']));
        $personal_values[] = $row['amount'];
    }

} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
}

?>

<div class="container mx-auto mt-10">
    <h2 class="text-3xl font-bold mb-6 text-gray-800">Administrator Dashboard</h2>

    <?php if (!$interest_run_this_month && in_array($_SESSION['role_id'], [1, 2])): ?>
        <div class="p-4 mb-6 text-sm text-yellow-700 bg-yellow-100 rounded-lg shadow-md" role="alert">
            <span class="font-medium">Action Required!</span> The monthly loan interest script has not been run for the current month.
            <a href="apply_interest.php" class="font-bold underline ml-2">Run it now</a>.
        </div>
    <?php endif; ?>

    <?php if (isset($db_error)): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Database Error!</span> <?php echo htmlspecialchars($db_error); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">Success!</span> <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Error!</span> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Total Savings</h3>
            <p class="text-4xl font-bold text-indigo-600"><?php echo number_format($system_stats['total_savings'], 0); ?> <span class="text-2xl">UGX</span></p>
            <p class="text-lg text-gray-500 mt-2">~ $<?php echo number_format(convert_ugx_to_usd($system_stats['total_savings']), 2); ?> USD</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Outstanding Loans</h3>
            <p class="text-4xl font-bold text-red-600"><?php echo number_format($system_stats['total_loan_balance'], 0); ?> <span class="text-2xl">UGX</span></p>
            <p class="text-lg text-gray-500 mt-2">~ $<?php echo number_format(convert_ugx_to_usd($system_stats['total_loan_balance']), 2); ?> USD</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Pending Withdrawals</h3>
            <p class="text-4xl font-bold text-yellow-600"><?php echo $system_stats['pending_withdrawals']; ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Pending Loans</h3>
            <p class="text-4xl font-bold text-yellow-600"><?php echo $system_stats['pending_loans']; ?></p>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
        <!-- Society Savings Trend Chart -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Society Savings Trend</h3>
            <canvas id="societySavingsChart"></canvas>
        </div>

        <!-- Personal Savings Trend Chart -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">My Personal Savings Trend</h3>
            <canvas id="personalSavingsChart"></canvas>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mt-8 bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Quick Actions</h3>
        <div class="flex flex-wrap gap-4">
            <a href="manage_requests.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Manage Requests</a>
            <a href="add_member.php" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">Add New Member</a>
            <a href="add_saving.php" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">Add a Saving</a>
            <?php if (in_array($_SESSION['role_id'], [1, 2])): ?>
                <a href="apply_interest.php" class="bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">Apply Loan Interest</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Savings Transactions -->
    <div class="mt-8 bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Recent Savings Transactions</h3>
        <div class="overflow-auto max-h-96">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Member</th>
                        <th class="py-3 px-4 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount (UGX)</th>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm">
                    <?php
                    $recent_savings = [];
                    try {
                        // Exclude root user (ID 1)
                        $stmt = $pdo->query("SELECT s.id, s.amount, s.created_at, u.first_name, u.surname FROM savings s JOIN users u ON s.user_id = u.id WHERE u.id != 1 ORDER BY s.created_at DESC LIMIT 10");
                        $recent_savings = $stmt->fetchAll();
                    } catch (PDOException $e) {
                        echo '<tr><td colspan="4" class="py-4 text-center text-red-500">Could not fetch savings.</td></tr>';
                    }

                    if (count($recent_savings) > 0):
                        foreach ($recent_savings as $saving):
                    ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-4"><?php echo htmlspecialchars($saving['first_name'] . ' ' . $saving['surname']); ?></td>
                                <td class="py-3 px-4 text-right"><?php echo number_format($saving['amount'], 0); ?></td>
                                <td class="py-3 px-4"><?php echo date('d M Y', strtotime($saving['created_at'])); ?></td>
                                <td class="py-3 px-4 flex items-center space-x-4">
                                    <a href="edit_saving.php?id=<?php echo $saving['id']; ?>" class="text-indigo-600 hover:text-indigo-900 font-semibold">Rectify</a>
                                    <a href="delete_saving.php?id=<?php echo $saving['id']; ?>" class="text-red-600 hover:text-red-900 font-semibold" onclick="return confirm('Are you sure you want to delete this saving transaction? This action cannot be undone.');">Delete</a>
                                </td>
                            </tr>
                    <?php
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="4" class="py-4 text-center text-gray-500">No savings transactions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Admin's Personal Account View -->
    <div class="mt-8 bg-gray-50 p-6 rounded-lg shadow-inner border">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">My Personal Account</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h4 class="text-lg font-semibold text-gray-700 mb-2">My Total Savings</h4>
                <p class="text-3xl font-bold text-indigo-600"><?php echo number_format($admin_personal_stats['total_savings'], 0); ?> <span class="text-xl">UGX</span></p>
                <p class="text-md text-gray-500 mt-2">~ $<?php echo number_format(convert_ugx_to_usd($admin_personal_stats['total_savings']), 2); ?> USD</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h4 class="text-lg font-semibold text-gray-700 mb-2">My Loan Balance</h4>
                <p class="text-3xl font-bold text-red-600"><?php echo number_format($admin_personal_stats['loan_balance'], 0); ?> <span class="text-xl">UGX</span></p>
                <p class="text-md text-gray-500 mt-2">~ $<?php echo number_format(convert_ugx_to_usd($admin_personal_stats['loan_balance']), 2); ?> USD</p>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const societyCtx = document.getElementById('societySavingsChart').getContext('2d');
    new Chart(societyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($savings_labels ?? []); ?>,
            datasets: [{
                label: 'Total Society Savings per Month',
                data: <?php echo json_encode($savings_values ?? []); ?>,
                borderColor: 'rgba(79, 70, 229, 1)',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'UGX ' + value.toLocaleString(); }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Total: UGX ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    const personalCtx = document.getElementById('personalSavingsChart').getContext('2d');
    new Chart(personalCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($personal_labels ?? []); ?>,
            datasets: [{
                label: 'Savings Amount',
                data: <?php echo json_encode($personal_values ?? []); ?>,
                borderColor: 'rgba(16, 185, 129, 1)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'UGX ' + value.toLocaleString(); }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Amount: UGX ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php
require_once 'templates/footer.php';
?>
