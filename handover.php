<?php
require_once 'includes/auth_check.php';
// This page is exclusively for the Chairman
check_permissions([2]);

require_once 'config/app_config.php';
require_once 'templates/header.php';
require_once 'config/db_connect.php';

$current_user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle the handover form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_handover'])) {
    $new_chairman_id = $_POST['new_chairman_id'];
    $current_chairman_password = $_POST['password'];

    // --- Validation ---
    if (empty($new_chairman_id) || empty($current_chairman_password)) {
        $error_message = "Please select a member and enter your password to confirm.";
    } elseif ($new_chairman_id == $current_user_id) {
        $error_message = "You cannot hand over the role to yourself.";
    } else {
        try {
            // 1. Verify the current chairman's password for security
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$current_user_id]);
            $hash = $stmt->fetchColumn();

            if (password_verify($current_chairman_password, $hash)) {
                // 2. Perform the handover within a transaction
                $pdo->beginTransaction();

                // Demote current Chairman to a standard Member (role_id 5)
                $demote_stmt = $pdo->prepare("UPDATE users SET role_id = 5 WHERE id = ?");
                $demote_stmt->execute([$current_user_id]);

                // Promote the selected member to Chairman (role_id 2)
                $promote_stmt = $pdo->prepare("UPDATE users SET role_id = 2 WHERE id = ?");
                $promote_stmt->execute([$new_chairman_id]);

                // Commit the transaction
                $pdo->commit();

                // Success: Force logout to reflect role change
                $success_message = "Role handover successful. You are now a standard member and will be logged out.";
                // Destroy session after a delay
                echo '<meta http-equiv="refresh" content="5;url=logout.php">';

            } else {
                $error_message = "Incorrect password. Handover cancelled.";
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = "A database error occurred. Handover failed: " . $e->getMessage();
        }
    }
}


// --- Fetch eligible members to become the new Chairman ---
// Exclude Root, the current Chairman, and non-members
try {
    $stmt = $pdo->prepare(
        "SELECT id, username, account_no FROM users
         WHERE role_id NOT IN (1, 2) -- Exclude Root and current Chairman
         ORDER BY username ASC"
    );
    $stmt->execute();
    $eligible_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Could not fetch list of members.";
    $eligible_users = [];
}
?>

<div class="max-w-4xl mx-auto">
    <h2 class="text-3xl font-bold mb-6 text-gray-800">Handover Chairman Role</h2>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Transfer Leadership</h3>

        <?php if ($success_message): ?>
            <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (empty($success_message)): // Hide form on success ?>
        <form action="handover.php" method="POST" onsubmit="return confirm('Are you absolutely sure you want to hand over your Chairman role? This action is irreversible.');">
            <div class="mb-4">
                <label for="new_chairman_id" class="block text-gray-700 text-sm font-bold mb-2">Select New Chairman:</label>
                <select name="new_chairman_id" id="new_chairman_id" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="" disabled selected>Select a member...</option>
                    <?php foreach ($eligible_users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['username'] . ' (' . $user['account_no'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-600 mt-1">Select the member who will become the new Chairman. Your account will be demoted to a standard member.</p>
            </div>
            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Confirm Your Password:</label>
                <input type="password" name="password" id="password" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p class="text-xs text-gray-600 mt-1">For security, please enter your current password to authorize this transfer.</p>
            </div>
            <div class="flex items-center justify-end">
                <button type="submit" name="confirm_handover" class="bg-red-600 hover:bg-red-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Confirm and Handover Role
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
