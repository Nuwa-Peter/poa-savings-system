<?php
require_once 'includes/auth_check.php';
// All authenticated users can request a loan
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$user_id = $_SESSION['user_id'];
$is_eligible = false;
$savings_this_month = 0;
$total_savings = 0;
$loan_limit = 0;
$db_error = null;

try {
    // 1. Fetch total savings to calculate loan limit
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM savings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_savings = $stmt->fetchColumn() ?: 0;
    $loan_limit = $total_savings * 0.5;

    // 2. Fetch number of savings in the current calendar month
    $start_of_month = date('Y-m-01');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM savings WHERE user_id = ? AND created_at >= ?");
    $stmt->execute([$user_id, $start_of_month]);
    $savings_this_month = $stmt->fetchColumn() ?: 0;

    // 3. Determine eligibility
    if ($savings_this_month >= 3 || $total_savings >= 500000) {
        $is_eligible = true;
    }

    // 4. Fetch other members to be potential guarantors
    $stmt = $pdo->prepare("SELECT id, first_name, surname FROM users WHERE id != ? AND role_id = 5 ORDER BY first_name ASC");
    $stmt->execute([$user_id]);
    $other_members = $stmt->fetchAll();

} catch (PDOException $e) {
    $db_error = "Could not fetch your financial data: " . $e->getMessage();
    $other_members = []; // Ensure this is an array to prevent errors in the form
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

    <?php if ($db_error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Database Error!</span> <?php echo htmlspecialchars($db_error); ?>
        </div>
    <?php endif; ?>

    <!-- Eligibility & Info Section -->
    <?php if (!$is_eligible): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-r-lg" role="alert">
        <p class="font-bold text-lg mb-2">Your Path to Loan Eligibility</p>
        <p>You are not yet eligible for a loan. Here's what you need to achieve:</p>
        <ul class="list-disc list-inside mt-3 space-y-1">
            <li>
                <strong>Savings This Month:</strong> <?php echo $savings_this_month; ?> / 3
                <div class="w-full bg-gray-200 rounded-full h-2.5 mt-1">
                    <div class="bg-green-600 h-2.5 rounded-full" style="width: <?php echo min(100, ($savings_this_month / 3) * 100); ?>%"></div>
                </div>
            </li>
            <li class="mt-2">
                <strong>Your Current Loan Limit:</strong> <?php echo number_format($loan_limit, 2); ?> UGX
                <p class="text-xs">(This is 50% of your total savings of <?php echo number_format($total_savings, 2); ?> UGX)</p>
            </li>
        </ul>
        <p class="mt-4">Once you have made at least <strong>3 savings</strong> this month, the loan application form will become available here.</p>
    </div>
    <?php else: ?>
    <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
        <p class="font-bold">Loan Terms</p>
        <p class="mt-2">A <strong>2% monthly interest rate</strong> applies to the outstanding balance. Your loan limit is <strong>50%</strong> of your total savings, which is currently <strong><?php echo number_format($loan_limit, 2); ?> UGX</strong>.</p>
    </div>
    <?php endif; ?>


    <!-- Loan Application Form (Only shows if eligible) -->
    <?php if ($is_eligible): ?>
    <form action="process_loan.php" method="POST" class="bg-white dark:bg-gray-800 p-8 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-1 gap-6">
            <div>
                <label for="amount" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Loan Amount: <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" name="amount" id="amount" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label for="guarantor_id" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Select a Guarantor: <span class="text-red-500">*</span></label>
                <select name="guarantor_id" id="guarantor_id" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="" disabled selected>Select a member to guarantee your loan</option>
                    <?php foreach ($other_members as $member): ?>
                        <option value="<?php echo htmlspecialchars($member['id']); ?>">
                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['surname']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">The member you select will be sent a request to approve guaranteeing your loan. Your loan cannot be approved by an admin until your guarantor approves.</p>
            </div>
        </div>
        <div class="mt-8 flex justify-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-md focus:outline-none focus:shadow-outline transition duration-150 ease-in-out">
                Submit Loan Application
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php
require_once 'templates/footer.php';
?>
