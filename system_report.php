<?php
require_once 'includes/auth_check.php';
// Restrict access to Root, Chairman, Secretary, and Treasurer
check_permissions([1, 2, 3, 4]);

require_once 'config/app_config.php';
require_once 'templates/header.php';
require_once 'config/db_connect.php';

$total_system_savings = 0;
$savings_by_user = [];
$db_error = '';

try {
    // 1. Fetch total savings across the entire system (Excluding root and chairman)
    $total_stmt = $pdo->query("SELECT SUM(s.amount) as total FROM savings s JOIN users u ON s.user_id = u.id WHERE u.id != 1 AND u.role_id != 2");
    $total_system_savings = $total_stmt->fetchColumn() ?? 0;

    // 2. Fetch savings grouped by user (Excluding root and chairman)
    $user_savings_stmt = $pdo->query(
        "SELECT u.id, u.username, u.account_no, SUM(s.amount) as total_saved
         FROM users u
         JOIN savings s ON u.id = s.user_id
         WHERE u.id != 1 AND u.role_id != 2 -- Exclude system users from report
         GROUP BY u.id, u.username, u.account_no
         ORDER BY total_saved DESC"
    );
    $savings_by_user = $user_savings_stmt->fetchAll();

} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
}
?>

<div class="container mx-auto">
    <h2 class="text-3xl font-bold mb-6 text-gray-800">System Financial Report</h2>

    <?php if ($db_error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <?php echo htmlspecialchars($db_error); ?>
        </div>
    <?php endif; ?>

    <!-- System Wide Summary Card -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h3 class="text-xl font-semibold text-gray-700 mb-2">Total System-Wide Savings</h3>
        <p class="text-4xl font-bold text-indigo-600"><?php echo format_currency($total_system_savings, 'UGX'); ?></p>
        <p class="text-lg text-gray-500 mt-1"><?php echo format_currency(convert_ugx_to_usd($total_system_savings), 'USD'); ?></p>
    </div>

    <!-- Savings by Member Table -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="px-6 py-4">
            <h3 class="text-xl font-semibold text-gray-700">Savings by Member</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Member</th>
                        <th class="py-3 px-6 text-right">Total Saved (UGX)</th>
                        <th class="py-3 px-6 text-right">Total Saved (USD)</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm font-light">
                    <?php if (count($savings_by_user) > 0): ?>
                        <?php foreach ($savings_by_user as $user_saving): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6 text-left whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="ml-3">
                                            <p class="font-semibold"><?php echo htmlspecialchars($user_saving['username']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user_saving['account_no']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-6 text-right">
                                    <?php echo format_currency($user_saving['total_saved'], 'UGX'); ?>
                                </td>
                                <td class="py-3 px-6 text-right">
                                    <?php echo format_currency(convert_ugx_to_usd($user_saving['total_saved']), 'USD'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="py-4 text-center">No savings data available for any member yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
