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

    <!-- Avatar Upload Section -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Update Profile Picture</h3>
        <div id="avatar-success" class="hidden p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg"></div>
        <div id="avatar-error" class="hidden p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg"></div>

        <div class="flex items-center space-x-4">
            <input type="file" id="upload-avatar-input" class="hidden" accept="image/*">
            <button id="upload-avatar-btn" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Upload Photo
            </button>
            <button id="take-photo-btn" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                Take Photo
            </button>
        </div>
    </div>

    <!-- Avatar Cropping Modal -->
    <div id="avatar-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-lg">
            <h3 class="text-xl font-semibold mb-4">Crop Your Photo</h3>
            <div id="camera-container" class="hidden">
                <video id="camera-stream" autoplay class="w-full h-auto"></video>
                <button id="capture-btn" class="mt-4 bg-gray-800 text-white py-2 px-4 rounded">Capture</button>
            </div>
            <div id="cropper-container" class="hidden">
                <img id="image-to-crop" class="max-w-full">
            </div>
            <div class="mt-6 flex justify-end space-x-4">
                <button id="cancel-crop-btn" class="text-gray-600">Cancel</button>
                <button id="confirm-crop-btn" class="bg-blue-600 text-white py-2 px-4 rounded">Confirm & Upload</button>
            </div>
        </div>
    </div>

    <!-- Profile Information Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Update Profile Information</h3>

        <?php if ($profile_success): ?>
            <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                <?php echo htmlspecialchars($profile_success); ?>
            </div>
        <?php endif; ?>
        <?php if ($profile_error): ?>
            <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                <?php echo htmlspecialchars($profile_error); ?>
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
                <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('avatar-modal');
    const uploadBtn = document.getElementById('upload-avatar-btn');
    const uploadInput = document.getElementById('upload-avatar-input');
    const takePhotoBtn = document.getElementById('take-photo-btn');
    const cancelBtn = document.getElementById('cancel-crop-btn');
    const confirmBtn = document.getElementById('confirm-crop-btn');
    const imageToCrop = document.getElementById('image-to-crop');
    const cropperContainer = document.getElementById('cropper-container');
    const cameraContainer = document.getElementById('camera-container');
    const video = document.getElementById('camera-stream');
    const captureBtn = document.getElementById('capture-btn');
    const avatarSuccess = document.getElementById('avatar-success');
    const avatarError = document.getElementById('avatar-error');

    let cropper;
    let stream;

    function showModal() {
        modal.classList.remove('hidden');
    }

    function hideModal() {
        modal.classList.add('hidden');
        if (cropper) {
            cropper.destroy();
        }
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        cameraContainer.classList.add('hidden');
        cropperContainer.classList.add('hidden');
    }

    uploadBtn.addEventListener('click', () => {
        uploadInput.click();
    });

    uploadInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (event) => {
                imageToCrop.src = event.target.result;
                cropperContainer.classList.remove('hidden');
                cameraContainer.classList.add('hidden');
                showModal();
                cropper = new Cropper(imageToCrop, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 0.8
                });
            };
            reader.readAsDataURL(file);
        }
    });

    takePhotoBtn.addEventListener('click', async () => {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: true });
            video.srcObject = stream;
            cameraContainer.classList.remove('hidden');
            cropperContainer.classList.add('hidden');
            showModal();
        } catch (err) {
            avatarError.textContent = 'Could not access the camera. Please ensure you have a camera and have granted permission.';
            avatarError.classList.remove('hidden');
        }
    });

    captureBtn.addEventListener('click', () => {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        imageToCrop.src = canvas.toDataURL('image/jpeg');

        cameraContainer.classList.add('hidden');
        cropperContainer.classList.remove('hidden');

        stream.getTracks().forEach(track => track.stop());

        cropper = new Cropper(imageToCrop, {
            aspectRatio: 1,
            viewMode: 1,
            autoCropArea: 0.8
        });
    });

    cancelBtn.addEventListener('click', hideModal);

    confirmBtn.addEventListener('click', () => {
        if (cropper) {
            cropper.getCroppedCanvas({
                width: 512,
                height: 512,
                imageSmoothingQuality: 'high'
            }).toBlob((blob) => {
                const formData = new FormData();
                formData.append('avatar', blob, 'avatar.jpg');

                fetch('upload_avatar.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        avatarSuccess.textContent = data.message;
                        avatarSuccess.classList.remove('hidden');
                        avatarError.classList.add('hidden');
                        // Optionally, refresh the avatar image on the page
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        avatarError.textContent = data.message;
                        avatarError.classList.remove('hidden');
                        avatarSuccess.classList.add('hidden');
                    }
                })
                .catch(error => {
                    avatarError.textContent = 'An unexpected error occurred.';
                    avatarError.classList.remove('hidden');
                })
                .finally(() => {
                    hideModal();
                });
            }, 'image/jpeg');
        }
    });
});
</script>
