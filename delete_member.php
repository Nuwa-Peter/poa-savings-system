<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2]); // Only Root and Chairman can access

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$member_id = $_GET['id'] ?? null;
$member = null;
$error = '';

if ($member_id) {
    try {
        // Exclude root and chairman
        $stmt = $pdo->prepare("SELECT id, first_name, surname FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();
        if (!$member) {
            $error = "Active member not found.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
} else {
    $error = "No member ID specified.";
}
?>

<div class="container mx-auto mt-10">
    <h2 class="text-2xl font-bold mb-5">Delete Member</h2>

    <?php if ($error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php elseif ($member): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Warning!</p>
            <p>You are about to delete the member: <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['surname']); ?></strong>. This will revoke their access to the system, but their financial records will be preserved for auditing purposes.</p>
        </div>

        <form action="process_delete_member.php" method="POST" class="bg-white p-6 rounded-lg shadow-md">
            <input type="hidden" name="member_id" value="<?php echo htmlspecialchars($member['id']); ?>">

            <div class="mb-4">
                <label for="reason" class="block text-gray-700 text-sm font-bold mb-2">Reason for Deletion: <span class="text-red-500">*</span></label>
                <textarea name="reason" id="reason" rows="4" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
            </div>

            <div class="flex items-center justify-between">
                <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" onclick="return confirm('Are you sure you want to delete this member?');">
                    Confirm Deletion
                </button>
                <a href="member_directory.php" class="text-gray-600 hover:text-gray-800">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php
require_once 'templates/footer.php';
?>
