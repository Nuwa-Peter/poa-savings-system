<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'POA Savings'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/dist/style.css" rel="stylesheet">
</head>
<body>

    <div id="app" class="flex">
        <!-- Sidebar for web view -->
        <aside class="w-64 bg-gray-800 text-white p-4 hidden md:block">
            <h1 class="text-2xl font-bold mb-4">POA Savings</h1>
            <nav>
                <ul>
                    <li><a href="dashboard.php" class="block py-2">Dashboard</a></li>
                    <!-- More links here based on role -->
                </ul>
            </nav>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col">
            <!-- Top navigation bar -->
            <header class="bg-white shadow-md p-4 flex justify-between items-center">
                <div>
                    <!-- Hamburger icon for mobile -->
                    <button class="md:hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                        </svg>
                    </button>
                </div>
                <div class="flex items-center">
                    <span class="mr-4"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?></span>
                    <img src="<?php echo htmlspecialchars($user['avatar'] ?? 'https://via.placeholder.com/40'); ?>" alt="User Avatar" class="w-10 h-10 rounded-full">
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 p-4">
                <?php echo $content ?? ''; ?>
            </main>
        </div>
    </div>

    <!-- Bottom navigation for mobile view -->
    <nav class="md:hidden fixed bottom-0 w-full bg-gray-800 text-white flex justify-around p-2">
        <a href="dashboard.php" class="text-center">Dashboard</a>
        <a href="#" class="text-center">Profile</a>
        <a href="#" class="text-center">Settings</a>
    </nav>

    <script src="../assets/js/main.js"></script>
</body>
</html>
