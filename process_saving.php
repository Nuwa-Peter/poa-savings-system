<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'config/db_connect.php';
require_once 'includes/logging.php'; // Assuming logging is ready

// Only Secretary (role_id 3), Chairman (role_id 2), and Root (role_id 1) can process savings
check_permissions([1, 2, 3]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $raw_amount = $_POST['amount'] ?? 0;
    $amount = round(str_replace(',', '', $raw_amount ?: 0));
    $verifier_id = $_SESSION['user_id'];

    // --- Database Insertion ---
    try {
        $pdo->beginTransaction();

        // Insert into savings table
        $stmt = $pdo->prepare('INSERT INTO savings (user_id, amount, verified_by_user_id) VALUES (?, ?, ?)');
        $stmt->execute([$user_id, $amount, $verifier_id]);

        // Insert into notifications table
        $message = "Your account has been credited with UGX " . number_format($amount, 0);
        $notify_stmt = $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
        $notify_stmt->execute([$user_id, $message]);

        // Log the action
        log_action($pdo, $verifier_id, "Added a saving of $amount for user ID $user_id.");

        $pdo->commit();

        header('Location: add_saving.php?success=1');
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Location: add_saving.php?error=Database error: ' . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Not a POST request
    header('Location: add_saving.php');
    exit;
}
