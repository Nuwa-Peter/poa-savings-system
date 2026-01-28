<?php
session_start();
require_once 'config/db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = $_POST['identifier'];
    $password = $_POST['password'];

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter both identifier and password.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :identifier OR email = :identifier OR phone = :identifier');
            $stmt->execute(['identifier' => $identifier]);
            $user = $stmt->fetch();

            // First, check if a user was found and if the stored hash is potentially valid
            if ($user && !empty($user['password'])) {
                // Now, verify the password against the hash
                if (password_verify($password, $user['password'])) {
                    // Password is correct, start session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role_id'] = $user['role_id'];

                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit;
                }
            }

            // If we reach here, either the user was not found, the hash was empty, or the password was incorrect.
            // In all cases, present a generic error to prevent user enumeration attacks.
            $error = 'Invalid credentials. Please try again.';

        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - POA Savings</title>
    <link rel="icon" href="assets/images/poa_light.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="assets/js/main.js" defer></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <div class="text-center">
            <img class="mx-auto h-20 w-auto" src="assets/images/poa_light.png" alt="POA Savings Logo">
            <h2 class="mt-6 text-2xl font-bold text-gray-800">POA Savings Management System</h2>
        </div>
        <h3 class="text-xl font-semibold text-center text-gray-700">Sign in to your account</h3>

        <?php if ($error): ?>
            <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                <span class="font-medium">Error!</span> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-6">
            <div>
                <label for="identifier" class="block text-sm font-medium text-gray-700">Username, Email, or Phone</label>
                <input type="text" name="identifier" id="identifier" required
                       class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm appearance-none focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" id="password" required
                       class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm appearance-none focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div class="flex items-center">
                <input id="show-password" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                <label for="show-password" class="ml-2 block text-sm text-gray-900">Show Password</label>
            </div>

            <div>
                <button type="submit"
                        class="w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Sign In
                </button>
            </div>
        </form>
        <div class="mt-4 text-center">
            <button type="button" id="login-biometric-btn" class="bg-gray-800 hover:bg-gray-900 text-white font-bold py-2 px-4 rounded w-full">
                Login with Fingerprint/Face
            </button>
        </div>
        <div class="text-sm text-center">
            <a href="forgot_password.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                Forgot your password?
            </a>
        </div>
    </div>
    <script>
        const showPasswordCheckbox = document.getElementById('show-password');
        const passwordInput = document.getElementById('password');

        showPasswordCheckbox.addEventListener('change', function() {
            passwordInput.type = this.checked ? 'text' : 'password';
        });

        document.getElementById('login-biometric-btn').addEventListener('click', async () => {
            try {
                const response = await fetch('webauthn_login_start.php');
                const getArgs = await response.json();

                if (getArgs.error) {
                    throw new Error(getArgs.error);
                }

                getArgs.challenge = bufferDecode(getArgs.challenge);
                getArgs.allowCredentials.forEach((cred) => {
                    cred.id = bufferDecode(cred.id);
                });

                const credential = await navigator.credentials.get({
                    publicKey: getArgs
                });

                const formData = new FormData();
                formData.append('id', credential.id);
                formData.append('clientDataJSON', bufferEncode(credential.response.clientDataJSON));
                formData.append('authenticatorData', bufferEncode(credential.response.authenticatorData));
                formData.append('signature', bufferEncode(credential.response.signature));
                formData.append('userHandle', bufferEncode(credential.response.userHandle));

                const finishResponse = await fetch('webauthn_login_finish.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await finishResponse.json();

                if (result.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    throw new Error(result.message);
                }

            } catch (err) {
                alert('Login failed: ' + err.message);
            }
        });
    </script>
</body>
</html>
