<?php
require_once 'config/db_connect.php';

echo "--- POA Savings Database Repair Script ---\n";

try {
    // 1. Fix Users table status column
    echo "Updating 'users' table status column...\n";
    $pdo->exec("ALTER TABLE users MODIFY status VARCHAR(50) NOT NULL DEFAULT 'active'");

    // 2. Fix Loan Guarantors table status column
    echo "Updating 'loan_guarantors' table status column...\n";
    $pdo->exec("ALTER TABLE loan_guarantors MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");

    // 3. Create Roles Table
    echo "Ensuring 'roles' table exists...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `roles` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `role_name` varchar(50) NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. Seed Roles
    echo "Seeding 'roles'...\n";
    $roles = [
        [1, 'Root'],
        [2, 'Chairman'],
        [3, 'Secretary'],
        [4, 'Treasurer'],
        [5, 'Member']
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO roles (id, role_name) VALUES (?, ?)");
    foreach ($roles as $role) {
        $stmt->execute($role);
    }

    // 5. Create Credit Scores Table
    echo "Ensuring 'member_credit_scores' table exists...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `member_credit_scores` (
        `user_id` int(11) NOT NULL,
        `score` int(11) DEFAULT 500,
        `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`user_id`),
        CONSTRAINT `fk_user_score_repair` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "----------------------------------------\n";
    echo "Database repair completed successfully!\n";

} catch (PDOException $e) {
    echo "FATAL ERROR during database repair: " . $e->getMessage() . "\n";
}
?>
