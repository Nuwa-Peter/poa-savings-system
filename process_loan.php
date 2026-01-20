<?php
require_once 'includes/auth_check.php';
// All authenticated users can request a loan
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: request_loan.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$amount = $_POST['amount'] ?? 0;

// --- Basic Validation ---
if (!is_numeric($amount) || $amount <= 0) {
    header('Location: request_loan.php?error=' . urlencode('Invalid loan amount specified.'));
    exit;
}

try {
    // --- Eligibility Check 1: Savings Frequency ---
    // This query checks if the user has saved at least 3 times a week for the last 4 weeks.
    // It does this by counting how many of the past 4 weekly groups have 3 or more savings.
    $four_weeks_ago = date('Y-m-d H:i:s', strtotime('-4 weeks'));

    $freq_stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM (
            SELECT COUNT(id)
            FROM savings
            WHERE user_id = ? AND created_at >= ?
            GROUP BY YEAR(created_at), WEEK(created_at, 1)
            HAVING COUNT(id) >= 3
        ) as qualifying_weeks_count"
    );
    $freq_stmt->execute([$user_id, $four_weeks_ago]);
    $qualifying_weeks = $freq_stmt->fetchColumn();

    if ($qualifying_weeks < 4) {
        header('Location: request_loan.php?error=' . urlencode('Eligibility failed: You must save at least 3 times per week for the last 4 consecutive weeks.'));
        exit;
    }

    // --- Eligibility Check 2: Loan Amount Limit ---
    // The requested amount cannot be more than half of the user's total savings.
    $savings_stmt = $pdo->prepare("SELECT SUM(amount) as total FROM savings WHERE user_id = ?");
    $savings_stmt->execute([$user_id]);
    $total_savings = $savings_stmt->fetchColumn() ?: 0;

    $max_loan_amount = $total_savings / 2;

    if ($amount > $max_loan_amount) {
        $error_msg = 'Eligibility failed: Requested amount exceeds 50% of your total savings. Your maximum loan amount is ' . number_format($max_loan_amount, 2);
        header('Location: request_loan.php?error=' . urlencode($error_msg));
        exit;
    }

    // --- Process the Loan Request ---
    $pdo->beginTransaction();

    // Insert the loan request with a 'pending' status.
    $insert_stmt = $pdo->prepare(
        "INSERT INTO loans (user_id, amount, balance, status, requested_at) VALUES (?, ?, ?, 'pending', NOW())"
    );
    $insert_stmt->execute([$user_id, $amount, $amount]);

    // Log the action for audit purposes.
    $log_action = "User requested a loan of " . number_format($amount, 2);
    $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    $log_stmt->execute([$user_id, $log_action]);

    $pdo->commit();

    // Redirect on success
    header('Location: request_loan.php?success=1');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Log the error for the admin, show a generic message to the user.
    error_log("Loan Request Error: " . $e->getMessage());
    header('Location: request_loan.php?error=' . urlencode('A database error occurred. Please try again later.'));
    exit;
}
