<?php
require_once 'includes/auth_check.php';
// Only Root (1) and Chairman (2) can access this page
check_permissions([1, 2]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';
require_once 'includes/CreditScoreHelper.php';

$scoreHelper = new CreditScoreHelper($pdo);

// Logic for sorting
$sort_column = $_GET['sort'] ?? 'id';
$sort_order = $_GET['order'] ?? 'asc';
$valid_columns = ['id', 'username', 'first_name', 'surname', 'email', 'phone', 'account_no', 'created_at'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'id';
$sort_order = strtolower($sort_order) === 'desc' ? 'DESC' : 'ASC';

try {
    $stmt = $pdo->prepare("SELECT id, username, first_name, surname, email, phone, account_no, avatar, created_at FROM users WHERE status = 'active' ORDER BY {$sort_column} {$sort_order}");
    $stmt->execute();
    $members = $stmt->fetchAll();
} catch (PDOException $e) {
    $members = [];
    $error = "Failed to load member list.";
}

// Function to generate sorting links
function sort_link($column, $text, $current_column, $current_order) {
    $order = ($current_column === $column && $current_order === 'ASC') ? 'desc' : 'asc';
    $arrow = ($current_column === $column) ? ($current_order === 'ASC' ? ' &uarr;' : ' &darr;') : '';
    return "<a href=\"?sort={$column}&order={$order}\">{$text}{$arrow}</a>";
}
?>

<div class="container mx-auto mt-10 p-4">
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-xl border border-slate-200 dark:border-slate-800 overflow-hidden transition-all">
        <div class="p-8 border-b border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-6 bg-slate-50/50 dark:bg-slate-800/50">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Member Directory</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1">Manage and view details of all society members.</p>
            </div>
            <div class="relative group">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400 group-focus-within:text-indigo-500 transition-colors"></i>
                <input type="text" id="member-search" placeholder="Search by name, account, email..." class="pl-10 pr-4 py-3 border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-2xl text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all w-80 shadow-sm">
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="p-8 text-center text-red-600 font-medium">
                <?php echo $error; ?>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800" data-interactive="true" data-search-input="member-search" data-pagination="15">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/80 backdrop-blur-sm sticky top-0 z-10">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Avatar</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider"><?php echo sort_link('id', 'ID', $sort_column, $sort_order); ?></th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider"><?php echo sort_link('first_name', 'First Name', $sort_column, $sort_order); ?></th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider"><?php echo sort_link('surname', 'Surname', $sort_column, $sort_order); ?></th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider"><?php echo sort_link('username', 'Username', $sort_column, $sort_order); ?></th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider"><?php echo sort_link('email', 'Email', $sort_column, $sort_order); ?></th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider"><?php echo sort_link('phone', 'Phone', $sort_column, $sort_order); ?></th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Credit Score</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider"><?php echo sort_link('account_no', 'Account No.', $sort_column, $sort_order); ?></th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider"><?php echo sort_link('created_at', 'Joined On', $sort_column, $sort_order); ?></th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($members)): ?>
                            <tr>
                                <td colspan="10" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">No members found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        <?php display_avatar($member['avatar'], $member['username'], $member['first_name'], $member['surname']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($member['id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($member['first_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($member['surname']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($member['username']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($member['email']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($member['phone']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php
                                            $score = $scoreHelper->getScore($member['id']);
                                            $label = $scoreHelper->getScoreLabel($score);
                                        ?>
                                        <span class="font-bold <?php echo $label['color']; ?>"><?php echo $score; ?></span>
                                        <span class="text-[10px] uppercase opacity-70 block"><?php echo $label['label']; ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($member['account_no']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo date('M j, Y', strtotime($member['created_at'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex gap-3">
                                        <a href="view_member.php?id=<?php echo $member['id']; ?>" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-900 bg-indigo-50 px-2 py-1 rounded transition-colors">
                                            <i data-lucide="user" class="w-3.5 h-3.5"></i> Profile
                                        </a>
                                        <a href="edit_user.php?id=<?php echo $member['id']; ?>" class="inline-flex items-center gap-1 text-slate-600 hover:text-slate-900 bg-slate-50 px-2 py-1 rounded transition-colors">
                                            <i data-lucide="edit-2" class="w-3.5 h-3.5"></i> Edit
                                        </a>
                                    <?php if (in_array($_SESSION['role_id'], [1, 2])): ?>
                                        <a href="delete_member.php?id=<?php echo $member['id']; ?>" class="inline-flex items-center gap-1 text-red-600 hover:text-red-900 bg-red-50 px-2 py-1 rounded transition-colors font-semibold">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
                                        </a>
                                    <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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
