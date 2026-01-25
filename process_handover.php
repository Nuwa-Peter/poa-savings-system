<?php
require_once 'includes/auth_check.php';
check_permissions([2]); // Only Chairman can access this page

require_once 'config/db_connect.php';
require_once 'includes/logging.php';

$outgoing_chairman_id = $_SESSION['user_id'];
$successor_id = $_POST['successor_id'] ?? null;

if (!$successor_id) {
    header('Location: handover.php?error=No successor selected.');
    exit;
}

// Ensure the chairman isn't trying to hand over to themselves
if ($outgoing_chairman_id == $successor_id) {
    header('Location: handover.php?error=You cannot hand over the role to yourself.');
    exit;
}

try {
    $pdo->beginTransaction();

    // --- Step 1: Re-assign all financial records from the outgoing chairman to the successor ---
    $update_savings_stmt1 = $pdo->prepare("UPDATE savings SET user_id = ? WHERE user_id = ?");
    $update_savings_stmt1->execute([$successor_id, $outgoing_chairman_id]);

    $update_loans_stmt1 = $pdo->prepare("UPDATE loans SET user_id = ? WHERE user_id = ?");
    $update_loans_stmt1->execute([$successor_id, $outgoing_chairman_id]);

    // --- Step 2: Re-assign all financial records from the successor to the outgoing chairman ---
    // Note: We need a temporary, non-conflicting user_id to avoid unique constraint violations
    $temp_user_id = -1; // A temporary ID that won't exist in the users table
    $update_savings_temp = $pdo->prepare("UPDATE savings SET user_id = ? WHERE user_id = ?");
    $update_savings_temp->execute([$temp_user_id, $successor_id]);

    $update_loans_temp = $pdo->prepare("UPDATE loans SET user_id = ? WHERE user_id = ?");
    $update_loans_temp->execute([$temp_user_id, $successor_id]);

    $update_savings_stmt2 = $pdo->prepare("UPDATE savings SET user_id = ? WHERE user_id = ?");
    $update_savings_stmt2->execute([$outgoing_chairman_id, $temp_user_id]);

    $update_loans_stmt2 = $pdo->prepare("UPDATE loans SET user_id = ? WHERE user_id = ?");
    $update_loans_stmt2->execute([$outgoing_chairman_id, $temp_user_id]);


    // --- Step 3: Swap User Roles ---
    $demote_chairman_stmt = $pdo->prepare("UPDATE users SET role_id = 5 WHERE id = ?");
    $demote_chairman_stmt->execute([$outgoing_chairman_id]);

    $promote_successor_stmt = $pdo->prepare("UPDATE users SET role_id = 2 WHERE id = ?");
    $promote_successor_stmt->execute([$successor_id]);

    // --- Step 4: Log the Action ---
    $successor_info_stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $successor_info_stmt->execute([$successor_id]);
    $successor_username = $successor_info_stmt->fetchColumn();

    log_action($pdo, $outgoing_chairman_id, "Handed over Chairman role to {$successor_username} (ID: {$successor_id}). Financial records were successfully swapped.");

    $pdo->commit();

    // --- Step 5: Force Logout to Refresh Session ---
    session_destroy();
    header('Location: login.php?success=Handover complete. Please log in again.');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: handover.php?error=An error occurred during the handover process: ' . urlencode($e->getMessage()));
    exit;
}
?>
