<?php
require_once 'includes/auth_check.php';
// Only Root (1) and Chairman (2) can access this page
check_permissions([1, 2]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

// Pagination logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Sorting logic
$sort_column = $_GET['sort'] ?? 'timestamp';
$sort_order = $_GET['order'] ?? 'desc';
$valid_columns = ['log_id', 'username', 'action', 'timestamp'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'timestamp';
$sort_order = strtolower($sort_order) === 'asc' ? 'ASC' : 'DESC';

try {
    // Get total number of logs for pagination
    $total_logs_stmt = $pdo->query("SELECT COUNT(*) FROM logs");
    $total_logs = $total_logs_stmt->fetchColumn();
    $total_pages = ceil($total_logs / $limit);

    // Fetch logs with user information, sorted and paginated
    $sql = "SELECT l.id as log_id, l.action, l.timestamp, u.username
            FROM logs l
            JOIN users u ON l.user_id = u.id
            ORDER BY {$sort_column} {$sort_order}
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $logs = [];
    $error = "Failed to load audit logs.";
}

function sort_link($column, $text, $current_column, $current_order) {
    $order = ($current_column === $column && $current_order === 'DESC') ? 'asc' : 'desc';
    $arrow = ($current_column === $column) ? ($current_order === 'DESC' ? ' &darr;' : ' &uarr;') : '';
    return "<a href=\"?sort={$column}&order={$order}&page=1\">{$text}{$arrow}</a>";
}
?>

<div class="container mx-auto mt-10 p-4">
    <div class="bg-white dark:bg-gray-800 p-8 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6">System Audit View</h1>
        <p class="text-gray-600 dark:text-gray-400 mb-6">A detailed log of all significant actions performed within the system.</p>

        <?php if (isset($error)): ?>
            <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert">
                <span class="font-medium">Error!</span> <?php echo $error; ?>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo sort_link('log_id', 'Log ID', $sort_column, $sort_order); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo sort_link('username', 'User', $sort_column, $sort_order); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo sort_link('action', 'Action', $sort_column, $sort_order); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo sort_link('timestamp', 'Timestamp', $sort_column, $sort_order); ?></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($log['log_id']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($log['username']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($log['action']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls -->
            <div class="mt-6 flex justify-between items-center">
                <span class="text-sm text-gray-700 dark:text-gray-400">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </span>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sort_column; ?>&order=<?php echo $sort_order; ?>" class="px-4 py-2 text-sm text-white bg-blue-500 rounded hover:bg-blue-600">&larr; Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sort_column; ?>&order=<?php echo $sort_order; ?>" class="px-4 py-2 text-sm text-white bg-blue-500 rounded hover:bg-blue-600">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
