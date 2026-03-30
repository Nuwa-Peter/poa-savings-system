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
            $sql = "SELECT s.amount, s.created_at, u.first_name, u.surname
                    FROM savings s
                    JOIN users u ON s.user_id = u.id
                    WHERE s.user_id = ?
                    ORDER BY s.created_at DESC";
            $savings_stmt = $pdo->prepare($sql);
            $savings_stmt->execute([$selected_member_id]);
        } else {
            $sql = "SELECT s.amount, s.created_at, u.first_name, u.surname
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

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
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
            <div>
                <a href="download_savings_report.php?member_id=<?php echo htmlspecialchars($selected_member_id); ?>&view_mode=<?php echo htmlspecialchars($view_mode); ?>" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Download as PDF
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <table class="min-w-full leading-normal">
            <thead>
                <tr>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Member</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider"><?php echo $view_mode === 'history' ? 'Amount (UGX)' : 'Total Saved (UGX)'; ?></th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider"><?php echo $view_mode === 'history' ? 'Date & Time' : 'Last Check Date'; ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($savings)): ?>
                    <tr>
                        <td colspan="3" class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">No savings records found.</td>
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
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
