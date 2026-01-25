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
    <h2 class="text-2xl font-bold mb-5">Add New Member</h2>

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

    <form method="POST" action="add_member.php" class="bg-white dark:bg-gray-800 p-8 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-1">
                <label for="first_name" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">First Name: <span class="text-red-500">*</span></label>
                <input type="text" name="first_name" id="first_name" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-1">
                <label for="surname" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Surname: <span class="text-red-500">*</span></label>
                <input type="text" name="surname" id="surname" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-2">
                <label for="username" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Username: <span class="text-red-500">*</span></label>
                <input type="text" name="username" id="username" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-2">
                <label for="email" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Email: <span class="text-red-500">*</span></label>
                <input type="email" name="email" id="email" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-2">
                <label for="phone" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Phone:</label>
                <input type="text" name="phone" id="phone" class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-2">
                <label for="password" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Password: <span class="text-red-500">*</span></label>
                <input type="password" name="password" id="password" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-2">
                <label for="role_id" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Role: <span class="text-red-500">*</span></label>
                <select name="role_id" id="role_id" required class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 focus:border-blue-500 dark:focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="5">Member</option>
                    <option value="4">Treasurer</option>
                    <option value="3">Secretary</option>
                    <option value="2">Chairman</option>
                </select>
            </div>
        </div>
        <div class="mt-8 flex justify-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-md focus:outline-none focus:shadow-outline transition duration-150 ease-in-out">
                Add Member
            </button>
        </div>
    </form>
</div>

<?php
require_once 'templates/footer.php';
?>
