<?php
// Database configuration
$host = 'localhost';
$dbname = 'poa_savings';
$username = 'poa_user';
$password = 'poa_password';

try {
    // Create a PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

    // Set PDO to throw exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set the default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- Efficient Self-healing Database Schema Logic ---
    // Only run the repair if the schema_fixed flag doesn't exist to prevent performance issues.
    $lockFile = __DIR__ . '/../.schema_fixed';
    if (!file_exists($lockFile)) {
        try {
            // 1. Fix Status Columns (Convert ENUM to VARCHAR to prevent truncation)
            $pdo->exec("ALTER TABLE users MODIFY status VARCHAR(50) NOT NULL DEFAULT 'active'");
            $pdo->exec("ALTER TABLE loan_guarantors MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");
            $pdo->exec("ALTER TABLE withdrawals MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");
            $pdo->exec("ALTER TABLE loans MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");

            // 2. Ensure Savings Table Columns exist
            $columns = $pdo->query("SHOW COLUMNS FROM savings")->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('description', $columns)) {
                $pdo->exec("ALTER TABLE savings ADD COLUMN description VARCHAR(255) NULL AFTER amount");
            }

            if (!in_array('verified_by_user_id', $columns)) {
                $pdo->exec("ALTER TABLE savings ADD COLUMN verified_by_user_id INT(11) NULL AFTER description");
            }

            // 3. Ensure Supporting Tables exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS `roles` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `role_name` varchar(50) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("INSERT IGNORE INTO roles (id, role_name) VALUES (1, 'Root'), (2, 'Chairman'), (3, 'Secretary'), (4, 'Treasurer'), (5, 'Member')");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `member_credit_scores` (
                `user_id` int(11) NOT NULL,
                `score` int(11) DEFAULT 500,
                `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`user_id`),
                CONSTRAINT `fk_user_score_sh` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Mark the schema as fixed
            file_put_contents($lockFile, date('Y-m-d H:i:s'));

        } catch (PDOException $schema_err) {
             // error_log("Schema healing failed: " . $schema_err->getMessage());
        }
    }

} catch (PDOException $e) {
    // Handle connection errors
    die("Database connection failed: " . $e->getMessage());
}
