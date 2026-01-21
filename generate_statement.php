<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);
require_once 'templates/header.php';
?>

<div class="container mx-auto mt-10 p-4">
    <div class="max-w-2xl mx-auto">
        <div class="text-center">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-white">Account Statement</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">Generate a detailed statement of your account activity for a selected period.</p>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="mt-6 p-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert">
                <span class="font-medium">Error!</span> <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
            </div>
        <?php endif; ?>

        <div class="mt-8 bg-white dark:bg-gray-800 p-8 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
            <form action="process_statement.php" method="POST" target="_blank">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="start_date" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Start Date:</label>
                        <input type="date" name="start_date" id="start_date" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">End Date:</label>
                        <input type="date" name="end_date" id="end_date" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="mt-8">
                    <button type="submit" class="w-full flex items-center justify-center space-x-2 bg-gray-800 hover:bg-gray-900 text-white font-bold py-3 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-150 ease-in-out">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span>Generate Statement</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Set default end date to today
    document.getElementById('end_date').valueAsDate = new Date();
</script>

<?php
require_once 'templates/footer.php';
?>
