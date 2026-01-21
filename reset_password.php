<?php
session_start();
require_once 'config/db_connect.php';

$error = '';
$message = '';
$token = $_GET['token'] ?? null;
$valid_token = false;

if (!$token) {
    $error = "No reset token provided. Please use the link provided by the administrator.";
} else {
    try {
        // Validate the token
        $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()');
        $stmt->execute([$token]);
        $reset_request = $stmt->fetch();

        if ($reset_request) {
            $valid_token = true;
            $user_id = $reset_request['user_id'];

            // Handle the form submission for the new password
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $password = $_POST['password'];
                $password_confirm = $_POST['password_confirm'];

                if (empty($password) || empty($password_confirm)) {
                    $error = "Please enter and confirm your new password.";
                } elseif ($password !== $password_confirm) {
                    $error = "The passwords do not match.";
                } elseif (strlen($password) < 8) {
                    $error = "Password must be at least 8 characters long.";
                } else {
                    // Hash the new password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Update the user's password
                    $update_stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $update_stmt->execute([$hashed_password, $user_id]);

                    // Invalidate the reset token by deleting it
                    $delete_stmt = $pdo->prepare('DELETE FROM password_resets WHERE token = ?');
                    $delete_stmt->execute([$token]);

                    $message = "Your password has been successfully reset. You can now log in with your new password.";
                    $valid_token = false; // Hide the form
                }
            }
        } else {
            $error = "Invalid or expired reset token. Please request a new one.";
        }
    } catch (PDOException $e) {
        error_log("Password Reset Error: " . $e->getMessage());
        $error = "An unexpected database error occurred.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - POA Savings</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center text-gray-800">Set a New Password</h2>

        <?php if ($message): ?>
            <div class="p-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                <span class="font-medium">Success!</span> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="p-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                <span class="font-medium">Error!</span> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($valid_token): ?>
        <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" class="space-y-6">
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                <input type="password" name="password" id="password" required
                       class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm appearance-none focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="password_confirm" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                <input type="password" name="password_confirm" id="password_confirm" required
                       class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm appearance-none focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <button type="submit"
                        class="w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Reset Password
                </button>
            </div>
        </form>
        <?php endif; ?>
        <div class="text-sm text-center">
            <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                &larr; Back to Login
            </a>
        </div>
    </div>
</body>
</html>
