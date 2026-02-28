<?php
require_once 'config/db_connect.php';

echo "--- Savings Deduction Schema Repair ---\n";

try {
    // 1. Add description column to savings if it doesn't exist
    echo "Updating 'savings' table schema...\n";
    $pdo->exec("ALTER TABLE savings ADD COLUMN IF NOT EXISTS description VARCHAR(255) NULL AFTER amount");

    // 2. Fix ENUM mapping for guarantor requests if not already VARCHAR
    echo "Updating 'loan_guarantors' table status column...\n";
    // We try to convert to VARCHAR to prevent truncation errors in future
    $pdo->exec("ALTER TABLE loan_guarantors MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");

    echo "----------------------------------------\n";
    echo "Schema updates applied successfully!\n";

} catch (PDOException $e) {
    echo "ERROR during database repair: " . $e->getMessage() . "\n";
}
?>
