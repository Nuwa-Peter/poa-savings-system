<?php
// reset_password.php
require_once 'config/db_connect.php';
require_once 'templates/header.php';

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please enter and confirm your new password.";
    } elseif ($new_password !== $confirm_password) {
        $error = "The passwords do not match.";
    } else {
        try {
            // Find the reset request
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
            $stmt->execute([$token]);
            $reset_request = $stmt->fetch();

            if ($reset_request) {
                $email = $reset_request['email'];
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                // Update user's password
                $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $update_stmt->execute([$hashed_password, $email]);

                // Delete the used token
                $delete_stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                $delete_stmt->execute([$token]);

                $pdo->commit();
                $success = "Your password has been reset successfully. You can now <a href='login.php' class='font-bold text-indigo-600'>log in</a>.";
            } else {
                $error = "Invalid or expired token. Please request a new reset link.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-center text-gray-800">Choose a New Password</h2>
    <p class="text-center text-gray-600">Enter and confirm your new password below.</p>

    <?php if ($success): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): // Hide form on success ?>
    <form method="POST" action="reset_password.php" class="space-y-6">
    <?php endif; ?>
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <div>
            <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
            <input type="password" name="new_password" id="new_password" required
                   class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm appearance-none focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>

        <div>
            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
            <input type="password" name="confirm_password" id="confirm_password" required
                   class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm appearance-none focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>

        <div>
            <button type="submit"
                    class="w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Reset Password
            </button>
        </div>
    </form>
</div>

<?php
require_once 'templates/footer.php';
?>
