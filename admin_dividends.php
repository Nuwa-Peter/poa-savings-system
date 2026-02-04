<?php
require_once 'includes/auth_check.php';
// Only Root (1) and Chairman (2) can manage dividends
check_permissions([1, 2]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$error = '';
$success = '';

// Fetch all members with their total savings
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.surname, u.account_no, COALESCE(SUM(s.amount), 0) as total_savings
        FROM users u
        LEFT JOIN savings s ON u.id = s.user_id
        WHERE u.status = 'active' AND u.id != 1
        GROUP BY u.id
        ORDER BY u.first_name ASC
    ");
    $members = $stmt->fetchAll();

    $total_society_savings = array_sum(array_column($members, 'total_savings'));
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['distribute'])) {
    $distribution_type = $_POST['type'] ?? 'percentage';
    $value = floatval($_POST['value'] ?? 0);
    $description = trim($_POST['description'] ?? 'Annual Dividend Distribution');

    if ($value <= 0) {
        $error = "Please enter a valid positive value.";
    } else {
        try {
            $pdo->beginTransaction();

            foreach ($members as $member) {
                $dividend_amount = 0;
                if ($distribution_type === 'percentage') {
                    $dividend_amount = round($member['total_savings'] * ($value / 100));
                } else {
                    // Proportional distribution of a total pool amount
                    if ($total_society_savings > 0) {
                        $dividend_amount = round(($member['total_savings'] / $total_society_savings) * $value);
                    }
                }

                if ($dividend_amount > 0) {
                    $stmt = $pdo->prepare("INSERT INTO dividends (user_id, amount, description) VALUES (?, ?, ?)");
                    $stmt->execute([$member['id'], $dividend_amount, $description]);

                    // Notify member
                    $notify_msg = "You have received a dividend of UGX " . number_format($dividend_amount, 0) . ". Reason: $description";
                    $notify_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $notify_stmt->execute([$member['id'], $notify_msg]);
                }
            }

            $pdo->commit();
            $success = "Dividends distributed successfully to " . count($members) . " members.";

            // Refresh member data
            $stmt = $pdo->query("
                SELECT u.id, u.first_name, u.surname, u.account_no, COALESCE(SUM(s.amount), 0) as total_savings
                FROM users u
                LEFT JOIN savings s ON u.id = s.user_id
                WHERE u.status = 'active' AND u.id != 1
                GROUP BY u.id
                ORDER BY u.first_name ASC
            ");
            $members = $stmt->fetchAll();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to distribute dividends: " . $e->getMessage();
        }
    }
}
?>

<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold mb-6" style="color: var(--text-primary);">Dividends Distribution</h1>

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
        <!-- Distribution Form -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">New Distribution</h2>
                <form action="admin_dividends.php" method="POST" onsubmit="return confirm('Are you sure you want to distribute dividends? This action cannot be undone.');">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Distribution Type</label>
                        <select name="type" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                            <option value="percentage">Percentage of Individual Savings (%)</option>
                            <option value="pool">Total Pool Amount to Distribute Proportionally (UGX)</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Value</label>
                        <input type="number" step="0.01" name="value" required class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
                        <input type="text" name="description" placeholder="e.g. 2023 Annual Dividends" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <button type="submit" name="distribute" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-200">
                        Distribute Dividends
                    </button>
                </form>
                <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900 rounded-lg">
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        <strong>Total Society Savings:</strong> <?php echo number_format($total_society_savings, 0); ?> UGX
                    </p>
                </div>
            </div>
        </div>

        <!-- Member List Preview -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Member Savings Overview</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Member</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">A/C No</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Savings</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['surname']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($member['account_no']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900 dark:text-white">
                                        <?php echo number_format($member['total_savings'], 0); ?> UGX
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
