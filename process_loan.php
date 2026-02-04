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
$raw_amount = $_POST['amount'] ?? 0;
$amount = round(str_replace(',', '', $raw_amount ?: 0));
$guarantor_id = $_POST['guarantor_id'] ?? null;

// --- Basic Validation ---
if (!is_numeric($amount) || $amount <= 0) {
    header('Location: request_loan.php?error=' . urlencode('Invalid loan amount specified.'));
    exit;
}

if (!$guarantor_id || !is_numeric($guarantor_id) || $guarantor_id == $user_id) {
    header('Location: request_loan.php?error=' . urlencode('Invalid guarantor selected.'));
    exit;
}

try {
    // --- Eligibility Check 1: Savings Frequency ---
    // You must have saved at least three weeks out of the four weeks.
    $four_weeks_ago = date('Y-m-d H:i:s', strtotime('-4 weeks'));

    $freq_stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT WEEK(created_at, 1))
         FROM savings
         WHERE user_id = ? AND created_at >= ?"
    );
    $freq_stmt->execute([$user_id, $four_weeks_ago]);
    $qualifying_weeks = $freq_stmt->fetchColumn();

    if ($qualifying_weeks < 3) {
        header('Location: request_loan.php?error=' . urlencode('Loan Eligibility Criteria: You must have saved at least three weeks out of the four weeks.'));
        exit;
    }

    // --- Eligibility Check 2: Loan Amount Limit ---
    // The requested amount cannot be more than half of the user's total savings.
    $savings_stmt = $pdo->prepare("SELECT SUM(amount) as total FROM savings WHERE user_id = ?");
    $savings_stmt->execute([$user_id]);
    $total_savings = $savings_stmt->fetchColumn() ?: 0;

    $max_loan_amount = $total_savings / 2;

    if ($amount > $max_loan_amount) {
        $error_msg = 'Eligibility failed: Requested amount exceeds 50% of your total savings. Your maximum loan amount is ' . number_format($max_loan_amount, 0);
        header('Location: request_loan.php?error=' . urlencode($error_msg));
        exit;
    }

    // --- Process the Loan Request ---
    $pdo->beginTransaction();

    // 1. Insert the loan request with a 'pending' status.
    $insert_loan_stmt = $pdo->prepare(
        "INSERT INTO loans (user_id, amount, balance, status, requested_at) VALUES (?, ?, ?, 'pending', NOW())"
    );
    $insert_loan_stmt->execute([$user_id, $amount, $amount]);
    $loan_id = $pdo->lastInsertId();

    // 2. Create the pending guarantor request.
    $insert_guarantor_stmt = $pdo->prepare(
        "INSERT INTO loan_guarantors (loan_id, guarantor_id, status) VALUES (?, ?, 'pending')"
    );
    $insert_guarantor_stmt->execute([$loan_id, $guarantor_id]);

    // 3. Create a notification for the guarantor.
    $borrower_stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $borrower_stmt->execute([$user_id]);
    $borrower_username = $borrower_stmt->fetchColumn();

    $notification_message = htmlspecialchars($borrower_username) . " has requested you to be a guarantor for a loan of " . number_format($amount, 0) . " UGX. Please review this request.";
    $notify_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $notify_stmt->execute([$guarantor_id, $notification_message]);

    // 4. Log the action for audit purposes.
    $log_action = "User requested a loan of " . number_format($amount, 0) . " with guarantor ID " . $guarantor_id;
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
