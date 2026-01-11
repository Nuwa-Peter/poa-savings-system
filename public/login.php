<?php
session_start();
require_once '../config/db_connect.php';
require_once '../src/User.php';

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $errorMessage = 'Please enter both your login identifier and password.';
    } else {
        $user = new User($pdo);
        $foundUser = $user->findByLoginIdentifier($identifier);

        if ($foundUser && $foundUser->verifyPassword($password)) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            // Store user data in the session
            $_SESSION['user_id'] = $foundUser->id;
            $_SESSION['username'] = $foundUser->username;
            $_SESSION['role_id'] = $foundUser->role_id;

            // Redirect to the dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $errorMessage = 'Invalid login credentials. Please try again.';
        }
    }
}

$pageTitle = 'Login - POA Savings';
ob_start();
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4>Login</h4>
                </div>
                <div class="card-body">
                    <?php if ($errorMessage): ?>
                        <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>
                    <form action="login.php" method="post">
                        <div class="mb-3">
                            <label for="login" class="form-label">Username, Email, or Phone</label>
                            <input type="text" class="form-control" id="login" name="login" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Login</button>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../templates/layout.php';
?>
