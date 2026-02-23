<?php
require_once 'includes/auth_check.php';
// Only Root and Chairman can manage users
check_permissions([1, 2]);

require_once 'config/app_config.php';
require_once 'templates/header.php';
require_once 'config/db_connect.php';

$db_error = '';
$delete_success = '';
$delete_error = '';

// Handle User Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id_to_delete = $_POST['user_id'];
    $delete_reason = trim($_POST['delete_reason']);
    $current_user_id = $_SESSION['user_id'];

    if (empty($delete_reason)) {
        $delete_error = "A reason for deletion is required.";
    } elseif ($user_id_to_delete == $current_user_id) {
        $delete_error = "You cannot delete your own account.";
    } elseif ($user_id_to_delete == 1) { // Prevent root user deletion
        $delete_error = "The root user account cannot be deleted.";
    } else {
        try {
            // Get username for logging before deleting
            $user_stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $user_stmt->execute([$user_id_to_delete]);
            $username_to_delete = $user_stmt->fetchColumn();

            $pdo->beginTransaction();

            $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $delete_stmt->execute([$user_id_to_delete]);

            // Log the action
            require_once 'includes/logging.php';
            $log_message = "Deleted user: $username_to_delete (ID: $user_id_to_delete). Reason: $delete_reason";
            log_action($pdo, $current_user_id, $log_message);

            $pdo->commit();
            $delete_success = "User has been deleted successfully.";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $delete_error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch all users to display, excluding the root user
try {
    $stmt = $pdo->query(
        "SELECT id, username, account_no, email, role_id
         FROM users
         WHERE account_no != 'POA00000'
         ORDER BY username ASC"
    );
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
    $users = [];
}

require_once 'includes/user_functions.php';
?>

<div class="container mx-auto">
    <h2 class="text-3xl font-bold mb-6 text-gray-800">Manage Users</h2>

    <?php if ($db_error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert"><?php echo htmlspecialchars($db_error); ?></div>
    <?php endif; ?>
    <?php if ($delete_success): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert"><?php echo htmlspecialchars($delete_success); ?></div>
    <?php endif; ?>
    <?php if ($delete_error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert"><?php echo htmlspecialchars($delete_error); ?></div>
    <?php endif; ?>

    <!-- User List Table -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Username</th>
                        <th class="py-3 px-6 text-left">Email</th>
                        <th class="py-3 px-6 text-center">Role</th>
                        <th class="py-3 px-6 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm font-light">
                    <?php foreach ($users as $user): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                            <td class="py-3 px-6 text-left">
                                <p class="font-semibold"><?php echo htmlspecialchars($user['username']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user['account_no']); ?></p>
                            </td>
                            <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="py-3 px-6 text-center"><?php echo getRoleName($user['role_id']); ?></td>
                            <td class="py-3 px-6 text-center">
                                <form action="manage_users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');" class="flex items-center justify-center space-x-2">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="text" name="delete_reason" placeholder="Reason for deletion" required class="border px-2 py-1 text-sm rounded-md">
                                    <button type="submit" name="delete_user" class="text-red-600 hover:text-red-900 font-semibold">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
