<?php
require_once 'includes/auth_check.php';
// All authenticated users can request a withdrawal
check_permissions([1, 2, 3, 4, 5]);

require_once 'templates/header.php';
?>

<div class="container mx-auto mt-10">
    <h2 class="text-2xl font-bold mb-5">Request a Withdrawal</h2>

    <?php if (isset($_GET['success'])): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">Success!</span> Your withdrawal request has been submitted.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Error!</span> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <form action="process_withdrawal.php" method="POST" class="bg-white dark:bg-gray-800 p-8 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-1 gap-6">
            <div>
                <label for="amount" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Amount: <span class="text-red-500">*</span></label>
                <input type="number" step="1" name="amount" id="amount" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <div class="mt-8 flex justify-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-md focus:outline-none focus:shadow-outline transition duration-150 ease-in-out">
                Submit Request
            </button>
        </div>
    </form>
</div>

<?php
require_once 'templates/footer.php';
?>
