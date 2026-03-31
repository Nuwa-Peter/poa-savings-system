<?php
// forgot_password.php
require_once 'config/db_connect.php';
require_once 'templates/header.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate a secure token
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Store the token in the database
                $insert_stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $insert_stmt->execute([$email, $token, $expires_at]);

                // Dynamically generate the base URL
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $host = $_SERVER['HTTP_HOST'];
                $base_url = $protocol . $host;

                // For testing: Display the reset link instead of emailing it
                $reset_link = $base_url . "/reset_password.php?token=" . $token;
                $message = "<strong>Security Warning:</strong> This link is displayed for testing purposes only. In a production environment, this link would be sent to your email address.<br><br>" .
                           "A password reset link has been generated. Please click the link below to reset your password. <br><a href='{$reset_link}' class='text-indigo-600'>{$reset_link}</a>";

            } else {
                // To prevent email enumeration, show a generic success message even if the email doesn't exist.
                $message = "If an account with that email exists, a password reset link has been sent.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-center text-gray-800">Reset Your Password</h2>
    <p class="text-center text-gray-600">Enter your email address and we will generate a link to reset your password.</p>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="forgot_password.php" class="space-y-6">
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
            <input type="email" name="email" id="email" required
                   class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm appearance-none focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>

        <div>
            <button type="submit"
                    class="w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Send Password Reset Link
            </button>
        </div>
    </form>
    <div class="text-sm text-center">
        <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500">
            Back to login
        </a>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
