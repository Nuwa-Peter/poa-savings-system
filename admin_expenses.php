<?php
require_once 'includes/auth_check.php';
// Admins and Treasurers
check_permissions([1, 2, 4]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$success = '';
$error = '';
$admin_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $amount = round(str_replace(',', '', $_POST['amount'] ?? 0));
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date = $_POST['date_incurred'] ?? date('Y-m-d');

    if ($amount <= 0 || empty($category)) {
        $error = "Please provide a valid amount and category.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO expenses (amount, category, description, date_incurred, admin_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$amount, $category, $description, $date, $admin_id]);
            $success = "Expense recorded successfully.";
        } catch (PDOException $e) {
            $error = "Failed to record expense.";
        }
    }
}

// Fetch expenses
try {
    $stmt = $pdo->query("
        SELECT e.*, u.username as recorded_by
        FROM expenses e
        JOIN users u ON e.admin_id = u.id
        ORDER BY e.date_incurred DESC
        LIMIT 100
    ");
    $expenses = $stmt->fetchAll();

    $total_expenses = array_sum(array_column($expenses, 'amount'));
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold mb-6" style="color: var(--text-primary);">Society Expense Tracking</h1>

    <?php if ($success): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">Success!</span> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Error!</span> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Add Expense Form -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Record New Expense</h2>
                <form action="admin_expenses.php" method="POST">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Amount (UGX)</label>
                        <input type="number" step="1" name="amount" required class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category</label>
                        <select name="category" required class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                            <option value="Rent">Rent</option>
                            <option value="Utilities">Utilities</option>
                            <option value="Stationary">Stationary</option>
                            <option value="Salaries">Salaries</option>
                            <option value="Taxes">Taxes</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date Incurred</label>
                        <input type="date" name="date_incurred" required value="<?php echo date('Y-m-d'); ?>" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
                        <textarea name="description" rows="2" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white"></textarea>
                    </div>
                    <button type="submit" name="add_expense" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded transition duration-200">
                        Record Expense
                    </button>
                </form>
            </div>
        </div>

        <!-- Expense List -->
        <div class="lg:col-span-3">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Expense Log</h2>
                    <p class="text-lg font-bold text-red-600">Total: <?php echo number_format($total_expenses ?? 0, 0); ?> UGX</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if (empty($expenses)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No expenses recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($expenses as $row): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo date('M j, Y', strtotime($row['date_incurred'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            <span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs"><?php echo htmlspecialchars($row['category']); ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($row['description']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-red-600">
                                            -<?php echo number_format($row['amount'], 0); ?> UGX
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
