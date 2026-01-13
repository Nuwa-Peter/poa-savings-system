<?php
/**
 * Generates a new, unique account number for a user.
 * The format is POAXXXXX, where XXXXX is a zero-padded number.
 *
 * @param PDO $pdo The database connection object.
 * @return string The new, unique account number.
 * @throws Exception If a unique account number cannot be generated.
 */
function generate_account_number($pdo) {
    $prefix = 'POA';
    $padding = 5;

    try {
        // Start a transaction to ensure atomicity
        $pdo->beginTransaction();

        // Lock the table to prevent race conditions
        $stmt = $pdo->query("SELECT account_no FROM users ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $last_account = $stmt->fetchColumn();

        $next_number = 1;
        if ($last_account) {
            // Extract the numeric part and increment it
            $numeric_part = (int)substr($last_account, strlen($prefix));
            $next_number = $numeric_part + 1;
        }

        // Format the new account number
        $new_account_no = $prefix . str_pad($next_number, $padding, '0', STR_PAD_LEFT);

        // Commit the transaction
        $pdo->commit();

        return $new_account_no;

    } catch (PDOException $e) {
        // Rollback on error
        $pdo->rollBack();
        throw new Exception("Failed to generate account number: " . $e->getMessage());
    }
}
