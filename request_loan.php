<?php
require_once 'includes/auth_check.php';
// All authenticated users can request a loan
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

// Fetch other members to be potential guarantors
$other_members = [];
try {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id != ? AND role_id = 5 ORDER BY username ASC");
    $stmt->execute([$_SESSION['user_id']]);
    $other_members = $stmt->fetchAll();
} catch (PDOException $e) {
    $db_error = "Could not fetch members: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10">
    <h2 class="text-2xl font-bold mb-5">Request a Loan</h2>

    <?php if (isset($_GET['success'])): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">Success!</span> Your loan request has been submitted for review.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Error!</span> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
        <p class="font-bold">Loan Eligibility Criteria</p>
        <ul class="list-disc list-inside mt-2">
            <li>You must have saved at least <strong>three times a week</strong> for the past <strong>four weeks</strong>.</li>
            <li>The requested loan amount cannot exceed <strong>50%</strong> of your total savings.</li>
            <li>All loans are subject to a <strong>2% monthly interest rate</strong>.</li>
        </ul>
    </div>

    <form action="process_loan.php" method="POST" class="bg-white p-6 rounded-lg shadow-md">
        <div class="mb-4">
            <label for="amount" class="block text-gray-700 text-sm font-bold mb-2">Loan Amount:</label>
            <input type="number" step="0.01" name="amount" id="amount" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>
        <div class="mb-6">
            <label for="guarantor_id" class="block text-gray-700 text-sm font-bold mb-2">Select a Guarantor:</label>
            <select name="guarantor_id" id="guarantor_id" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <option value="" disabled selected>Select a member to guarantee your loan</option>
                <?php foreach ($other_members as $member): ?>
                    <option value="<?php echo htmlspecialchars($member['id']); ?>">
                        <?php echo htmlspecialchars($member['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-600 mt-2">The member you select will be sent a request to approve guaranteeing your loan. Your loan cannot be approved by an admin until your guarantor approves.</p>
        </div>
        <div class="flex items-center justify-between">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Submit Loan Application
            </button>
        </div>
    </form>
</div>

<?php
require_once 'templates/footer.php';
?>
