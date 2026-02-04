<?php
require_once 'includes/auth_check.php';
// Admins and Treasurers
check_permissions([1, 2, 4]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_investment'])) {
        $name = trim($_POST['name'] ?? '');
        $amount = round(str_replace(',', '', $_POST['amount'] ?? 0));
        $date = $_POST['date'] ?? date('Y-m-d');
        $rate = floatval($_POST['rate'] ?? 0);

        if (empty($name) || $amount <= 0) {
            $error = "Invalid investment details.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO investments (investment_name, amount_invested, current_value, investment_date, expected_return_rate) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $amount, $amount, $date, $rate]);
                $success = "Investment recorded.";
            } catch (PDOException $e) {
                $error = "Failed to record investment.";
            }
        }
    } elseif (isset($_POST['update_value'])) {
        $id = $_POST['id'];
        $new_value = round(str_replace(',', '', $_POST['current_value'] ?? 0));
        $status = $_POST['status'] ?? 'active';

        try {
            $stmt = $pdo->prepare("UPDATE investments SET current_value = ?, status = ? WHERE id = ?");
            $stmt->execute([$new_value, $status, $id]);
            $success = "Investment updated.";
        } catch (PDOException $e) {
            $error = "Failed to update investment.";
        }
    }
}

// Fetch investments
try {
    $stmt = $pdo->query("SELECT * FROM investments ORDER BY investment_date DESC");
    $investments = $stmt->fetchAll();

    $total_invested = array_sum(array_column($investments, 'amount_invested'));
    $total_current = array_sum(array_column($investments, 'current_value'));
    $net_gain = $total_current - $total_invested;
} catch (PDOException $e) {
    $error = "Database error.";
}
?>

<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold mb-6" style="color: var(--text-primary);">Investment Portfolio</h1>

    <?php if ($success): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">Success!</span> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Total Amount Invested</h3>
            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo number_format($total_invested ?? 0, 0); ?> UGX</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Current Portfolio Value</h3>
            <p class="text-3xl font-bold text-blue-600"><?php echo number_format($total_current ?? 0, 0); ?> UGX</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Net Portfolio Gain/Loss</h3>
            <p class="text-3xl font-bold <?php echo ($net_gain >= 0) ? 'text-green-600' : 'text-red-600'; ?>">
                <?php echo ($net_gain >= 0 ? '+' : '') . number_format($net_gain ?? 0, 0); ?> UGX
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Add Investment -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Record New Investment</h2>
                <form action="admin_investments.php" method="POST">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Investment Name</label>
                        <input type="text" name="name" required placeholder="e.g. Treasury Bill #402" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Amount Invested (UGX)</label>
                        <input type="number" step="1" name="amount" required class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Expected Return (%)</label>
                        <input type="number" step="0.01" name="rate" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date</label>
                        <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <button type="submit" name="add_investment" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-200">
                        Record Investment
                    </button>
                </form>
            </div>
        </div>

        <!-- Portfolio List -->
        <div class="lg:col-span-3">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Active Portfolio</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Investment</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Invested</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Current Value</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($investments as $row): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white font-medium">
                                        <?php echo htmlspecialchars($row['investment_name']); ?>
                                        <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($row['investment_date'])); ?></p>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">
                                        <?php echo number_format($row['amount_invested'], 0); ?> UGX
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-blue-600">
                                        <?php echo number_format($row['current_value'], 0); ?> UGX
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $row['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <form action="admin_investments.php" method="POST" class="inline-flex items-center space-x-2">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <input type="number" step="1" name="current_value" value="<?php echo (int)$row['current_value']; ?>" class="w-24 p-1 border rounded dark:bg-gray-700 dark:text-white text-xs">
                                            <select name="status" class="p-1 border rounded dark:bg-gray-700 dark:text-white text-xs">
                                                <option value="active" <?php echo $row['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="matured" <?php echo $row['status'] === 'matured' ? 'selected' : ''; ?>>Matured</option>
                                                <option value="sold" <?php echo $row['status'] === 'sold' ? 'selected' : ''; ?>>Sold</option>
                                            </select>
                                            <button type="submit" name="update_value" class="text-blue-600 hover:text-blue-900 font-bold">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
