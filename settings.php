<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]); // All logged-in users can access settings

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$user_id = $_SESSION['user_id'];
$profile_success = '';
$profile_error = '';
$password_success = '';
$password_error = '';
$avatar_success = '';
$avatar_error = '';

// Handle Avatar Upload
if (isset($_POST['update_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $allowed_types = ['image/png', 'image/jpeg', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (in_array($_FILES['avatar']['type'], $allowed_types) && $_FILES['avatar']['size'] <= $max_size) {
            // Sanitize and create a unique filename
            $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $safe_filename = uniqid('avatar_', true) . '.' . $file_extension;
            $upload_path = 'assets/uploads/avatars/' . $safe_filename;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    if ($stmt->execute([$upload_path, $user_id])) {
                        $avatar_success = "Avatar updated successfully.";
                    } else {
                        $avatar_error = "Database update failed. Please try again.";
                    }
                } catch (PDOException $e) {
                    $avatar_error = "Database error: " . $e->getMessage();
                }
            } else {
                $avatar_error = "Failed to move uploaded file.";
            }
        } else {
            $avatar_error = "Invalid file type or size. Max 2MB, PNG, JPG, GIF allowed.";
        }
    } else {
        $avatar_error = "Please select a file to upload.";
    }
}

// Handle Profile Information Update
if (isset($_POST['update_profile'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    if (empty($username) || empty($email)) {
        $profile_error = "Username and Email cannot be empty.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$username, $email, $user_id]);
            if ($stmt->fetch()) {
                $profile_error = "Username or Email is already in use by another account.";
            } else {
                $update_stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ? WHERE id = ?");
                if ($update_stmt->execute([$username, $email, $phone, $user_id])) {
                    $profile_success = "Your profile has been updated successfully.";
                    $_SESSION['username'] = $username;
                } else {
                    $profile_error = "Failed to update profile. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $profile_error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle Password Change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "New password and confirmation do not match.";
    } else {
        try {
            // Fetch the current user's hashed password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_password_hash = $stmt->fetchColumn();

            // Verify the current password
            if (password_verify($current_password, $user_password_hash)) {
                // Hash the new password
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Update the password in the database
                $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($update_stmt->execute([$new_hashed_password, $user_id])) {
                    $password_success = "Password changed successfully.";
                } else {
                    $password_error = "Failed to update password. Please try again.";
                }
            } else {
                $password_error = "Incorrect current password.";
            }
        } catch (PDOException $e) {
            $password_error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch current user data to populate the form
try {
    $stmt = $pdo->prepare("SELECT username, email, phone FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        // This should not happen for a logged-in user
        die("Could not find user data.");
    }
} catch (PDOException $e) {
    die("Database error: Could not fetch user data.");
}
?>

<div class="max-w-4xl mx-auto">
    <h2 class="text-3xl font-bold mb-6 text-gray-800">Settings</h2>

    <!-- Avatar Upload Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Update Profile Picture</h3>

        <?php if ($avatar_success): ?>
            <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                <?php echo htmlspecialchars($avatar_success); ?>
            </div>
        <?php endif; ?>
        <?php if ($avatar_error): ?>
            <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                <?php echo htmlspecialchars($avatar_error); ?>
            </div>
        <?php endif; ?>

        <form action="settings.php" method="POST" enctype="multipart/form-data">
            <div class="mb-4">
                <label for="avatar" class="block text-gray-700 text-sm font-bold mb-2">Choose a new photo:</label>
                <input type="file" name="avatar" id="avatar" accept="image/png, image/jpeg, image/gif" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="flex items-center justify-end">
                <button type="submit" name="update_avatar" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Upload Photo
                </button>
            </div>
        </form>
    </div>

    <!-- Profile Information Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Update Profile Information</h3>

        <?php if ($success_message): ?>
            <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form action="settings.php" method="POST">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-6">
                <label for="phone" class="block text-gray-700 text-sm font-bold mb-2">Phone:</label>
                <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="flex items-center justify-end">
                <button type="submit" name="update_profile" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update Profile
                </button>
            </div>
        </form>
    </div>

    <!-- Change Password Form -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Change Password</h3>

        <?php if ($password_success): ?>
            <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                <?php echo htmlspecialchars($password_success); ?>
            </div>
        <?php endif; ?>
        <?php if ($password_error): ?>
            <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                <?php echo htmlspecialchars($password_error); ?>
            </div>
        <?php endif; ?>

        <form action="settings.php" method="POST">
            <div class="mb-4">
                <label for="current_password" class="block text-gray-700 text-sm font-bold mb-2">Current Password:</label>
                <input type="password" name="current_password" id="current_password" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-4">
                <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">New Password:</label>
                <input type="password" name="new_password" id="new_password" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-6">
                <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password:</label>
                <input type="password" name="confirm_password" id="confirm_password" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="flex items-center justify-end">
                <button type="submit" name="change_password" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Change Password
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
