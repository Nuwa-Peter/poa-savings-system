<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_goal'])) {
    $name = trim($_POST['goal_name'] ?? '');
    $target = round(str_replace(',', '', $_POST['target_amount'] ?? 0));
    $deadline = $_POST['deadline'] ?? null;

    if (empty($name) || $target <= 0) {
        $error = "Please provide a valid name and target amount.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO savings_goals (user_id, goal_name, target_amount, deadline) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $name, $target, $deadline]);
            $success = "Savings goal added successfully!";
        } catch (PDOException $e) {
            $error = "Failed to add goal: " . $e->getMessage();
        }
    }
}

if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM savings_goals WHERE id = ? AND user_id = ?");
        $stmt->execute([$_GET['delete'], $user_id]);
        $success = "Goal deleted.";
    } catch (PDOException $e) {
        $error = "Failed to delete goal.";
    }
}

// Fetch total savings
try {
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM savings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_savings = $stmt->fetchColumn() ?: 0;

    // Fetch goals
    $stmt = $pdo->prepare("SELECT * FROM savings_goals WHERE user_id = ? ORDER BY deadline ASC");
    $stmt->execute([$user_id]);
    $goals = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold mb-6" style="color: var(--text-primary);">Savings Goals</h1>

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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Add Goal Form -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Set a New Goal</h2>
                <form action="savings_goals.php" method="POST">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Goal Name</label>
                        <input type="text" name="goal_name" required placeholder="e.g. New House" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Target Amount (UGX)</label>
                        <input type="number" step="1" name="target_amount" required class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Target Date (Optional)</label>
                        <input type="date" name="deadline" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                    </div>
                    <button type="submit" name="add_goal" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition duration-200">
                        Add Goal
                    </button>
                </form>
            </div>
        </div>

        <!-- Goals List -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Active Goals</h2>
                <div class="space-y-6">
                    <?php if (empty($goals)): ?>
                        <p class="text-center text-gray-500 py-4">You haven't set any savings goals yet.</p>
                    <?php else: ?>
                        <?php foreach ($goals as $goal):
                            $percentage = min(100, ($total_savings / $goal['target_amount']) * 100);
                        ?>
                            <div class="border rounded-lg p-4 dark:border-gray-700">
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="font-bold text-lg text-gray-800 dark:text-white"><?php echo htmlspecialchars($goal['goal_name']); ?></h3>
                                    <a href="?delete=<?php echo $goal['id']; ?>" class="text-red-500 hover:text-red-700 text-sm" onclick="return confirm('Delete this goal?');">Delete</a>
                                </div>
                                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <span>Target: <?php echo number_format($goal['target_amount'], 0); ?> UGX</span>
                                    <?php if ($goal['deadline']): ?>
                                        <span>Deadline: <?php echo date('M j, Y', strtotime($goal['deadline'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-4 dark:bg-gray-700 overflow-hidden">
                                    <div class="bg-green-500 h-4 rounded-full transition-all duration-500" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                                <p class="text-xs text-right mt-1 text-gray-500"><?php echo round($percentage); ?>% Complete</p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
