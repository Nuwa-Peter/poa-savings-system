<?php
require_once 'includes/auth_check.php';
// Admins with reporting privileges
check_permissions([1, 2, 4]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$error = '';
$history = [];

try {
    // Total fund balance (Excluding root and chairman)
    $total_stmt = $pdo->query("SELECT SUM(wc.amount) FROM welfare_contributions wc JOIN users u ON wc.user_id = u.id WHERE u.id != 1 AND u.role_id != 2");
    $total_fund = $total_stmt->fetchColumn() ?: 0;

    // Recent contributions
    $history_stmt = $pdo->query("
        SELECT wc.amount, wc.description, wc.created_at, u.username, u.first_name, u.surname
        FROM welfare_contributions wc
        JOIN users u ON wc.user_id = u.id
        WHERE u.id != 1 AND u.role_id != 2
        ORDER BY wc.created_at DESC
        LIMIT 50
    ");
    $history = $history_stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold mb-6" style="color: var(--text-primary);">Welfare Fund Administration</h1>

    <?php if ($error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Error!</span> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Total Welfare Fund Balance</h3>
            <p class="text-4xl font-bold text-indigo-600"><?php echo number_format($total_fund, 0); ?> <span class="text-2xl">UGX</span></p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
        <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Recent Contributions</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Member</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No contributions found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $row): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo date('M j, Y, g:i a', strtotime($row['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['surname'] . ' (' . $row['username'] . ')'); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($row['description']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900 dark:text-white">
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
