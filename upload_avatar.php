<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (in_array($_FILES['avatar']['type'], $allowed_types) && $_FILES['avatar']['size'] <= $max_size) {
        // Use an absolute path for the upload directory
        $upload_dir = __DIR__ . '/assets/uploads/avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        if (empty($file_extension)) {
            // Fallback for blobs that might not have an extension
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
            finfo_close($finfo);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
            $file_extension = $extensions[$mime_type] ?? 'jpg';
        }

        $safe_filename = uniqid('avatar_' . $user_id . '_', true) . '.' . $file_extension;
        $upload_path = $upload_dir . $safe_filename;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
            try {
                // First, get the old avatar path to delete it
                $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $old_avatar = $stmt->fetchColumn();

                // Update the database with the new path
                $update_stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                if ($update_stmt->execute([$upload_path, $user_id])) {
                    // If update is successful, delete the old avatar file (if it exists and is not a default)
                    if ($old_avatar && file_exists($old_avatar) && strpos($old_avatar, 'default') === false) {
                        unlink($old_avatar);
                    }
                    $response = ['success' => true, 'message' => 'Avatar updated successfully!', 'path' => $upload_path];
                } else {
                    $response['message'] = 'Database update failed.';
                }
            } catch (PDOException $e) {
                $response['message'] = 'Database error: ' . $e->getMessage();
            }
        } else {
            $response['message'] = 'Failed to move uploaded file. Check directory permissions.';
        }
    } else {
        $response['message'] = 'Invalid file type or size. Max 5MB, JPG, PNG, GIF allowed.';
    }
} else {
    $response['message'] = 'No file was uploaded or an error occurred during upload.';
}

echo json_encode($response);
?>
