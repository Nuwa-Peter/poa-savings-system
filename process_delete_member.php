<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2]); // Only Root and Chairman can process

require_once 'config/db_connect.php';
require_once 'includes/logging.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_POST['member_id'] ?? null;
    $reason = trim($_POST['reason'] ?? '');
    $admin_id = $_SESSION['user_id'];

    if (empty($member_id) || empty($reason)) {
        header('Location: member_directory.php?error=Missing required fields.');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Get member's username for logging
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$member_id]);
        $username = $stmt->fetchColumn();

        if (!$username) {
            throw new Exception("Member not found.");
        }

        // 2. Soft delete the user by updating their status
        $update_stmt = $pdo->prepare("UPDATE users SET status = 'deleted' WHERE id = ?");
        $update_stmt->execute([$member_id]);

        // 3. Log the action with the reason
        $log_action = "Soft deleted user '{$username}' (ID: {$member_id}). Reason: {$reason}";
        log_action($pdo, $admin_id, $log_action);

        $pdo->commit();

        header('Location: member_directory.php?success=Member deleted successfully.');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        header('Location: member_directory.php?error=An error occurred: ' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: member_directory.php');
    exit;
}
?>
