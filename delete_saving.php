<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3]); // Root, Chairman, Secretary

require_once 'config/db_connect.php';

$saving_id = $_GET['id'] ?? null;
$admin_id = $_SESSION['user_id'];
$error = '';

if (!$saving_id) {
    header("Location: admin_dashboard.php?error=No saving ID specified.");
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Get saving details before deleting for logging purposes
    $stmt = $pdo->prepare("SELECT user_id, amount FROM savings WHERE id = ?");
    $stmt->execute([$saving_id]);
    $saving = $stmt->fetch();

    if (!$saving) {
        throw new Exception("Saving record not found.");
    }

    // 2. Delete the saving record
    $delete_stmt = $pdo->prepare("DELETE FROM savings WHERE id = ?");
    $delete_stmt->execute([$saving_id]);

    // 3. Log the deletion action
    $log_action = "Administrator (ID: {$admin_id}) deleted saving record #{$saving_id} of amount {$saving['amount']} for user ID {$saving['user_id']}.";
    $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    $log_stmt->execute([$admin_id, $log_action]);

    $pdo->commit();

    header("Location: admin_dashboard.php?success=Saving transaction deleted successfully.");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    // Redirect with a generic error; specific errors could be logged for the admin
    header("Location: admin_dashboard.php?error=Database error: " . $e->getMessage());
    exit;
}
?>
