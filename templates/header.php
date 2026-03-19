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
    <meta name="theme-color" content="#4f46e5">
    <title>POA Savings Management System</title>
    <link rel="icon" href="assets/images/poa_light.png" type="image/png">
    <link rel="manifest" href="manifest.json">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <link rel="stylesheet" href="assets/vendor/cropperjs/cropper.min.css">
    <script src="assets/vendor/cropperjs/cropper.min.js" defer></script>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="assets/js/main.js" defer></script>
    <script src="assets/js/theme.js" defer></script>
</head>
<?php
require_once 'includes/device_info.php';
$device = getDeviceInfo();
$body_class = "bg-slate-50 flex ";
$body_class .= strtolower(str_replace(' ', '-', $device['os'])) . " ";
$body_class .= strtolower(str_replace(' ', '-', $device['browser']));
?>
<body class="<?php echo $body_class; ?>">
    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-2"></div>

    <!-- Mobile Sidebar Overlay -->
    <div id="sidebar-overlay" class="md:hidden"></div>

    <?php
    if ($is_logged_in) {
        // Include notification functions
        require_once 'includes/notifications.php';
        // Include new avatar functions
        require_once 'includes/avatar_functions.php';
        // Include reusable UI components
        require_once 'includes/ui_components.php';

        // Fetch user's avatar path for display
        // In a real app, you would have this from the initial login query
        require_once 'config/db_connect.php';
        try {
            $user_id = $_SESSION['user_id'];
            // Fetch user details for avatar and display
            $stmt = $pdo->prepare("SELECT username, first_name, surname, avatar FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_details = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fetch unread notification count
            $notify_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $notify_stmt->execute([$user_id]);
            $unread_notifications_count = $notify_stmt->fetchColumn();

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
        <nav class="flex-grow p-4 overflow-y-auto sidebar-nav">
            <ul class="space-y-1">
                <!-- Group 1: SOCIETY OVERVIEW -->
                <li class="pt-6 pb-2">
                    <span class="px-4 text-[10px] text-gray-500 font-bold uppercase tracking-wider">Society Overview</span>
                </li>
                <li><a href="dashboard.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="layout-dashboard" class="w-4 h-4"></i><span>Dashboard</span></a></li>
                <?php if (in_array($role_id, [1, 2, 3, 4])): ?>
                    <li><a href="dashboard.php?view=personal" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="user-check" class="w-4 h-4 text-emerald-400"></i><span>My Personal Account</span></a></li>
                <?php endif; ?>
                <li><a href="view_member.php?id=<?php echo $_SESSION['user_id']; ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="user" class="w-4 h-4 text-indigo-400"></i><span>My Member Profile</span></a></li>
                <li><a href="about.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="help-circle" class="w-4 h-4 text-indigo-400"></i><span>About & Help</span></a></li>
                <?php if (in_array($role_id, [1, 2])): ?>
                    <li><a href="member_directory.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="users-2" class="w-4 h-4 text-blue-400"></i><span>Member Directory</span></a></li>
                <?php endif; ?>

                <!-- Group 2: FINANCIAL SERVICES -->
                <li class="pt-6 pb-2">
                    <span class="px-4 text-[10px] text-gray-500 font-bold uppercase tracking-wider">Financial Services</span>
                </li>
                <li><a href="view_savings.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="piggy-bank" class="w-4 h-4 text-emerald-400"></i><span>View Savings</span></a></li>
                <li><a href="withdraw.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="arrow-up-circle" class="w-4 h-4 text-orange-400"></i><span>Request Withdrawal</span></a></li>
                <li><a href="request_loan.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="landmark" class="w-4 h-4 text-blue-400"></i><span>Request Loan</span></a></li>
                <li><a href="repay_loan.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="wallet" class="w-4 h-4 text-emerald-400"></i><span>Repay Loan</span></a></li>
                <li><a href="view_dividends.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="pie-chart" class="w-4 h-4 text-pink-400"></i><span>View Dividends</span></a></li>
                <li><a href="welfare.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="heart" class="w-4 h-4 text-rose-400"></i><span>Welfare Fund</span></a></li>
                <li><a href="view_share_capital.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="award" class="w-4 h-4 text-purple-400"></i><span>View Share Capital</span></a></li>
                <li><a href="fixed_deposits.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="lock" class="w-4 h-4 text-emerald-400"></i><span>Fixed Deposits</span></a></li>
                <li><a href="pay_subscription.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="calendar-check" class="w-4 h-4 text-orange-400"></i><span>View Subscriptions</span></a></li>
                <li><a href="savings_goals.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="target" class="w-4 h-4 text-amber-400"></i><span>Savings Goals</span></a></li>
                <li><a href="guarantor_requests.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="users" class="w-4 h-4 text-indigo-400"></i><span>Guarantor Requests</span></a></li>
                <li><a href="generate_statement.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="file-text" class="w-4 h-4"></i><span>View Statement</span></a></li>

                <!-- Group 3: ADMINISTRATION -->
                <?php if (in_array($role_id, [1, 2, 3])): ?>
                    <li class="pt-6 pb-2">
                        <span class="px-4 text-[10px] text-gray-500 font-bold uppercase tracking-wider">Administration</span>
                    </li>
                    <li><a href="manage_requests.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="clipboard-list" class="w-4 h-4"></i><span>Manage Requests</span></a></li>
                    <li><a href="add_member.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="user-plus" class="w-4 h-4"></i><span>Add Member</span></a></li>
                    <li><a href="add_saving.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="plus-circle" class="w-4 h-4"></i><span>Add Saving</span></a></li>
                    <li><a href="admin_share_capital.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="award" class="w-4 h-4"></i><span>Manage Share Capital</span></a></li>
                    <li><a href="admin_subscriptions.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="calendar-days" class="w-4 h-4"></i><span>Manage Subscriptions</span></a></li>
                    <?php if (in_array($role_id, [1, 2])): ?>
                        <li><a href="apply_interest.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="percent" class="w-4 h-4"></i><span>Apply Interest</span></a></li>
                        <li><a href="admin_dividends.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="gift" class="w-4 h-4"></i><span>Distribute Dividends</span></a></li>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Group 4: REPORTING & ANALYTICS -->
                <?php if (in_array($role_id, [1, 2, 3, 4])): ?>
                    <li class="pt-6 pb-2">
                        <span class="px-4 text-[10px] text-gray-500 font-bold uppercase tracking-wider">Reports & Analytics</span>
                    </li>
                    <li><a href="reports.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="bar-chart-big" class="w-4 h-4 text-emerald-400"></i><span>System Reports</span></a></li>
                    <li><a href="financial_reports.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="landmark" class="w-4 h-4 text-emerald-400"></i><span>Financial Reports</span></a></li>
                    <li><a href="admin_welfare.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="activity" class="w-4 h-4"></i><span>Welfare Fund Report</span></a></li>
                    <li><a href="admin_expenses.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="receipt" class="w-4 h-4"></i><span>Society Expenses</span></a></li>
                    <li><a href="admin_investments.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="briefcase" class="w-4 h-4"></i><span>Investment Portfolio</span></a></li>
                    <li><a href="admin_analytics_view.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="line-chart" class="w-4 h-4"></i><span>Analytics View</span></a></li>
                    <li><a href="admin_tabular_view.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="table" class="w-4 h-4"></i><span>Tabular View</span></a></li>
                <?php endif; ?>

                <!-- Group 5: SECURITY & TOOLS -->
                <?php if (in_array($role_id, [1, 2])): ?>
                    <li class="pt-6 pb-2">
                        <span class="px-4 text-[10px] text-gray-500 font-bold uppercase tracking-wider">Security & Tools</span>
                    </li>
                    <li><a href="admin_reset_password.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="key" class="w-4 h-4"></i><span>Reset User Password</span></a></li>
                    <li><a href="admin_audit_view.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="shield-check" class="w-4 h-4"></i><span>Audit View</span></a></li>
                    <?php if ($role_id == 2): ?>
                        <li><a href="handover.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="refresh-cw" class="w-4 h-4"></i><span>Handover Role</span></a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="p-4 border-t border-gray-700 sidebar-footer">
            <a href="settings.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors"><i data-lucide="settings" class="w-4 h-4"></i><span>Settings</span></a>
            <a href="logout.php" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-white/10 transition-colors text-rose-400"><i data-lucide="log-out" class="w-4 h-4"></i><span>Logout</span></a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="main-content flex-grow">
        <header class="bg-white shadow-md p-4 flex justify-between items-center w-full">
            <!-- Mobile Menu Button -->
            <button id="menu-button" class="text-gray-800 md:hidden">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
            </button>

            <?php
            $role_names = [1 => 'Root', 2 => 'Chairman', 3 => 'Secretary', 4 => 'Treasurer', 5 => 'Member'];
            $role_name = $role_names[$_SESSION['role_id']] ?? 'Guest';
            ?>
            <h1 class="text-xl font-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($role_name); ?> of POA Savings and Credit Society</h1>

            <!-- Theme Toggle and User Avatar -->
            <div class="flex items-center space-x-4">
                <!-- Theme Toggle Button -->
                <button id="theme-toggle" type="button" class="text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:focus:ring-gray-700 rounded-lg text-sm p-2.5">
                    <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                    <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 5.05A1 1 0 016.465 3.636l.707.707a1 1 0 01-1.414 1.414l-.707-.707a1 1 0 010-1.414zM5 11a1 1 0 100-2H4a1 1 0 100 2h1zM8 16a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM3.636 6.465a1 1 0 011.414 0l.707.707a1 1 0 01-1.414 1.414l-.707-.707a1 1 0 010-1.414z"></path></svg>
                </button>

                <!-- Notification Bell -->
                <div class="relative">
                    <a href="notifications.php" class="relative">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <?php if ($unread_notifications_count > 0): ?>
                            <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-xs text-white"><?php echo $unread_notifications_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- User Avatar & Dropdown -->
                <a href="settings.php" class="relative">
                     <?php
                     if ($user_details) {
                         display_avatar($user_details['avatar'], $user_details['username'], $user_details['first_name'], $user_details['surname']);
                     }
                     ?>
                </a>
            </div>
        </header>
        <main class="p-6">
    <?php endif; ?>
