<?php
require_once 'includes/auth_check.php';
// Only Root (1) and Chairman (2) can access this page
check_permissions([1, 2]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$message = '';
$error = '';
$new_password = '';

// Fetch all users to populate the dropdown
try {
    $users_stmt = $pdo->query("SELECT id, first_name, surname, email FROM users WHERE status = 'active' AND id != 1 AND role_id != 2 ORDER BY first_name ASC");
    $users = $users_stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
    $error = "Failed to load user list.";
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id_to_reset = $_POST['user_id'] ?? null;
    $admin_id = $_SESSION['user_id'];

    if (empty($user_id_to_reset)) {
        $error = "Please select a user to reset.";
    } else {
        try {
            // Generate a secure random password
            $new_password = bin2hex(random_bytes(8)); // 16 characters long
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $pdo->beginTransaction();

            // Update user's password
            $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->execute([$hashed_password, $user_id_to_reset]);

            // Log this administrative action
            $user_info_stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $user_info_stmt->execute([$user_id_to_reset]);
            $username_to_reset = $user_info_stmt->fetchColumn();

            $log_action = "Administrator (ID: {$admin_id}) reset the password for user '{$username_to_reset}' (ID: {$user_id_to_reset}).";
            $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
            $log_stmt->execute([$admin_id, $log_action]);

            $pdo->commit();

            $message = "Password for '{$username_to_reset}' has been successfully reset.";
            // The $new_password will be displayed below only if this message is set.

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Admin Password Reset Error: " . $e->getMessage());
            $error = "An unexpected database error occurred.";
        }
    }
}
?>

<div class="container mx-auto mt-10 p-4">
    <div class="max-w-2xl mx-auto bg-white dark:bg-gray-800 p-8 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6">Administrator Password Reset</h1>

        <p class="text-gray-600 dark:text-gray-400 mb-6">
            Select a user from the list to reset their password. A new, secure password will be generated. You must securely communicate this new password to the user. This action will be logged.
        </p>

        <?php if ($message): ?>
            <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-200 dark:text-green-800" role="alert">
                <span class="font-medium">Success!</span> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <?php if ($new_password): ?>
            <div class="p-4 mb-4 text-sm text-yellow-700 bg-yellow-100 rounded-lg dark:bg-yellow-200 dark:text-yellow-800" role="alert">
                <span class="font-medium">New Temporary Password:</span>
                <strong class="font-mono text-lg ml-2"><?php echo htmlspecialchars($new_password); ?></strong>
                <p class="mt-2">Please copy this password now and provide it to the user. It will not be shown again.</p>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert">
                <span class="font-medium">Error!</span> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin_reset_password.php" class="space-y-6">
            <div>
                <label for="user_id" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Select User to Reset:</label>
                <select name="user_id" id="user_id" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Select a User --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['surname']) . ' (' . htmlspecialchars($user['email']) . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" class="w-full flex items-center justify-center space-x-2 bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-150 ease-in-out">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span>Reset Password</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
