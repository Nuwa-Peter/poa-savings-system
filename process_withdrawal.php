<?php
require_once 'includes/auth_check.php';
// All authenticated users can process their own withdrawal request
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect if accessed directly
    header('Location: withdraw.php');
    exit;
}

// Get form data
$raw_amount = $_POST['amount'] ?? 0;
$amount = round(str_replace(',', '', $raw_amount ?: 0));
$user_id = $_SESSION['user_id'];

// --- Validation ---
// 1. Validate amount is a positive number
if (!is_numeric($amount) || $amount <= 0) {
    header('Location: withdraw.php?error=' . urlencode('Invalid amount entered.'));
    exit;
}

// 2. Check if user has sufficient funds
try {
    // Calculate total savings
    $savings_stmt = $pdo->prepare("SELECT SUM(amount) as total_savings FROM savings WHERE user_id = ?");
    $savings_stmt->execute([$user_id]);
    $total_savings = $savings_stmt->fetchColumn() ?: 0;

    // Calculate total withdrawals (approved or pending) to prevent over-requesting
    $withdrawal_stmt = $pdo->prepare("SELECT SUM(amount) as total_withdrawals FROM withdrawals WHERE user_id = ? AND status IN ('pending', 'approved')");
    $withdrawal_stmt->execute([$user_id]);
    $total_withdrawals = $withdrawal_stmt->fetchColumn() ?: 0;

    // Calculate total active fixed deposits (locked funds)
    $fd_stmt = $pdo->prepare("SELECT SUM(amount) as total_fd FROM fixed_deposits WHERE user_id = ? AND status = 'active'");
    $fd_stmt->execute([$user_id]);
    $total_fd = $fd_stmt->fetchColumn() ?: 0;

    $available_balance = $total_savings - $total_withdrawals - $total_fd;

    if ($amount > $available_balance) {
        header('Location: withdraw.php?error=' . urlencode('Insufficient funds. Your available balance is ' . number_format($available_balance, 0)));
        exit;
    }

    // --- Insert into database ---
    $pdo->beginTransaction();

    $insert_stmt = $pdo->prepare(
        "INSERT INTO withdrawals (user_id, amount, status, requested_at) VALUES (?, ?, 'pending', NOW())"
    );

    $insert_stmt->execute([$user_id, $amount]);

    // Log the action
    $log_action = "User requested a withdrawal of " . number_format($amount, 0);
    $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    $log_stmt->execute([$user_id, $log_action]);

    $pdo->commit();

    // Redirect with success message
    header('Location: withdraw.php?success=1');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // In a real app, you would log this error.
    error_log("Withdrawal Error: " . $e->getMessage());
    header('Location: withdraw.php?error=' . urlencode('A database error occurred. Please try again.'));
    exit;
}
