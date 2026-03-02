<?php
require_once 'config/db_connect.php';

echo "--- POA Savings Comprehensive Database Fix ---\n";

try {
    // 1. Add 'description' column to 'savings' table if it doesn't exist
    echo "Checking 'savings' table for 'description' column...\n";
    $result = $pdo->query("SHOW COLUMNS FROM savings LIKE 'description'");
    if ($result->rowCount() == 0) {
        echo "Adding 'description' column to 'savings'...\n";
        $pdo->exec("ALTER TABLE savings ADD COLUMN description VARCHAR(255) NULL AFTER amount");
        echo "Column added.\n";
    } else {
        echo "Column already exists.\n";
    }

    // 2. Fix status column types to prevent truncation errors
    echo "Updating status columns to VARCHAR(50)...\n";
    $pdo->exec("ALTER TABLE users MODIFY status VARCHAR(50) NOT NULL DEFAULT 'active'");
    $pdo->exec("ALTER TABLE withdrawals MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");
    $pdo->exec("ALTER TABLE loans MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");
    $pdo->exec("ALTER TABLE loan_guarantors MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");
    echo "Status columns updated.\n";

    // 3. Ensure 'roles' and 'member_credit_scores' tables exist
    echo "Ensuring supporting tables exist...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `roles` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `role_name` varchar(50) NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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

    $pdo->exec("CREATE TABLE IF NOT EXISTS `member_credit_scores` (
        `user_id` int(11) NOT NULL,
        `score` int(11) DEFAULT 500,
        `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`user_id`),
        CONSTRAINT `fk_user_score_fix` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "----------------------------------------\n";
    echo "Database fix applied successfully!\n";

} catch (Exception $e) {
    echo "FATAL ERROR during database fix: " . $e->getMessage() . "\n";
}
?>
