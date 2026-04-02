<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4]); // Admins only

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$selected_member_id = $_GET['member_id'] ?? 'all';
$view_mode = $_GET['view_mode'] ?? 'summary';
$members = [];
$savings = [];
$error = '';

try {
    // Fetch all active members with their total savings for the dropdown (excluding root ID 1)
    $members_stmt = $pdo->query("
        SELECT u.id, u.first_name, u.surname, COALESCE(SUM(s.amount), 0) as total_saved
        FROM users u
        LEFT JOIN savings s ON u.id = s.user_id
        WHERE u.status = 'active' AND u.id != 1
        GROUP BY u.id
        ORDER BY u.first_name ASC
    ");
    $members = $members_stmt->fetchAll();

    if ($view_mode === 'history') {
        // Detailed History View
        if ($selected_member_id !== 'all' && is_numeric($selected_member_id)) {
            $sql = "SELECT s.id, s.amount, s.created_at, u.first_name, u.surname
                    FROM savings s
                    JOIN users u ON s.user_id = u.id
                    WHERE s.user_id = ?
                    ORDER BY s.created_at DESC";
            $savings_stmt = $pdo->prepare($sql);
            $savings_stmt->execute([$selected_member_id]);
        } else {
            $sql = "SELECT s.id, s.amount, s.created_at, u.first_name, u.surname
                    FROM savings s
                    JOIN users u ON s.user_id = u.id
                    WHERE u.id != 1
                    ORDER BY s.created_at DESC";
            $savings_stmt = $pdo->query($sql);
        }
        $savings = $savings_stmt->fetchAll();
    } else {
        // Summary View (Grouped by Member)
        if ($selected_member_id !== 'all' && is_numeric($selected_member_id)) {
            $sql = "SELECT u.id as user_id, u.first_name, u.surname, COALESCE(SUM(s.amount), 0) as total_saved
                    FROM users u
                    LEFT JOIN savings s ON u.id = s.user_id
                    WHERE u.status = 'active' AND u.id = ?
                    GROUP BY u.id";
            $savings_stmt = $pdo->prepare($sql);
            $savings_stmt->execute([$selected_member_id]);
        } else {
            $sql = "SELECT u.id as user_id, u.first_name, u.surname, COALESCE(SUM(s.amount), 0) as total_saved
                    FROM users u
                    LEFT JOIN savings s ON u.id = s.user_id
                    WHERE u.status = 'active' AND u.id != 1
                    GROUP BY u.id
                    ORDER BY total_saved DESC";
            $savings_stmt = $pdo->query($sql);
        }
        $savings = $savings_stmt->fetchAll();
    }

    // Calculate grand total savings for the footer
    $total_savings = 0;
    foreach ($savings as $row) {
        $total_savings += ($view_mode === 'history' ? $row['amount'] : $row['total_saved']);
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10">
    <h2 class="text-2xl font-bold mb-5">View Member Savings</h2>

    <?php if ($error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 mb-6 transition-all">
        <form action="view_savings.php" method="GET" class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-4">
                <div>
                    <label for="member_id" class="block text-gray-700 text-sm font-bold mb-1">Select Member:</label>
                    <select name="member_id" id="member_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" onchange="this.form.submit()">
                        <option value="all" <?php echo $selected_member_id === 'all' ? 'selected' : ''; ?>>All Members</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo htmlspecialchars($member['id']); ?>" <?php echo $selected_member_id == $member['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['surname'] . ' (' . number_format($member['total_saved'], 0) . ' UGX)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="view_mode" class="block text-gray-700 text-sm font-bold mb-1">View Mode:</label>
                    <select name="view_mode" id="view_mode" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" onchange="this.form.submit()">
                        <option value="summary" <?php echo $view_mode === 'summary' ? 'selected' : ''; ?>>Summary (Totals)</option>
                        <option value="history" <?php echo $view_mode === 'history' ? 'selected' : ''; ?>>Detailed History</option>
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="relative">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                    <input type="text" id="table-search" placeholder="Search records..." class="pl-9 pr-4 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all w-64">
                </div>
                <a href="download_savings_report.php?member_id=<?php echo htmlspecialchars($selected_member_id); ?>&view_mode=<?php echo htmlspecialchars($view_mode); ?>" class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition-colors shadow-sm">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    PDF
                </a>
                <a href="export_savings.php?member_id=<?php echo htmlspecialchars($selected_member_id); ?>&view_mode=<?php echo htmlspecialchars($view_mode); ?>" class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition-colors shadow-sm">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                    CSV
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden transition-all">
        <table class="min-w-full leading-normal" data-interactive="true" data-search-input="table-search" data-pagination="15">
            <thead>
                <tr>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Member</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider"><?php echo $view_mode === 'history' ? 'Amount (UGX)' : 'Total Saved (UGX)'; ?></th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider"><?php echo $view_mode === 'history' ? 'Date & Time' : 'Last Check Date'; ?></th>
                    <?php if ($view_mode === 'history' && in_array($_SESSION['role_id'], [1, 2, 3])): ?>
                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($savings)): ?>
                    <tr>
                        <td colspan="3" class="px-5 py-10 bg-white">
                            <?php echo renderEmptyState('database', 'No Savings Found', 'There are no savings records matching your criteria.', 'Add a Saving', 'add_saving.php'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($savings as $saving): ?>
                        <tr>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($saving['first_name'] . ' ' . $saving['surname']); ?></span>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-right font-bold text-gray-900">
                                <?php echo number_format($view_mode === 'history' ? $saving['amount'] : $saving['total_saved'], 0); ?>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-gray-600">
                                <?php echo $view_mode === 'history' ? date('M j, Y, g:i a', strtotime($saving['created_at'])) : date('M j, Y, g:i a'); ?>
                            </td>
                            <?php if ($view_mode === 'history' && in_array($_SESSION['role_id'], [1, 2, 3])): ?>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <a href="edit_saving.php?id=<?php echo $saving['id']; ?>" class="text-indigo-600 hover:text-indigo-900 inline-flex items-center gap-1">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        <span>Rectify</span>
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($savings)): ?>
                <tfoot>
                    <tr>
                        <th class="px-5 py-3 border-t-2 border-gray-200 bg-gray-100 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Total</th>
                        <th class="px-5 py-3 border-t-2 border-gray-200 bg-gray-100 text-right text-xs font-bold text-gray-700 uppercase tracking-wider"><?php echo number_format($total_savings, 0); ?> UGX</th>
                        <th class="px-5 py-3 border-t-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider"></th>
                        <?php if ($view_mode === 'history' && in_array($_SESSION['role_id'], [1, 2, 3])): ?>
                            <th class="px-5 py-3 border-t-2 border-gray-200 bg-gray-100"></th>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
