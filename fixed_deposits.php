<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_fd'])) {
    $amount = round(str_replace(',', '', $_POST['amount'] ?? 0));
    $duration = intval($_POST['duration'] ?? 6); // Months

    // Interest rates based on duration
    $rates = [3 => 5, 6 => 7, 12 => 10, 24 => 15];
    $interest_rate = $rates[$duration] ?? 5;

    // Check if user has enough available savings
    try {
        // Calculate total savings
        $savings_stmt = $pdo->prepare("SELECT SUM(amount) as total_savings FROM savings WHERE user_id = ?");
        $savings_stmt->execute([$user_id]);
        $total_savings = $savings_stmt->fetchColumn() ?: 0;

        // Calculate total withdrawals (approved or pending)
        $withdrawal_stmt = $pdo->prepare("SELECT SUM(amount) as total_withdrawals FROM withdrawals WHERE user_id = ? AND status IN ('pending', 'approved')");
        $withdrawal_stmt->execute([$user_id]);
        $total_withdrawals = $withdrawal_stmt->fetchColumn() ?: 0;

        // Calculate total active fixed deposits
        $fd_stmt = $pdo->prepare("SELECT SUM(amount) as total_fd FROM fixed_deposits WHERE user_id = ? AND status = 'active'");
        $fd_stmt->execute([$user_id]);
        $total_fd = $fd_stmt->fetchColumn() ?: 0;

        $available_balance = $total_savings - $total_withdrawals - $total_fd;

        if ($amount <= 0 || $amount > $available_balance) {
            $error = "Insufficient available balance. You can only lock up to " . number_format($available_balance, 0) . " UGX.";
        } else {
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime("+$duration months"));

            $stmt = $pdo->prepare("INSERT INTO fixed_deposits (user_id, amount, interest_rate, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$user_id, $amount, $interest_rate, $start_date, $end_date]);

            $success = "Savings locked successfully until " . date('M j, Y', strtotime($end_date)) . ".";
        }
    } catch (PDOException $e) {
        $error = "Database error.";
    }
}

// Fetch active fixed deposits
try {
    $stmt = $pdo->prepare("SELECT * FROM fixed_deposits WHERE user_id = ? ORDER BY end_date ASC");
    $stmt->execute([$user_id]);
    $fds = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error.";
}
?>

<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold mb-6" style="color: var(--text-primary);">Fixed Deposits & Locked Savings</h1>

    <?php if ($success): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">Success!</span> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Error!</span> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Create FD Form -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Lock Savings</h2>
                <form action="fixed_deposits.php" method="POST">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Amount to Lock (UGX)</label>
                        <input type="text" inputmode="numeric" data-type="currency" name="amount" required class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Duration</label>
                        <select name="duration" required class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                            <option value="3">3 Months (5% p.a.)</option>
                            <option value="6">6 Months (7% p.a.)</option>
                            <option value="12">12 Months (10% p.a.)</option>
                            <option value="24">24 Months (15% p.a.)</option>
                        </select>
                    </div>
                    <p class="text-xs text-gray-500 mb-4 italic">Locked amounts cannot be withdrawn until the maturity date.</p>
                    <button type="submit" name="create_fd" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded transition duration-200">
                        Create Fixed Deposit
                    </button>
                </form>
            </div>
        </div>

        <!-- FD List -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">My Fixed Deposits</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Maturity Date</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Principal</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Rate</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if (empty($fds)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center">
                                        <?php echo renderEmptyState('lock', 'No Locked Savings', 'You do not have any active fixed deposits.', 'Lock Funds', '#'); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($fds as $row): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            <?php echo date('M j, Y', strtotime($row['end_date'])); ?>
                                            <p class="text-xs text-gray-500">Started: <?php echo date('M j, Y', strtotime($row['start_date'])); ?></p>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900 dark:text-white">
                                            <?php echo number_format($row['amount'], 0); ?> UGX
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500">
                                            <?php echo $row['interest_rate']; ?>%
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $row['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
