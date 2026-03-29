<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$user_id = $_SESSION['user_id'];
$notifications = [];

try {
    // Fetch all notifications for the user, newest first
    $stmt = $pdo->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();

    // Mark all unread notifications as read
    $update_stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $update_stmt->execute([$user_id]);

} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10">
    <h2 class="text-3xl font-bold mb-6 text-gray-800">Notifications</h2>

    <?php if (isset($db_error)): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Database Error!</span> <?php echo htmlspecialchars($db_error); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="space-y-4">
            <?php if (empty($notifications)): ?>
                <p class="text-center text-gray-500">You have no notifications.</p>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="border-b pb-4 <?php echo $notification['is_read'] ? 'text-gray-500' : 'font-semibold text-gray-800'; ?>">
                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                        <p class="text-xs text-gray-400 mt-1">
                            <?php echo date('M j, Y, g:i a', strtotime($notification['created_at'])); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
