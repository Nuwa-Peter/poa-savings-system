<?php
function check_inactivity() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $timeout_duration = 15 * 60; // 15 minutes in seconds

    if (isset($_SESSION['last_activity'])) {
        $elapsed_time = time() - $_SESSION['last_activity'];
        if ($elapsed_time > $timeout_duration) {
            session_unset();
            session_destroy();
            header('Location: login.php?error=' . urlencode('Session expired due to inactivity.'));
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}

function check_permissions(array $allowed_roles) {
    check_inactivity();

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    // Check if user role is allowed
    $user_role = $_SESSION['role_id'];
    if (!in_array($user_role, $allowed_roles)) {
        // Redirect to a 'not authorized' page or the dashboard
        header('Location: dashboard.php?error=unauthorized');
        exit;
    }
}
