<?php
require_once 'includes/auth_check.php';
// Only Secretary (role_id 3), Chairman (role_id 2), and Root (role_id 1) can add users
check_permissions([1, 2, 3]);

require_once 'config/db_connect.php';
require_once 'includes/user_functions.php';
require_once 'templates/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $surname = trim($_POST['surname']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $role_id = $_POST['role_id'];

    if (empty($first_name) || empty($surname) || empty($username) || empty($email) || empty($password) || empty($role_id)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Generate a new account number
            $account_no = generate_account_number($pdo);

            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert the new user into the database
            $sql = "INSERT INTO users (account_no, first_name, surname, username, email, phone, password, role_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$account_no, $first_name, $surname, $username, $email, $phone, $hashed_password, $role_id]);

            // Log the action
            require_once 'includes/logging.php';
            $new_user_id = $pdo->lastInsertId();
            log_action($pdo, $_SESSION['user_id'], "Created new user: $username (ID: $new_user_id)");

            $success = "User created successfully with account number: $account_no";

        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="container mx-auto mt-10">
    <h2 class="text-2xl font-bold mb-5">Add New User</h2>

    <?php if ($success): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">Success!</span> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Error!</span> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="add_user.php" class="bg-white dark:bg-gray-800 p-8 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-1">
                <label for="first_name" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">First Name:</label>
                <input type="text" name="first_name" id="first_name" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-1">
                <label for="surname" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Surname:</label>
                <input type="text" name="surname" id="surname" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-2">
                <label for="username" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Username:</label>
                <input type="text" name="username" id="username" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-2">
                <label for="email" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Email:</label>
                <input type="email" name="email" id="email" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        <div class="mb-4">
            <label for="phone" class="block text-gray-700 text-sm font-bold mb-2">Phone:</label>
            <input type="text" name="phone" id="phone" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>
        <div class="mb-4">
            <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
            <input type="password" name="password" id="password" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>
        <div class="mb-6">
            <label for="role_id" class="block text-gray-700 text-sm font-bold mb-2">Role:</label>
            <select name="role_id" id="role_id" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <option value="5">Member</option>
                <option value="4">Treasurer</option>
                <option value="3">Secretary</option>
                <option value="2">Chairman</option>
            </select>
        </div>
        <div class="flex items-center justify-between">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Add User
            </button>
        </div>
    </form>
</div>

<?php
require_once 'templates/footer.php';
?>
