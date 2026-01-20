<?php
// Securely start a session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION['user_id']);
$role_id = $_SESSION['role_id'] ?? 0; // Default to 0 if not logged in
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POA Savings Management System</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="assets/js/main.js" defer></script>
    <script src="assets/js/theme.js" defer></script>
</head>
<body class="bg-gray-100 flex">
    <?php
    if ($is_logged_in) {
        // Include notification functions
        require_once 'includes/notifications.php';
        // Include new avatar functions
        require_once 'includes/avatar_functions.php';

        // Fetch user's avatar path for display
        // In a real app, you would have this from the initial login query
        require_once 'config/db_connect.php';
        try {
            $user_id = $_SESSION['user_id'];
            // Fetch user avatar
            $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_avatar = $stmt->fetchColumn();

            // Fetch unread notification count
            $notify_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $notify_stmt->execute([$user_id]);
            $unread_notifications_count = $notify_stmt->fetchColumn();

            // --- Check if interest needs to be run (for top-level admins) ---
            $show_interest_alert = false;
            if (in_array($role_id, [1, 2])) {
                $interest_log_stmt = $pdo->prepare(
                    "SELECT timestamp FROM logs WHERE action LIKE '%Admin ran interest script%' ORDER BY timestamp DESC LIMIT 1"
                );
                $interest_log_stmt->execute();
                $last_run_timestamp = $interest_log_stmt->fetchColumn();

                if ($last_run_timestamp) {
                    $last_run_month = date('Y-m', strtotime($last_run_timestamp));
                    $current_month = date('Y-m');
                    if ($last_run_month !== $current_month) {
                        $show_interest_alert = true;
                    }
                } else {
                    // If it has never been run, show the alert
                    $show_interest_alert = true;
                }
            }

        } catch (PDOException $e) {
            $user_avatar = null; // Default on error
            $unread_notifications_count = 0;
        }
    }
    ?>
    <?php if ($is_logged_in): ?>
    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar fixed top-0 left-0 h-full w-64 bg-gray-800 text-white flex flex-col transition-transform duration-300 ease-in-out z-30">
        <div class="p-4 border-b border-gray-700 flex justify-center items-center">
            <img id="logo" src="assets/images/poa_light.png" alt="POA Savings Logo" class="h-10">
        </div>
        <nav class="flex-grow p-4 overflow-y-auto">
            <ul class="space-y-2">
                <li><a href="dashboard.php" class="block py-2 px-4 rounded hover:bg-gray-700">Dashboard</a></li>

                <li class="pt-4">
                    <span class="px-4 text-xs text-gray-400 font-semibold uppercase">Member Actions</span>
                </li>
                <li><a href="withdraw.php" class="block py-2 px-4 rounded hover:bg-gray-700">Request Withdrawal</a></li>
                <li><a href="request_loan.php" class="block py-2 px-4 rounded hover:bg-gray-700">Request Loan</a></li>
                <li><a href="repay_loan.php" class="block py-2 px-4 rounded hover:bg-gray-700">Repay Loan</a></li>

                <?php if (in_array($role_id, [1, 2, 3])): // Admin-level actions ?>
                    <li class="pt-4">
                        <span class="px-4 text-xs text-gray-400 font-semibold uppercase">Admin Controls</span>
                    </li>
                    <li><a href="add_user.php" class="block py-2 px-4 rounded hover:bg-gray-700">Add User</a></li>
                    <li><a href="add_saving.php" class="block py-2 px-4 rounded hover:bg-gray-700">Add Saving</a></li>
                    <li><a href="manage_requests.php" class="block py-2 px-4 rounded hover:bg-gray-700">Manage Requests</a></li>
                     <?php if (in_array($role_id, [1, 2])): ?>
                        <li><a href="apply_interest.php" class="block py-2 px-4 rounded hover:bg-gray-700">Apply Interest</a></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (in_array($role_id, [1, 2, 3, 4])): ?>
                    <li class="pt-4">
                        <span class="px-4 text-xs text-gray-400 font-semibold uppercase">Reports & Logs</span>
                    </li>
                    <li><a href="system_report.php" class="block py-2 px-4 rounded hover:bg-gray-700">System Report</a></li>
                <?php endif; ?>

                <?php if (in_array($role_id, [1, 2])): ?>
                    <li><a href="view_logs.php" class="block py-2 px-4 rounded hover:bg-gray-700">View Logs</a></li>
                <?php endif; ?>

                <?php if ($role_id == 2): ?>
                    <li><a href="handover.php" class="block py-2 px-4 rounded hover:bg-gray-700">Handover Role</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="p-4 border-t border-gray-700">
            <a href="settings.php" class="block py-2 px-4 rounded hover:bg-gray-700">Settings</a>
            <a href="logout.php" class="block py-2 px-4 rounded hover:bg-gray-700">Logout</a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="main-content flex-grow">
        <header class="bg-white shadow-md p-4 flex justify-between items-center w-full">
            <!-- Mobile Menu Button -->
            <button id="menu-button" class="text-gray-800 md:hidden">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
            </button>

            <h1 class="text-xl font-bold text-gray-800">POA Savings and Credit Society</h1>

            <!-- Theme Toggle and User Avatar -->
            <div class="flex items-center space-x-4">
                <!-- Theme Toggle Button -->
                <button id="theme-toggle" type="button" class="text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:focus:ring-gray-700 rounded-lg text-sm p-2.5">
                    <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                    <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 5.05A1 1 0 016.465 3.636l.707.707a1 1 0 01-1.414 1.414l-.707-.707a1 1 0 010-1.414zM5 11a1 1 0 100-2H4a1 1 0 100 2h1zM8 16a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM3.636 6.465a1 1 0 011.414 0l.707.707a1 1 0 01-1.414 1.414l-.707-.707a1 1 0 010-1.414z"></path></svg>
                </button>

                <!-- Notification Bell -->
                <div class="relative">
                    <a href="#" class="relative">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <?php if ($unread_notifications_count > 0): ?>
                            <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-xs text-white"><?php echo $unread_notifications_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- User Avatar & Dropdown -->
                <a href="settings.php" class="relative">
                     <?php display_avatar($user_avatar, $_SESSION['username']); ?>
                </a>
            </div>
        </header>
        <main class="p-6">
            <?php if ($show_interest_alert): ?>
            <div id="interest-alert" class="relative mb-6 rounded-lg border-s-4 border-yellow-500 bg-yellow-50 p-4">
                <div class="flex items-center gap-2 text-yellow-800">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                        <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />
                    </svg>
                    <strong class="block font-medium"> Action Required </strong>
                </div>
                <p class="mt-2 text-sm text-yellow-700">The monthly loan interest has not been applied for the current month. Please run the script to ensure all loan balances are up to date.</p>
                <a href="apply_interest.php" class="mt-2 inline-block bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-3 text-sm rounded">Apply Interest Now</a>
                <button onclick="document.getElementById('interest-alert').style.display='none'" class="absolute top-2 right-2 text-yellow-800">&times;</button>
            </div>
            <?php endif; ?>
    <?php endif; ?>
