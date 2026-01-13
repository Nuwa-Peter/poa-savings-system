<?php
require_once 'includes/auth_check.php';
// Only Root (role_id 1) and Chairman (role_id 2) can view logs
check_permissions([1, 2]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

try {
    // Fetch logs with user information
    $stmt = $pdo->query(
        'SELECT logs.id, logs.action, logs.timestamp, users.username
         FROM logs
         JOIN users ON logs.user_id = users.id
         ORDER BY logs.timestamp DESC'
    );
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle database errors
    echo '<div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">';
    echo '<span class="font-medium">Error!</span> Failed to retrieve logs: ' . $e->getMessage();
    echo '</div>';
    $logs = []; // Ensure $logs is an empty array on error
}
?>

<div class="container mx-auto mt-10">
    <h2 class="text-3xl font-bold mb-6 text-gray-800">System Activity Logs</h2>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Log ID</th>
                        <th class="py-3 px-6 text-left">Timestamp</th>
                        <th class="py-3 px-6 text-left">User</th>
                        <th class="py-3 px-6 text-left">Action</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm font-light">
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6 text-left whitespace-nowrap">
                                    <span class="font-medium"><?php echo htmlspecialchars($log['id']); ?></span>
                                </td>
                                <td class="py-3 px-6 text-left">
                                    <span><?php echo htmlspecialchars($log['timestamp']); ?></span>
                                </td>
                                <td class="py-3 px-6 text-left">
                                    <span class="font-semibold"><?php echo htmlspecialchars($log['username']); ?></span>
                                </td>
                                <td class="py-3 px-6 text-left">
                                    <p class="break-words"><?php echo htmlspecialchars($log['action']); ?></p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="border-b border-gray-200">
                            <td colspan="4" class="py-4 px-6 text-center text-gray-500">
                                No log entries found.
                            </td>
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
