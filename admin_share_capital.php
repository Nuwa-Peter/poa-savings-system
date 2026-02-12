<?php
require_once 'includes/auth_check.php';
// Admins and Treasurers
check_permissions([1, 2, 4]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_share_capital'])) {
    $user_id = $_POST['user_id'];
    $amount = round(str_replace(',', '', $_POST['amount'] ?? 0));
    $description = trim($_POST['description'] ?? 'Share Capital Purchase');

    if ($amount <= 0 || empty($user_id)) {
        $error = "Please provide a valid amount and member.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO share_capital (user_id, amount, description) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $amount, $description]);

            // Log the action
            $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
            $log_stmt->execute([$_SESSION['user_id'], "Recorded share capital for member ID $user_id: " . number_format($amount, 0) . " UGX"]);

            $success = "Share capital recorded successfully.";
        } catch (PDOException $e) {
            $error = "Failed to record share capital.";
        }
    }
}

// Fetch all share capital records
try {
    $stmt = $pdo->query("
        SELECT sc.*, u.first_name, u.surname, u.account_no
        FROM share_capital sc
        JOIN users u ON sc.user_id = u.id
        ORDER BY sc.created_at DESC
    ");
    $records = $stmt->fetchAll();

    // Fetch members for the dropdown
    $members_stmt = $pdo->query("SELECT id, first_name, surname, account_no FROM users WHERE status = 'active' AND id != 1 ORDER BY first_name ASC");
    $members = $members_stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold mb-6" style="color: var(--text-primary);">Manage Share Capital</h1>

    <?php if ($success): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">Success!</span> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Add Form -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Record Equity</h2>
                <form action="admin_share_capital.php" method="POST">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Member</label>
                        <select name="user_id" required class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                            <option value="">Select Member</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['surname'] . ' (' . $m['account_no'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Amount (UGX)</label>
                        <input type="text" inputmode="numeric" data-type="currency" name="amount" required class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
                        <input type="text" name="description" value="Share Capital Purchase" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <button type="submit" name="add_share_capital" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-200">
                        Record Purchase
                    </button>
                </form>
            </div>
        </div>

        <!-- List -->
        <div class="lg:col-span-3">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Share Capital Ledger</h2>
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
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No share capital records found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($records as $row): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['surname']); ?>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($row['account_no']); ?></p>
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
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
