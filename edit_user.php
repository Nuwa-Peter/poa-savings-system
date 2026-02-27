<?php
require_once 'includes/auth_check.php';
// Only Root (1) and Chairman (2) can access this page
check_permissions([1, 2]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$user_id_to_edit = $_GET['id'] ?? null;
$error = '';
$success = '';
$user = null;

if (!$user_id_to_edit) {
    header('Location: member_directory.php');
    exit;
}

try {
    // Fetch user data (Excluding root and chairman)
    $stmt = $pdo->prepare("SELECT id, first_name, surname, username, email, phone, role_id FROM users WHERE id = ? AND id != 1 AND role_id != 2 AND status = 'active'");
    $stmt->execute([$user_id_to_edit]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User not found.");
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $first_name = trim($_POST['first_name']);
        $surname = trim($_POST['surname']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $role_id = $_POST['role_id'];

        if (empty($first_name) || empty($surname) || empty($username) || empty($email)) {
            $error = "First Name, Surname, Username, and Email are required fields.";
        } else {
            // If the role is not submitted (because it's disabled for the chairman), retain the existing role_id
            if (empty($role_id)) {
                $role_id = $user['role_id'];
            }

            $update_stmt = $pdo->prepare(
                "UPDATE users SET first_name = ?, surname = ?, username = ?, email = ?, phone = ?, role_id = ? WHERE id = ?"
            );
            $update_stmt->execute([$first_name, $surname, $username, $email, $phone, $role_id, $user_id_to_edit]);

            require_once 'includes/logging.php';
            log_action($pdo, $_SESSION['user_id'], "Updated profile for user: {$username} (ID: {$user_id_to_edit})");

            $success = "User profile updated successfully.";
            // Refresh user data to show new values
            $stmt->execute([$user_id_to_edit]);
            $user = $stmt->fetch();
        }
    }

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10 p-4">
    <div class="max-w-2xl mx-auto bg-white dark:bg-gray-800 p-8 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 mb-6 text-center">Edit User Profile</h1>

        <?php if ($error): ?>
            <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 dark:bg-red-900 dark:text-red-200 rounded-lg" role="alert"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($user): ?>
            <form method="POST" action="edit_user.php?id=<?php echo $user_id_to_edit; ?>" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="first_name" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">First Name: <span class="text-red-500">*</span></label>
                        <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="surname" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Surname: <span class="text-red-500">*</span></label>
                        <input type="text" name="surname" id="surname" value="<?php echo htmlspecialchars($user['surname']); ?>" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="username" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Username: <span class="text-red-500">*</span></label>
                        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Email: <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="block w-full px-4 py-2 rounded-md">
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Phone:</label>
                        <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="block w-full px-4 py-2 rounded-md">
                    </div>
                    <div>
                        <label for="role_id" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Role: <span class="text-red-500">*</span></label>
                        <select name="role_id" id="role_id" required class="block w-full px-4 py-2 rounded-md">
                            <option value="5" <?php echo $user['role_id'] == 5 ? 'selected' : ''; ?>>Member</option>
                            <option value="4" <?php echo $user['role_id'] == 4 ? 'selected' : ''; ?>>Treasurer</option>
                            <option value="3" <?php echo $user['role_id'] == 3 ? 'selected' : ''; ?>>Secretary</option>
                            <?php if ($user['role_id'] == 2): // If the user is the current chairman, keep the option visible but disabled ?>
                                <option value="2" selected>Chairman (Cannot be changed here)</option>
                            <?php endif; ?>
                             <?php if ($_SESSION['role_id'] == 1): // Only Root can assign Root ?>
                                <option value="1" <?php echo $user['role_id'] == 1 ? 'selected' : ''; ?>>Root</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="mt-8 flex justify-between items-center">
                    <a href="member_directory.php" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Directory</a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-md">
                        Save Changes
                    </button>
                </div>
            </form>
        <?php else: ?>
            <p>The requested user could not be found. Please return to the directory.</p>
            <a href="member_directory.php" class="text-indigo-600 hover:text-indigo-900">&larr; Back to Directory</a>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
