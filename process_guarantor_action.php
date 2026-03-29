<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';

// --- Basic Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: guarantor_requests.php');
    exit;
}

$guarantor_request_id = $_POST['guarantor_request_id'] ?? null;
$action = $_POST['action'] ?? null;
$guarantor_user_id = $_SESSION['user_id'];
$redirect_url = 'guarantor_requests.php';

if (!$guarantor_request_id || !in_array($action, ['approve', 'reject'])) {
    header('Location: ' . $redirect_url . '?error=' . urlencode('Invalid action or missing data.'));
    exit;
}

try {
    $pdo->beginTransaction();

    // Verify the request belongs to the logged-in guarantor
    $verify_stmt = $pdo->prepare("SELECT id FROM loan_guarantors WHERE id = ? AND guarantor_id = ? AND status = 'pending'");
    $verify_stmt->execute([$guarantor_request_id, $guarantor_user_id]);
    if ($verify_stmt->fetch() === false) {
        throw new Exception("Guarantor request not found or already processed.");
    }

    // 1. Update the guarantor status
    $update_stmt = $pdo->prepare("UPDATE loan_guarantors SET status = ?, responded_at = NOW() WHERE id = ?");
    $update_stmt->execute([$action, $guarantor_request_id]);

    // 2. Log the action
    $log_action = "User responded '" . $action . "' to guarantor request #" . $guarantor_request_id;
    $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    $log_stmt->execute([$guarantor_user_id, $log_action]);

    // 3. Notify the original borrower
    $loan_info_stmt = $pdo->prepare(
        "SELECT l.user_id, u.username as guarantor_name
         FROM loan_guarantors lg
         JOIN loans l ON lg.loan_id = l.id
         JOIN users u ON lg.guarantor_id = u.id
         WHERE lg.id = ?"
    );
    $loan_info_stmt->execute([$guarantor_request_id]);
    $loan_info = $loan_info_stmt->fetch();

    if ($loan_info) {
        $action_past_tense = ($action === 'approve') ? 'approved' : 'rejected';
        $notification_message = htmlspecialchars($loan_info['guarantor_name']) . " has " . $action_past_tense . " your request to be a loan guarantor.";
        $notify_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notify_stmt->execute([$loan_info['user_id'], $notification_message]);
    }

    $pdo->commit();

    $success_message = "You have successfully responded to the guarantor request.";
    header('Location: ' . $redirect_url . '?success=' . urlencode($success_message));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . $redirect_url . '?error=' . urlencode($e->getMessage()));
    exit;
}
