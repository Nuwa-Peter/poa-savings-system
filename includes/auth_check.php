<?php
function check_permissions(array $allowed_roles) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

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
