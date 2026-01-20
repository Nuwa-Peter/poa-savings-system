<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';

// --- Basic Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: repay_loan.php');
    exit;
}

$loan_id = $_POST['loan_id'] ?? null;
$amount = $_POST['amount'] ?? 0;
$current_balance = $_POST['current_balance'] ?? 0;
$user_id = $_SESSION['user_id'];
$redirect_url = 'repay_loan.php';

// Validate amount
if (!is_numeric($amount) || $amount <= 0) {
    header('Location: ' . $redirect_url . '?error=' . urlencode('Invalid payment amount.'));
    exit;
}

if ($amount > $current_balance) {
    header('Location: ' . $redirect_url . '?error=' . urlencode('Payment cannot exceed the outstanding balance.'));
    exit;
}

try {
    $pdo->beginTransaction();

    // Verify that the loan belongs to the user and is active
    $verify_stmt = $pdo->prepare("SELECT id FROM loans WHERE id = ? AND user_id = ? AND status = 'approved'");
    $verify_stmt->execute([$loan_id, $user_id]);
    if ($verify_stmt->fetch() === false) {
        throw new Exception("Active loan not found for your account.");
    }

    // 1. Update the loan balance
    $new_balance = $current_balance - $amount;
    $update_loan_stmt = $pdo->prepare("UPDATE loans SET balance = ? WHERE id = ?");
    $update_loan_stmt->execute([$new_balance, $loan_id]);

    // 2. Record the payment
    $insert_payment_stmt = $pdo->prepare("INSERT INTO loan_payments (loan_id, amount) VALUES (?, ?)");
    $insert_payment_stmt->execute([$loan_id, $amount]);

    // 3. If balance is zero or less, mark the loan as 'paid'
    if ($new_balance <= 0) {
        $mark_paid_stmt = $pdo->prepare("UPDATE loans SET status = 'paid' WHERE id = ?");
        $mark_paid_stmt->execute([$loan_id]);
    }

    // 4. Log the repayment action
    $log_action = "User made a loan repayment of " . number_format($amount, 2) . " UGX for loan #" . $loan_id;
    $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    $log_stmt->execute([$user_id, $log_action]);

    // 5. Create a notification for the user
    $notification_message = "Thank you for your payment of " . number_format($amount, 2) . " UGX. Your new loan balance is " . number_format($new_balance, 2) . " UGX.";
    if ($new_balance <= 0) {
        $notification_message = "Congratulations! You have fully paid off your loan.";
    }
    $notify_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $notify_stmt->execute([$user_id, $notification_message]);

    $pdo->commit();

    $success_message = "Your payment of " . number_format($amount, 2) . " UGX was successful.";
    header('Location: ' . $redirect_url . '?success=' . urlencode($success_message));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . $redirect_url . '?error=' . urlencode($e->getMessage()));
    exit;
}
