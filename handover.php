<?php
require_once 'includes/auth_check.php';
check_permissions([2]); // Only Chairman can access this page

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$current_chairman_id = $_SESSION['user_id'];
$eligible_successors = [];
$error = '';

try {
    // Fetch all members (role_id 5) who are not root or chairman
    $stmt = $pdo->prepare("SELECT id, first_name, surname FROM users WHERE role_id = 5 AND status = 'active' ORDER BY first_name ASC");
    $stmt->execute();
    $eligible_successors = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Could not fetch eligible members: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10">
    <h2 class="text-2xl font-bold mb-5">Handover Chairman Role</h2>

    <?php if ($error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
        <p class="font-bold">Important Notice</p>
        <p>This action is irreversible. When you hand over your role to a successor:</p>
        <ul class="list-disc list-inside mt-2">
            <li>Your financial history (savings, loans) will be archived and transferred back to you as a regular member.</li>
            <li>The successor's financial history will be transferred to the Chairman account.</li>
            <li>You will be demoted to a regular member, and the successor will be promoted to Chairman.</li>
        </ul>
    </div>

    <form action="process_handover.php" method="POST" class="bg-white p-6 rounded-lg shadow-md" onsubmit="return confirm('Are you absolutely sure you want to proceed with this handover? This action cannot be undone.');">
        <div class="mb-4">
            <label for="successor_id" class="block text-gray-700 text-sm font-bold mb-2">Select Successor:</label>
            <select name="successor_id" id="successor_id" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <option value="" disabled selected>Select a member to be the new Chairman</option>
                <?php foreach ($eligible_successors as $successor): ?>
                    <option value="<?php echo htmlspecialchars($successor['id']); ?>">
                        <?php echo htmlspecialchars($successor['first_name'] . ' ' . $successor['surname']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-center justify-between">
            <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Initiate Handover
            </button>
        </div>
    </form>
</div>

<?php
require_once 'templates/footer.php';
?>
