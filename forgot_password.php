<?php
session_start();
require_once 'config/db_connect.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? null;

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if the user exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate a secure, URL-safe token
                $token = bin2hex(random_bytes(32));
                // Token expiry (e.g., 1 hour from now)
                $expiry = date('Y-m-d H:i:s', time() + 3600);

                // Store the token in the database
                $insert_stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)');
                $insert_stmt->execute([$user['id'], $token, $expiry]);

                // In a real application, you would email this link.
                // Here, we'll display a message for the user to contact an admin.
                // An admin would then retrieve this link for the user.
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;

                $message = "A password reset request has been generated. Please contact the Chairman or a system administrator and provide them with your email address to receive your secure reset link. For security reasons, the link will not be displayed here.";

            } else {
                 // To prevent user enumeration, show a generic success message even if the email doesn't exist.
                $message = "If an account with that email exists, a password reset request has been generated. Please contact an administrator.";
            }

        } catch (PDOException $e) {
            error_log("Password Reset Error: " . $e->getMessage());
            $error = 'An unexpected database error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - POA Savings</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center text-gray-800">Forgot Your Password?</h2>
        <p class="text-center text-gray-600">Enter your email address below, and we will generate a secure link for you to reset your password.</p>

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

        <?php if (!$message): // Hide form after successful submission ?>
        <form method="POST" action="forgot_password.php" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Your Email Address</label>
                <input type="email" name="email" id="email" required
                       class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm appearance-none focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div>
                <button type="submit"
                        class="w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Request Reset Link
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
