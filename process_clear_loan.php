<?php
require_once 'includes/auth_check.php';
// Only Root (1) and Chairman (2) can permanently remove records
check_permissions([1, 2]);

require_once 'config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id = $_POST['loan_id'] ?? null;
    $admin_id = $_SESSION['user_id'];

    if (!$loan_id) {
        header('Location: manage_requests.php?error=Missing loan ID.');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Get borrower info for logging
        $stmt = $pdo->prepare("SELECT u.username, l.amount FROM loans l JOIN users u ON l.user_id = u.id WHERE l.id = ?");
        $stmt->execute([$loan_id]);
        $loan = $stmt->fetch();

        if (!$loan) {
            throw new Exception("Loan record not found.");
        }

        // 2. Delete the loan record (and dependent tables will be cleaned if foreign keys are CASCADE)
        // Manual cleanup for safety
        $pdo->prepare("DELETE FROM loan_guarantors WHERE loan_id = ?")->execute([$loan_id]);
        $pdo->prepare("DELETE FROM loan_collateral WHERE loan_id = ?")->execute([$loan_id]);
        $pdo->prepare("DELETE FROM loans WHERE id = ?")->execute([$loan_id]);

        // 3. Log the action
        $log_action = "Permanently removed loan record #{$loan_id} for user '{$loan['username']}' (Amount: " . number_format($loan['amount'], 0) . " UGX)";
        $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        $log_stmt->execute([$admin_id, $log_action]);

        $pdo->commit();
        header('Location: manage_requests.php?success=Loan record cleared and removed permanently.');
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Location: manage_requests.php?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: manage_requests.php');
    exit;
}
