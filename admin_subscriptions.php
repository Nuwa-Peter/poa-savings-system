<?php
require_once 'includes/auth_check.php';
// Admins and Treasurers
check_permissions([1, 2, 4]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subscription'])) {
    $name = trim($_POST['name'] ?? '');
    $amount = round(str_replace(',', '', $_POST['amount'] ?? 0));
    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;

    if (empty($name) || $amount <= 0) {
        $error = "Please provide valid subscription details.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO subscriptions (name, amount, is_mandatory) VALUES (?, ?, ?)");
            $stmt->execute([$name, $amount, $is_mandatory]);
            $success = "Subscription fee added successfully.";
        } catch (PDOException $e) {
            $error = "Failed to add subscription.";
        }
    }
}

// Fetch all subscription types
try {
    $stmt = $pdo->query("SELECT * FROM subscriptions ORDER BY created_at DESC");
    $subscriptions = $stmt->fetchAll();

    // Fetch payment status for the current month
    $month = date('n');
    $year = date('Y');

    $status_stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.surname, u.account_no,
               sp.amount_paid, sp.paid_at, s.name as sub_name
        FROM users u
        CROSS JOIN subscriptions s
        LEFT JOIN subscription_payments sp ON u.id = sp.user_id AND s.id = sp.subscription_id AND sp.month = ? AND sp.year = ?
        WHERE u.status = 'active' AND s.is_mandatory = 1
        ORDER BY u.first_name ASC
    ");
    $status_stmt->execute([$month, $year]);
    $payment_statuses = $status_stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold mb-6" style="color: var(--text-primary);">Automated Subscriptions & Fees</h1>

    <?php if ($success): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">Success!</span> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Add Subscription -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Define New Fee</h2>
                <form action="admin_subscriptions.php" method="POST">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fee Name</label>
                        <input type="text" name="name" required placeholder="e.g. Monthly Membership" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Amount (UGX)</label>
                        <input type="text" inputmode="numeric" data-type="currency" name="amount" required class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="is_mandatory" checked class="mr-2">
                            Mandatory for all members
                        </label>
                    </div>
                    <button type="submit" name="add_subscription" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-200">
                        Add Subscription Fee
                    </button>
                </form>
            </div>

            <div class="mt-6 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Active Fees</h2>
                <ul class="space-y-3">
                    <?php foreach ($subscriptions as $s): ?>
                        <li class="flex justify-between items-center text-sm">
                            <span class="text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($s['name']); ?></span>
                            <span class="font-bold text-gray-900 dark:text-white"><?php echo number_format($s['amount'], 0); ?> UGX</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Compliance List -->
        <div class="lg:col-span-3">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Monthly Compliance Tracking (<?php echo date('F Y'); ?>)</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Member</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subscription</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($payment_statuses as $row): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white font-medium">
                                        <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['surname']); ?>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($row['account_no']); ?></p>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($row['sub_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <?php if ($row['amount_paid']): ?>
                                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Paid</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">Overdue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500">
                                        <?php echo $row['paid_at'] ? date('M j, Y', strtotime($row['paid_at'])) : '-'; ?>
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
