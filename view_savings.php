<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4]); // Admins only

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$selected_member_id = $_GET['member_id'] ?? 'all';
$members = [];
$savings = [];
$error = '';

try {
    // Fetch all active members for the dropdown
    $members_stmt = $pdo->query("SELECT id, first_name, surname FROM users WHERE status = 'active' ORDER BY first_name ASC");
    $members = $members_stmt->fetchAll();

    // Fetch savings data based on selection
    $sql = "SELECT s.id, s.amount, s.created_at, u.first_name, u.surname FROM savings s JOIN users u ON s.user_id = u.id";
    if ($selected_member_id !== 'all' && is_numeric($selected_member_id)) {
        $sql .= " WHERE s.user_id = ?";
        $savings_stmt = $pdo->prepare($sql);
        $savings_stmt->execute([$selected_member_id]);
    } else {
        $savings_stmt = $pdo->query($sql);
    }
    $savings = $savings_stmt->fetchAll();

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
        <form action="view_savings.php" method="GET" class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <label for="member_id" class="block text-gray-700 text-sm font-bold">Select Member:</label>
                <select name="member_id" id="member_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" onchange="this.form.submit()">
                    <option value="all" <?php echo $selected_member_id === 'all' ? 'selected' : ''; ?>>All Members</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?php echo htmlspecialchars($member['id']); ?>" <?php echo $selected_member_id == $member['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['surname']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <a href="download_savings_report.php?member_id=<?php echo htmlspecialchars($selected_member_id); ?>" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
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
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount (UGX)</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($savings)): ?>
                    <tr>
                        <td colspan="3" class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">No savings transactions found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($savings as $saving): ?>
                        <tr>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($saving['first_name'] . ' ' . $saving['surname']); ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-right"><?php echo number_format($saving['amount'], 2); ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo date('M j, Y, g:i a', strtotime($saving['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
