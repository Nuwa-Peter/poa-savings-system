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
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/styles.css">
    <script src="assets/js/main.js" defer></script>
</head>
<body class="bg-gray-100 flex">
    <?php
    if ($is_logged_in) {
        // Include new avatar functions
        require_once 'includes/avatar_functions.php';

        // Fetch user's avatar path for display
        // In a real app, you would have this from the initial login query
        require_once 'config/db_connect.php';
        try {
            $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user_avatar = $stmt->fetchColumn();
        } catch (PDOException $e) {
            $user_avatar = null; // Default on error
        }
    }
    ?>
    <?php if ($is_logged_in): ?>
    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar fixed top-0 left-0 h-full w-64 bg-gray-800 text-white flex flex-col transition-transform duration-300 ease-in-out z-30">
        <div class="p-4 border-b border-gray-700">
            <h2 class="text-2xl font-bold text-center">POA Savings</h2>
        </div>
        <nav class="flex-grow p-4">
            <ul class="space-y-2">
                <li><a href="dashboard.php" class="block py-2 px-4 rounded hover:bg-gray-700">Dashboard</a></li>

                <?php if (in_array($role_id, [1, 2, 3])): ?>
                    <li><a href="add_user.php" class="block py-2 px-4 rounded hover:bg-gray-700">Add User</a></li>
                    <li><a href="add_saving.php" class="block py-2 px-4 rounded hover:bg-gray-700">Add Saving</a></li>
                <?php endif; ?>

                <?php if (in_array($role_id, [1, 2, 3, 4])): ?>
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

            <!-- This div is just a spacer for the mobile view title, invisible on desktop -->
            <div class="md:invisible">
                 <h1 class="text-xl font-bold">Dashboard</h1>
            </div>

            <!-- User Avatar & Dropdown -->
            <div class="relative">
                 <?php display_avatar($user_avatar, $_SESSION['username']); ?>
            </div>
        </header>
        <main class="p-6">
    <?php endif; ?>
