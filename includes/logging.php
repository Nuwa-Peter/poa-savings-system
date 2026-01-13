<?php
// This function requires the $pdo object to be in the global scope or passed as an argument.
// For simplicity, we assume it's included before this file where needed.
// Example: require_once 'config/db_connect.php'; require_once 'includes/logging.php';

/**
 * Logs a specific action performed by a user.
 *
 * @param PDO $pdo The database connection object.
 * @param int $user_id The ID of the user performing the action.
 * @param string $action_description A description of the action.
 * @return void
 */
function log_action($pdo, $user_id, $action_description) {
    try {
        $stmt = $pdo->prepare('INSERT INTO logs (user_id, action) VALUES (?, ?)');
        $stmt->execute([$user_id, $action_description]);
    } catch (PDOException $e) {
        // In a real-world application, you might want to log this error to a file
        // instead of halting execution, as logging is often a non-critical background task.
        // For this project, we can suppress the error or handle it minimally.
        // error_log('Logging failed: ' . $e->getMessage());
    }
}
