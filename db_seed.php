<?php
require_once 'config/db_connect.php';

// --- Security Check: Ensure this script is run from the CLI ---
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

try {
    echo "Starting database seeding...\n";

    // Disable foreign key checks to allow truncation
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
    echo "Foreign key checks disabled.\n";

    // List of tables to truncate
    $tables = [
        'users',
        'savings',
        'loans',
        'withdrawals',
        'loan_payments',
        'loan_guarantors',
        'notifications',
        'logs',
        'password_resets',
        'system_settings'
    ];

    // Truncate all tables
    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE TABLE `{$table}`;");
        echo "Truncated table: {$table}\n";
    }

    // Re-enable foreign key checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
    echo "Foreign key checks enabled.\n";

    // --- Seed Users Table ---
    $users_to_seed = [
        [
            'id' => 1,
            'account_no' => 'POA00000',
            'first_name' => 'Root',
            'surname' => 'User',
            'username' => 'root',
            'email' => 'root@poa.dev',
            'phone' => null,
            'password' => '$2y$10$CQoZZXkbiWu6/s9vUgBB7OtQuf0JuSOOSYuwsuQDuHSxd1YQy4wbi', // Default password: 'password'
            'role_id' => 1,
            'avatar' => null
        ],
        [
            'id' => 2,
            'account_no' => 'POA00001',
            'first_name' => 'Chairman',
            'surname' => 'Admin',
            'username' => 'chairman',
            'email' => 'chairman@poa.dev',
            'phone' => null,
            'password' => '$2y$10$CQoZZXkbiWu6/s9vUgBB7OtQuf0JuSOOSYuwsuQDuHSxd1YQy4wbi', // Default password: 'password'
            'role_id' => 2,
            'avatar' => null
        ]
    ];

    $user_stmt = $pdo->prepare(
        "INSERT INTO `users` (`id`, `account_no`, `first_name`, `surname`, `username`, `email`, `phone`, `password`, `role_id`, `avatar`)
         VALUES (:id, :account_no, :first_name, :surname, :username, :email, :phone, :password, :role_id, :avatar)"
    );

    foreach ($users_to_seed as $user) {
        $user_stmt->execute($user);
    }
    echo "Seeded " . count($users_to_seed) . " users.\n";

    // --- Seed System Settings Table ---
    $settings_stmt = $pdo->prepare("INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
    $settings_stmt->execute(['last_interest_run', null]);
    echo "Seeded system settings.\n";

    echo "--------------------------\n";
    echo "Database seeding complete!\n";

} catch (PDOException $e) {
    // Re-enable foreign key checks on error
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
    die("Database seeding failed: " . $e->getMessage() . "\n");
}
?>
