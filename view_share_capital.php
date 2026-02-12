<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$user_id = $_SESSION['user_id'];
$records = [];
$total_equity = 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM share_capital WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $records = $stmt->fetchAll();

    $total_equity = array_sum(array_column($records, 'amount'));
} catch (PDOException $e) {
    $error = "Database error.";
}
?>

<div class="container mx-auto mt-10 p-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold" style="color: var(--text-primary);">My Share Capital</h1>
        <div class="bg-indigo-600 text-white px-6 py-3 rounded-xl shadow-lg">
            <p class="text-sm uppercase opacity-80">Total Equity</p>
            <p class="text-2xl font-bold"><?php echo number_format($total_equity, 0); ?> UGX</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
        <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Transaction History</h2>
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
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-10 text-center">
                                <?php echo renderEmptyState('award', 'No Equity Found', 'You have not purchased any share capital yet.', 'Contact Admin', '#'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($row['description']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-indigo-600">
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

<?php require_once 'templates/footer.php'; ?>
