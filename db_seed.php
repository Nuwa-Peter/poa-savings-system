<?php
// db_seed.php
// A command-line script to seed the database with initial admin users.

// Prevent execution via browser
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once 'config/db_connect.php';

try {
    echo "Starting database seeding...\n";

    // 1. Truncate the users table to ensure a clean slate
    $pdo->exec("TRUNCATE TABLE users");
    echo "Users table truncated.\n";

    // 2. Start a transaction for the INSERT statements
    $pdo->beginTransaction();

    // 3. Define the users to be created
    $default_password = 'password';
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

    if ($hashed_password === false) {
        throw new Exception("Failed to hash the default password.");
    }

    echo "Default password hashed successfully.\n";

    $users_to_seed = [
        [
            'id' => 1,
            'account_no' => 'POA00000',
            'username' => 'root',
            'email' => 'root@poa.dev',
            'password' => $hashed_password,
            'role_id' => 1, // Root role
        ],
        [
            'id' => 2,
            'account_no' => 'POA00001',
            'username' => 'chairman',
            'email' => 'chairman@poa.dev',
            'password' => $hashed_password,
            'role_id' => 2, // Chairman role
        ],
    ];

    // 3. Prepare the SQL statement
    $stmt = $pdo->prepare(
        "INSERT INTO users (id, account_no, username, email, password, role_id)
         VALUES (:id, :account_no, :username, :email, :password, :role_id)"
    );

    // 4. Insert each user
    foreach ($users_to_seed as $user) {
        $stmt->execute($user);
        echo "User '{$user['username']}' created with account number {$user['account_no']}.\n";
    }

    // Commit the transaction
    $pdo->commit();

    echo "\nDatabase seeding completed successfully!\n";
    echo "You can now log in with the default users (root, chairman) using the password: '$default_password'\n";

} catch (Exception $e) {
    // Roll back the transaction if something failed
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die("\nERROR: Database seeding failed.\n" . $e->getMessage() . "\n");
}
