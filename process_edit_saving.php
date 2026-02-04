<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3]); // Root, Chairman, Secretary

require_once 'config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $saving_id = $_POST['saving_id'] ?? null;
    $old_amount = str_replace(',', '', $_POST['old_amount'] ?? 0);
    $new_amount = str_replace(',', '', $_POST['new_amount'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $admin_id = $_SESSION['user_id'];

    // Validation
    if (empty($saving_id) || $new_amount === '' || $reason === '') {
        header("Location: edit_saving.php?id={$saving_id}&error=Missing required fields.");
        exit;
    }

    if (!is_numeric($new_amount) || (float)$new_amount < 0) {
        header("Location: edit_saving.php?id={$saving_id}&error=Invalid amount.");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Update the saving record
        $stmt = $pdo->prepare("UPDATE savings SET amount = ? WHERE id = ?");
        $stmt->execute([$new_amount, $saving_id]);

        // 2. Log the change in the new savings_log table
        $stmt = $pdo->prepare(
            "INSERT INTO savings_log (saving_id, admin_id, old_amount, new_amount, reason) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$saving_id, $admin_id, $old_amount, $new_amount, $reason]);

        // 3. Log the generic action
        $log_action = "Rectified saving record #{$saving_id}. Changed amount from {$old_amount} to {$new_amount}. Reason: {$reason}";
        $stmt = $pdo->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        $stmt->execute([$admin_id, $log_action]);

        $pdo->commit();

        header("Location: admin_dashboard.php?success=Saving updated successfully.");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Redirect with a generic error; specific errors could be logged for the admin
        header("Location: edit_saving.php?id={$saving_id}&error=" . urlencode("Error: " . $e->getMessage()));
        exit;
    }
} else {
    // Redirect if accessed directly
    header("Location: dashboard.php");
    exit;
}
?>
