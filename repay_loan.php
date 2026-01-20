<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$user_id = $_SESSION['user_id'];
$active_loan = null;
$payment_history = [];

try {
    // Find the user's active, approved loan
    $stmt = $pdo->prepare("SELECT id, amount, balance, due_date FROM loans WHERE user_id = ? AND status = 'approved'");
    $stmt->execute([$user_id]);
    $active_loan = $stmt->fetch();

    if ($active_loan) {
        // Fetch payment history for this loan
        $history_stmt = $pdo->prepare("SELECT amount, paid_at FROM loan_payments WHERE loan_id = ? ORDER BY paid_at DESC");
        $history_stmt->execute([$active_loan['id']]);
        $payment_history = $history_stmt->fetchAll();
    }

} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10">
    <h2 class="text-3xl font-bold mb-6 text-gray-800">Repay Loan</h2>

    <?php if (isset($_GET['success'])): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">Success!</span> <?php echo htmlspecialchars(urldecode($_GET['success'])); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Error!</span> <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($db_error)): ?>
         <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Database Error!</span> <?php echo htmlspecialchars($db_error); ?>
        </div>
    <?php endif; ?>

    <?php if ($active_loan): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Repayment Form -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Loan Details</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-500">Original Amount</p>
                        <p class="text-lg font-semibold"><?php echo number_format($active_loan['amount'], 2); ?> UGX</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Outstanding Balance</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo number_format($active_loan['balance'], 2); ?> UGX</p>
                    </div>
                     <div>
                        <p class="text-sm text-gray-500">Due Date</p>
                        <p class="text-lg font-semibold"><?php echo date('M j, Y', strtotime($active_loan['due_date'])); ?></p>
                    </div>
                </div>

                <hr class="my-6">

                <h3 class="text-xl font-semibold text-gray-700 mb-4">Make a Payment</h3>
                <form action="process_repayment.php" method="POST">
                    <input type="hidden" name="loan_id" value="<?php echo $active_loan['id']; ?>">
                    <input type="hidden" name="current_balance" value="<?php echo $active_loan['balance']; ?>">
                    <div class="mb-4">
                        <label for="amount" class="block text-gray-700 text-sm font-bold mb-2">Payment Amount:</label>
                        <input type="number" step="0.01" name="amount" id="amount" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Submit Payment
                    </button>
                </form>
            </div>

            <!-- Payment History -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-xl font-semibold text-gray-700 mb-4">Payment History</h3>
                <div class="overflow-auto max-h-96">
                    <?php if (empty($payment_history)): ?>
                        <p class="text-center text-gray-500">No payments made yet.</p>
                    <?php else: ?>
                        <ul class="space-y-4">
                            <?php foreach ($payment_history as $payment): ?>
                                <li class="flex justify-between items-center border-b pb-2">
                                    <div>
                                        <p class="font-semibold text-green-600"><?php echo number_format($payment['amount'], 2); ?> UGX</p>
                                        <p class="text-xs text-gray-500"><?php echo date('M j, Y, g:i a', strtotime($payment['paid_at'])); ?></p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-white p-6 rounded-lg shadow-md text-center">
            <p class="text-gray-700">You do not have an active loan to repay.</p>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'templates/footer.php';
?>
