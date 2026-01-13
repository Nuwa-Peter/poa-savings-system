<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POA Savings Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-md">
        <div class="container mx-auto px-6 py-3">
            <div class="flex items-center justify-between">
                <div class="text-xl font-semibold text-gray-700">
                    <a href="dashboard.php">POA Savings (Swahili for 'Good & Safe')</a>
                </div>
                <div class="flex items-center">
                    <?php if (isset($_SESSION['role_id'])): ?>
                        <?php
                        $role_id = $_SESSION['role_id'];
                        // Roles: 1:Root, 2:Chairman, 3:Secretary, 4:Treasurer, 5:Member

                        // Add Saving link (for Root, Chairman, Secretary)
                        if (in_array($role_id, [1, 2, 3])) {
                            echo '<a href="add_saving.php" class="text-gray-600 hover:text-gray-800 px-3 py-2">Add Saving</a>';
                        }

                        // View Logs link (for Root, Chairman)
                        if (in_array($role_id, [1, 2])) {
                            echo '<a href="view_logs.php" class="text-gray-600 hover:text-gray-800 px-3 py-2">View Logs</a>';
                        }

                        // All logged-in users get a logout link
                        echo '<a href="logout.php" class="text-gray-600 hover:text-gray-800 px-3 py-2">Logout</a>';
                        ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main class="container mx-auto px-6 py-8">
