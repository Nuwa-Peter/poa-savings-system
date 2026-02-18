<?php
require_once 'includes/auth_check.php';
// Only Root (1), Chairman (2), and Secretary (3) can process these actions.
check_permissions([1, 2, 3]);

require_once 'config/db_connect.php';

// --- Basic Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_requests.php');
    exit;
}

$request_id = $_POST['request_id'] ?? null;
$request_type = $_POST['request_type'] ?? null;
$action = $_POST['action'] ?? null;
$admin_user_id = $_SESSION['user_id'];

if (!$request_id || !$request_type || !in_array($action, ['approve', 'reject'])) {
    header('Location: manage_requests.php?error=' . urlencode('Invalid action or missing data.'));
    exit;
}

$table_name = '';
$redirect_url = 'manage_requests.php';
$log_action_template = "Admin action '%s' on %s request #%d";

try {
    $pdo->beginTransaction();

    if ($request_type === 'withdrawal') {
        $table_name = 'withdrawals';

        // On approval, double-check for sufficient funds.
        if ($action === 'approve') {
            // Get withdrawal details
            $stmt = $pdo->prepare("SELECT user_id, amount FROM withdrawals WHERE id = ? AND status = 'pending'");
            $stmt->execute([$request_id]);
            $withdrawal = $stmt->fetch();

            if (!$withdrawal) {
                 throw new Exception("Withdrawal request not found or already processed.");
            }

            // Calculate current available balance
            $savings_stmt = $pdo->prepare("SELECT SUM(amount) FROM savings WHERE user_id = ?");
            $savings_stmt->execute([$withdrawal['user_id']]);
            $total_savings = $savings_stmt->fetchColumn() ?: 0;

            $approved_withdrawals_stmt = $pdo->prepare("SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND status = 'approved'");
            $approved_withdrawals_stmt->execute([$withdrawal['user_id']]);
            $total_approved_withdrawals = $approved_withdrawals_stmt->fetchColumn() ?: 0;

            if ($withdrawal['amount'] > ($total_savings - $total_approved_withdrawals)) {
                throw new Exception("Approval failed: User has insufficient funds.");
            }
        }

        $update_stmt = $pdo->prepare(
            "UPDATE withdrawals SET status = ?, processed_at = NOW(), processed_by_user_id = ? WHERE id = ? AND status = 'pending'"
        );

    } elseif ($request_type === 'loan') {
        $table_name = 'loans';

        if ($action === 'approve') {
            $approved_amount = $_POST['approved_amount'] ?? 0;
            // Strip commas from masked input
            $approved_amount = str_replace(',', '', $approved_amount);
            if (!is_numeric($approved_amount) || $approved_amount <= 0) {
                throw new Exception("Invalid approved amount specified.");
            }

            // Fetch current approval status
            $loan_status_stmt = $pdo->prepare("SELECT secretary_approval, chairman_approval FROM loans WHERE id = ?");
            $loan_status_stmt->execute([$request_id]);
            $loan_approvals = $loan_status_stmt->fetch();

            $role_id = $_SESSION['role_id'];
            $is_final_approval = false;

            if ($role_id == 3) { // Secretary
                $update_stmt = $pdo->prepare(
                    "UPDATE loans SET secretary_approval = 1, secretary_id = ? WHERE id = ? AND status = 'pending'"
                );
                $update_stmt->execute([$admin_user_id, $request_id]);
                $success_message = "Secretary approval recorded. Pending Chairman approval.";
            } elseif ($role_id == 2 || $role_id == 1) { // Chairman or Root
                // Chairman can approve if Secretary has approved, or if they choose to bypass (Root)
                // For now, let's require Secretary approval unless it's Root
                if (!$loan_approvals['secretary_approval'] && $role_id != 1) {
                    throw new Exception("Chairman approval requires prior Secretary approval.");
                }

                $is_final_approval = true;
                $due_date = date('Y-m-d', strtotime('+1 month'));
                $update_stmt = $pdo->prepare(
                    "UPDATE loans SET amount = ?, balance = ?, status = 'approved', chairman_approval = 1, chairman_id = ?, approved_at = NOW(), approved_by_user_id = ?, due_date = ?, last_interest_applied_at = NOW() WHERE id = ? AND status = 'pending'"
                );
                $update_stmt->execute([$approved_amount, $approved_amount, $admin_user_id, $admin_user_id, $due_date, $request_id]);
                $success_message = "Loan fully approved.";
            } else {
                throw new Exception("Unauthorized role for loan approval.");
            }

            // If it's not the final approval, we don't want the common notification logic to trigger yet or we want it to be specific
            if (!$is_final_approval) {
                 $pdo->commit();
                 header('Location: ' . $redirect_url . '?success=' . urlencode($success_message));
                 exit;
            }

        } else {
            // Standard rejection
            $update_stmt = $pdo->prepare(
                "UPDATE loans SET status = ?, approved_at = NOW(), approved_by_user_id = ? WHERE id = ? AND status = 'pending'"
            );
        }

    } else {
        throw new Exception("Invalid request type.");
    }

    // Execute the common update for withdrawals or the rejection for loans
    if ($request_type === 'withdrawal' || ($request_type === 'loan' && $action === 'reject')) {
        $update_stmt->execute([$action, $admin_user_id, $request_id]);
    }

    $rows_affected = $update_stmt->rowCount();
    if ($rows_affected === 0) {
        throw new Exception("Request may have already been processed by another admin.");
    }

    // --- Create a notification for the user ---
    $requester_user_id = null;
    $request_amount = null;

    $details_stmt = $pdo->prepare("SELECT user_id, amount FROM {$table_name} WHERE id = ?");
    $details_stmt->execute([$request_id]);
    $request_details = $details_stmt->fetch();

    if ($request_details) {
        $requester_user_id = $request_details['user_id'];
        $request_amount = $request_details['amount'];

        $action_past_tense = ($action === 'approve') ? 'approved' : 'rejected';
        $notification_message = "Your {$request_type} request for " . number_format($request_amount, 0) . " UGX has been " . $action_past_tense . ".";

        $notify_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notify_stmt->execute([$requester_user_id, $notification_message]);
    }

    // --- Log the administrative action ---
    $log_action = sprintf($log_action_template, $action, $request_type, $request_id);
    $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    $log_stmt->execute([$admin_user_id, $log_action]);

    $pdo->commit();
    $success_message = "Successfully processed the " . $request_type . " request.";
    header('Location: ' . $redirect_url . '?success=' . urlencode($success_message));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . $redirect_url . '?error=' . urlencode($e->getMessage()));
    exit;
}
