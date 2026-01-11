<?php
session_start();
require_once '../config/db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch user data from the database
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    // If user not found, destroy the session and redirect to login
    session_destroy();
    header('Location: login.php');
    exit;
}

$pageTitle = 'Dashboard - POA Savings';
ob_start();
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Dashboard</h1>
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Your Profile</h6>
                </div>
                <div class="card-body">
                    <p><strong>Account Number:</strong> <?php echo htmlspecialchars($user['account_no']); ?></p>
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Your Savings</h6>
                </div>
                <div class="card-body">
                    <p>TBD: Savings information will be displayed here.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<a href="logout.php">Logout</a>

<?php
$content = ob_get_clean();
include '../templates/layout.php';
?>
