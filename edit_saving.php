<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3]); // Root, Chairman, Secretary

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$saving_id = $_GET['id'] ?? null;
$saving = null;
$error = '';

if ($saving_id) {
    try {
        $stmt = $pdo->prepare("SELECT s.*, u.first_name, u.surname FROM savings s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
        $stmt->execute([$saving_id]);
        $saving = $stmt->fetch();
        if (!$saving) {
            $error = "Saving record not found.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
} else {
    $error = "No saving ID specified.";
}
?>

<div class="container mx-auto mt-10">
    <h2 class="text-2xl font-bold mb-5">Rectify Saving Record</h2>

    <?php if ($error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Error!</span> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php elseif ($saving): ?>
        <form action="process_edit_saving.php" method="POST" class="bg-white p-6 rounded-lg shadow-md">
            <input type="hidden" name="saving_id" value="<?php echo htmlspecialchars($saving['id']); ?>">
            <input type="hidden" name="old_amount" value="<?php echo htmlspecialchars($saving['amount']); ?>">

            <div class="mb-4">
                <p><strong>Member:</strong> <?php echo htmlspecialchars($saving['first_name'] . ' ' . $saving['surname']); ?></p>
                <p><strong>Original Amount:</strong> <?php echo htmlspecialchars(number_format($saving['amount'], 0)); ?> UGX</p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($saving['created_at']))); ?></p>
            </div>

            <div class="mb-4">
                <label for="new_amount" class="block text-gray-700 text-sm font-bold mb-2">New Amount: <span class="text-red-500">*</span></label>
                <input type="number" step="1" name="new_amount" id="new_amount" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?php echo htmlspecialchars((int)$saving['amount']); ?>">
            </div>

            <div class="mb-4">
                <label for="reason" class="block text-gray-700 text-sm font-bold mb-2">Reason for Change: <span class="text-red-500">*</span></label>
                <textarea name="reason" id="reason" rows="4" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
            </div>

            <div class="flex items-center justify-between">
                <button type="submit" class="bg-orange-500 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update Saving
                </button>
                <a href="dashboard.php" class="text-gray-600 hover:text-gray-800">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php
require_once 'templates/footer.php';
?>
