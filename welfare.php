<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contribute'])) {
    $amount = round(str_replace(',', '', $_POST['amount'] ?? 0));
    $description = trim($_POST['description'] ?? 'Monthly Welfare Contribution');

    if ($amount <= 0) {
        $error = "Please enter a valid amount.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO welfare_contributions (user_id, amount, description) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $amount, $description]);

            // Log action
            $stmt = $pdo->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
            $stmt->execute([$user_id, "Made a welfare contribution of UGX " . number_format($amount, 0)]);

            $success = "Thank you for your contribution of UGX " . number_format($amount, 0);
        } catch (PDOException $e) {
            $error = "Failed to process contribution: " . $e->getMessage();
        }
    }
}

// Fetch history
try {
    $stmt = $pdo->prepare("SELECT amount, description, created_at FROM welfare_contributions WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll();

    $total_contributed = array_sum(array_column($history, 'amount'));
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold mb-6" style="color: var(--text-primary);">Welfare & Insurance Fund</h1>

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
        <!-- Contribution Form -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Make a Contribution</h2>
                <form action="welfare.php" method="POST">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Amount (UGX)</label>
                        <input type="number" step="1" name="amount" required class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
                        <input type="text" name="description" placeholder="e.g. June Contribution" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <button type="submit" name="contribute" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-200">
                        Contribute
                    </button>
                </form>
                <div class="mt-6 p-4 bg-indigo-50 dark:bg-indigo-900 rounded-lg">
                    <p class="text-sm text-indigo-800 dark:text-indigo-200">
                        <strong>Your Total Contributions:</strong> <?php echo number_format($total_contributed ?? 0, 0); ?> UGX
                    </p>
                </div>
            </div>
        </div>

        <!-- History -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Contribution History</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">No contributions yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history as $row): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($row['description']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900 dark:text-white">
                                            <?php echo number_format($row['amount'], 0); ?> UGX
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
