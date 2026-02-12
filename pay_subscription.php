<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_sub'])) {
    $sub_id = $_POST['subscription_id'];
    $month = date('n');
    $year = date('Y');

    try {
        // Fetch sub details
        $sub_stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ?");
        $sub_stmt->execute([$sub_id]);
        $sub = $sub_stmt->fetch();

        if (!$sub) {
            $error = "Subscription not found.";
        } else {
            // Check if already paid
            $check_stmt = $pdo->prepare("SELECT id FROM subscription_payments WHERE user_id = ? AND subscription_id = ? AND month = ? AND year = ?");
            $check_stmt->execute([$user_id, $sub_id, $month, $year]);

            if ($check_stmt->fetch()) {
                $error = "You have already paid this subscription for " . date('F Y') . ".";
            } else {
                $stmt = $pdo->prepare("INSERT INTO subscription_payments (user_id, subscription_id, amount_paid, month, year) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $sub_id, $sub['amount'], $month, $year]);
                $success = "Subscription paid successfully for " . date('F Y') . ".";
            }
        }
    } catch (PDOException $e) {
        $error = "Failed to process payment.";
    }
}

// Fetch unpaid mandatory subscriptions
try {
    $month = date('n');
    $year = date('Y');

    $stmt = $pdo->prepare("
        SELECT s.* FROM subscriptions s
        WHERE s.is_mandatory = 1
        AND s.id NOT IN (
            SELECT subscription_id FROM subscription_payments
            WHERE user_id = ? AND month = ? AND year = ?
        )
    ");
    $stmt->execute([$user_id, $month, $year]);
    $unpaid = $stmt->fetchAll();

    // Fetch payment history
    $history_stmt = $pdo->prepare("
        SELECT sp.*, s.name as sub_name
        FROM subscription_payments sp
        JOIN subscriptions s ON sp.subscription_id = s.id
        WHERE sp.user_id = ?
        ORDER BY sp.paid_at DESC
    ");
    $history_stmt->execute([$user_id]);
    $history = $history_stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Database error.";
}
?>

<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold mb-6" style="color: var(--text-primary);">My Subscriptions & Fees</h1>

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
        <!-- Pending Payments -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Pending for <?php echo date('F Y'); ?></h2>
                <?php if (empty($unpaid)): ?>
                    <p class="text-green-600 font-medium">All subscriptions are up to date!</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($unpaid as $s): ?>
                            <div class="p-4 border rounded-lg bg-gray-50 dark:bg-gray-700">
                                <p class="font-bold text-gray-800 dark:text-white"><?php echo htmlspecialchars($s['name']); ?></p>
                                <p class="text-indigo-600 font-bold mb-3"><?php echo number_format($s['amount'], 0); ?> UGX</p>
                                <form action="pay_subscription.php" method="POST">
                                    <input type="hidden" name="subscription_id" value="<?php echo $s['id']; ?>">
                                    <button type="submit" name="pay_sub" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold py-2 rounded transition">
                                        Pay Now
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- History -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Payment History</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subscription</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($history as $row): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($row['paid_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($row['sub_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('F', mktime(0, 0, 0, $row['month'], 10)) . ' ' . $row['year']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900 dark:text-white">
                                        <?php echo number_format($row['amount_paid'], 0); ?> UGX
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
