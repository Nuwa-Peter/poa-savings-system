<?php
// includes/notifications.php

function check_payment_status($pdo, $user_id) {
    try {
        // First, check the user's creation date. If they are a new user, they are not overdue.
        $user_stmt = $pdo->prepare("SELECT created_at FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user_creation_date = $user_stmt->fetchColumn();

        if ($user_creation_date && strtotime($user_creation_date) > strtotime('-7 days')) {
            return false; // User created within the last 7 days is not overdue.
        }

        // Check the sum of savings in the last 7 days
        $seven_days_ago_date = date('Y-m-d H:i:s', strtotime('-7 days'));
        $savings_stmt = $pdo->prepare(
            "SELECT SUM(amount) as total_savings FROM savings WHERE user_id = ? AND created_at >= ?"
        );
        $savings_stmt->execute([$user_id, $seven_days_ago_date]);
        $result = $savings_stmt->fetch();

        $total_savings = $result['total_savings'] ?? 0;

        // If total savings in the last 7 days is less than 10,000 UGX, the payment is overdue.
        if ($total_savings < 10000) {
            return true;
        }

    } catch (PDOException $e) {
        // Log error or handle it as needed in a real application
        error_log("Notification check failed: " . $e->getMessage());
        return false;
    }

    return false; // Payment is not overdue
}
