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

try {
    $pdo->beginTransaction();

    // --- Step 1: Archive Outgoing Chairman's Financial Data ---
    $pdo->exec("INSERT INTO temp_savings SELECT * FROM savings WHERE user_id = {$outgoing_chairman_id}");
    $pdo->exec("INSERT INTO temp_loans SELECT * FROM loans WHERE user_id = {$outgoing_chairman_id}");

    // --- Step 2: Delete Outgoing Chairman's Original Financial Data ---
    $pdo->exec("DELETE FROM savings WHERE user_id = {$outgoing_chairman_id}");
    $pdo->exec("DELETE FROM loans WHERE user_id = {$outgoing_chairman_id}");

    // --- Step 3: Re-assign Successor's Data to the Chairman's User ID ---
    $pdo->exec("UPDATE savings SET user_id = {$outgoing_chairman_id} WHERE user_id = {$successor_id}");
    $pdo->exec("UPDATE loans SET user_id = {$outgoing_chairman_id} WHERE user_id = {$successor_id}");

    // --- Step 4: Swap User Roles ---
    $pdo->exec("UPDATE users SET role_id = 5 WHERE id = {$outgoing_chairman_id}"); // Demote old chairman
    $pdo->exec("UPDATE users SET role_id = 2 WHERE id = {$successor_id}");     // Promote new chairman

    // --- Step 5: Restore Archived Data to the (Now Demoted) Old Chairman ---
    $pdo->exec("UPDATE temp_savings SET user_id = {$successor_id}");
    $pdo->exec("INSERT INTO savings SELECT * FROM temp_savings");
    $pdo->exec("TRUNCATE TABLE temp_savings");

    $pdo->exec("UPDATE temp_loans SET user_id = {$successor_id}");
    $pdo->exec("INSERT INTO loans SELECT * FROM temp_loans");
    $pdo->exec("TRUNCATE TABLE temp_loans");

    // --- Step 6: Log the Action ---
    $successor_info_stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $successor_info_stmt->execute([$successor_id]);
    $successor_username = $successor_info_stmt->fetchColumn();

    log_action($pdo, $outgoing_chairman_id, "Handed over Chairman role to {$successor_username} (ID: {$successor_id}). Financial records were successfully migrated.");

    $pdo->commit();

    // --- Step 7: Force Logout to Refresh Session ---
    session_destroy();
    header('Location: login.php?success=Handover complete. Please log in again.');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    // Clean up temp tables on failure
    $pdo->exec("TRUNCATE TABLE temp_savings");
    $pdo->exec("TRUNCATE TABLE temp_loans");
    header('Location: handover.php?error=An error occurred during the handover process: ' . urlencode($e->getMessage()));
    exit;
}
?>
