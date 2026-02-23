<?php
require_once 'config/db_connect.php';

echo "--- POA Savings Database Repair Script ---\n";

try {
    // 1. Create Roles Table
    echo "Creating 'roles' table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `roles` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `role_name` varchar(50) NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);

    // 2. Seed Roles Table
    echo "Seeding 'roles' table...\n";
    $roles = [
        [1, 'Root'],
        [2, 'Chairman'],
        [3, 'Secretary'],
        [4, 'Treasurer'],
        [5, 'Member']
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `roles` (`id`, `role_name`) VALUES (?, ?)");
    foreach ($roles as $role) {
        $stmt->execute($role);
    }

    // 3. Create Member Credit Scores Table
    echo "Creating 'member_credit_scores' table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `member_credit_scores` (
        `user_id` int(11) NOT NULL,
        `score` int(11) DEFAULT 500,
        `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`user_id`),
        CONSTRAINT `fk_user_score_repair` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);

    echo "----------------------------------------\n";
    echo "Database schema fixes applied successfully!\n";
    echo "You should also run migrations: ddev exec vendor/bin/phinx migrate\n";

} catch (PDOException $e) {
    echo "FATAL ERROR during database repair: " . $e->getMessage() . "\n";
}
?>
