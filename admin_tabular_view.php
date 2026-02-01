<?php
require_once 'includes/auth_check.php';
// Only Root (1) and Chairman (2) can access this page
check_permissions([1, 2]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

// Default tab and sorting
$tab = $_GET['tab'] ?? 'savings';
$sort_column = $_GET['sort'] ?? 'id';
$sort_order = $_GET['order'] ?? 'desc';
$sort_order_sql = strtolower($sort_order) === 'asc' ? 'ASC' : 'DESC';

$data = [];
$error = '';
$columns = [];

try {
    switch ($tab) {
        case 'withdrawals':
            $valid_columns = ['id', 'username', 'amount', 'status', 'requested_at', 'processed_at'];
            $sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'id';
            // Exclude root user (ID 1)
            $sql = "SELECT w.id, u.username, w.amount, w.status, w.requested_at, w.processed_at FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE u.id != 1 ORDER BY {$sort_column} {$sort_order_sql}";
            $columns = ['ID', 'User', 'Amount', 'Status', 'Requested', 'Processed'];
            break;
        case 'loans':
            $valid_columns = ['id', 'username', 'amount', 'balance', 'status', 'requested_at', 'approved_at'];
            $sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'id';
            // Exclude root user (ID 1)
            $sql = "SELECT l.id, u.username, l.amount, l.balance, l.status, l.requested_at, l.approved_at FROM loans l JOIN users u ON l.user_id = u.id WHERE u.id != 1 ORDER BY {$sort_column} {$sort_order_sql}";
            $columns = ['ID', 'User', 'Amount', 'Balance', 'Status', 'Requested', 'Approved'];
            break;
        case 'savings':
        default:
            $valid_columns = ['id', 'username', 'amount', 'created_at'];
            $sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'id';
            // Exclude root user (ID 1)
            $sql = "SELECT s.id, u.username, s.amount, s.created_at FROM savings s JOIN users u ON s.user_id = u.id WHERE u.id != 1 ORDER BY {$sort_column} {$sort_order_sql}";
            $columns = ['ID', 'User', 'Amount', 'Date'];
            break;
    }
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Failed to load data for tab: {$tab}.";
}

function sort_link($column, $text, $current_tab, $current_column, $current_order) {
    $order = ($current_column === $column && $current_order === 'DESC') ? 'asc' : 'desc';
    $arrow = ($current_column === $column) ? ($current_order === 'DESC' ? ' &darr;' : ' &uarr;') : '';
    return "<a href=\"?tab={$current_tab}&sort={$column}&order={$order}\">{$text}{$arrow}</a>";
}
?>

<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold mb-6" style="color: var(--text-primary);">Tabular Data View</h1>
    <p class="text-gray-600 dark:text-gray-400 mb-6">A raw data view of the core financial records in the system.</p>

    <!-- Tab Navigation -->
    <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="myTab" role="tablist">
            <li class="mr-2" role="presentation">
                <a href="?tab=savings" class="inline-block p-4 border-b-2 rounded-t-lg <?php echo $tab === 'savings' ? 'border-blue-500 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300'; ?>">Savings</a>
            </li>
            <li class="mr-2" role="presentation">
                <a href="?tab=withdrawals" class="inline-block p-4 border-b-2 rounded-t-lg <?php echo $tab === 'withdrawals' ? 'border-blue-500 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300'; ?>">Withdrawals</a>
            </li>
            <li class="mr-2" role="presentation">
                <a href="?tab=loans" class="inline-block p-4 border-b-2 rounded-t-lg <?php echo $tab === 'loans' ? 'border-blue-500 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300'; ?>">Loans</a>
            </li>
        </ul>
    </div>

    <!-- Table Content -->
    <div class="bg-white dark:bg-gray-800 p-8 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
        <?php if ($error): ?>
            <p class="text-red-500"><?php echo $error; ?></p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <?php
                            $column_keys = array_keys($data[0] ?? []);
                            foreach ($columns as $index => $col_name):
                                $key = $column_keys[$index] ?? $col_name; // Fallback for simple cases
                            ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    <?php echo sort_link(strtolower($key), $col_name, $tab, $sort_column, $sort_order); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <?php foreach ($row as $value): ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($value); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                         <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="<?php echo count($columns); ?>" class="text-center py-4">No data available in this table.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
