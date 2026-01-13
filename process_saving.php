<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'config/db_connect.php';
require_once 'includes/logging.php'; // Assuming logging is ready

// Only Secretary (role_id 3), Chairman (role_id 2), and Root (role_id 1) can process savings
check_permissions([1, 2, 3]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $amount = $_POST['amount'];
    $verifier_id = $_SESSION['user_id'];

    // --- File Upload Handling ---
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] == 0) {
        $target_dir = "uploads/proofs/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $file_name = uniqid() . '-' . basename($_FILES["proof_image"]["name"]);
        $target_file = $target_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if image file is a actual image or fake image
        $check = getimagesize($_FILES["proof_image"]["tmp_name"]);
        if($check === false) {
            header('Location: add_saving.php?error=File is not an image.');
            exit;
        }

        // Check file size (e.g., 5MB limit)
        if ($_FILES["proof_image"]["size"] > 5000000) {
            header('Location: add_saving.php?error=Sorry, your file is too large.');
            exit;
        }

        // Allow certain file formats
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
            header('Location: add_saving.php?error=Sorry, only JPG, JPEG, PNG & GIF files are allowed.');
            exit;
        }

        // Try to upload file
        if (move_uploaded_file($_FILES["proof_image"]["tmp_name"], $target_file)) {
            // --- Database Insertion ---
            try {
                $pdo->beginTransaction();

                // Insert into savings table
                $stmt = $pdo->prepare('INSERT INTO savings (user_id, amount, proof_image_path, verified_by_user_id) VALUES (?, ?, ?, ?)');
                $stmt->execute([$user_id, $amount, $target_file, $verifier_id]);

                // Insert into notifications table
                $message = "Your account has been credited with $" . number_format($amount, 2);
                $notify_stmt = $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
                $notify_stmt->execute([$user_id, $message]);

                // Log the action
                log_action($pdo, $verifier_id, "Added a saving of $amount for user ID $user_id.");

                $pdo->commit();

                header('Location: add_saving.php?success=1');
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                // Optionally delete the uploaded file if DB insertion fails
                if (file_exists($target_file)) {
                    unlink($target_file);
                }
                header('Location: add_saving.php?error=Database error: ' . urlencode($e->getMessage()));
                exit;
            }
        } else {
            header('Location: add_saving.php?error=Sorry, there was an error uploading your file.');
            exit;
        }
    } else {
        header('Location: add_saving.php?error=No file uploaded or an error occurred during upload.');
        exit;
    }
} else {
    // Not a POST request
    header('Location: add_saving.php');
    exit;
}
