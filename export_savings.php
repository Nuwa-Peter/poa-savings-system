<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4]); // Admins only

require_once 'config/db_connect.php';

$selected_member_id = $_GET['member_id'] ?? 'all';
$view_mode = $_GET['view_mode'] ?? 'summary';

try {
    if ($view_mode === 'history') {
        if ($selected_member_id !== 'all' && is_numeric($selected_member_id)) {
            $sql = "SELECT u.first_name, u.surname, s.amount, s.created_at
                    FROM savings s
                    JOIN users u ON s.user_id = u.id
                    WHERE s.user_id = ?
                    ORDER BY s.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$selected_member_id]);
        } else {
            $sql = "SELECT u.first_name, u.surname, s.amount, s.created_at
                    FROM savings s
                    JOIN users u ON s.user_id = u.id
                    WHERE u.id != 1
                    ORDER BY s.created_at DESC";
            $stmt = $pdo->query($sql);
        }
        $filename = "savings_history_" . date('Y-m-d') . ".csv";
        $header = ['First Name', 'Surname', 'Amount (UGX)', 'Date'];
    } else {
        if ($selected_member_id !== 'all' && is_numeric($selected_member_id)) {
            $sql = "SELECT u.first_name, u.surname, COALESCE(SUM(s.amount), 0) as total_saved
                    FROM users u
                    LEFT JOIN savings s ON u.id = s.user_id
                    WHERE u.id = ?
                    GROUP BY u.id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$selected_member_id]);
        } else {
            $sql = "SELECT u.first_name, u.surname, COALESCE(SUM(s.amount), 0) as total_saved
                    FROM users u
                    LEFT JOIN savings s ON u.id = s.user_id
                    WHERE u.id != 1
                    GROUP BY u.id
                    ORDER BY total_saved DESC";
            $stmt = $pdo->query($sql);
        }
        $filename = "savings_summary_" . date('Y-m-d') . ".csv";
        $header = ['First Name', 'Surname', 'Total Saved (UGX)'];
    }

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    fputcsv($output, $header);

    foreach ($results as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;

} catch (PDOException $e) {
    die("Error exporting data: " . $e->getMessage());
}
