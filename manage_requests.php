<?php
require_once 'includes/auth_check.php';
// Only Root (1), Chairman (2), and Secretary (3) can manage requests.
check_permissions([1, 2, 3]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

// Fetch pending withdrawal requests
try {
    $withdrawals_stmt = $pdo->prepare(
        "SELECT w.id, u.username, u.account_no, w.amount, w.requested_at
         FROM withdrawals w
         JOIN users u ON w.user_id = u.id
         WHERE w.status = 'pending'
         ORDER BY w.requested_at ASC"
    );
    $withdrawals_stmt->execute();
    $pending_withdrawals = $withdrawals_stmt->fetchAll();
} catch (PDOException $e) {
    $db_error = "Error fetching withdrawals: " . $e->getMessage();
}

// Fetch pending loan requests
try {
    $loans_stmt = $pdo->prepare(
        "SELECT l.id, u.username, u.account_no, l.amount, l.requested_at
         FROM loans l
         JOIN users u ON l.user_id = u.id
         WHERE l.status = 'pending'
         ORDER BY l.requested_at ASC"
    );
    $loans_stmt->execute();
    $pending_loans = $loans_stmt->fetchAll();
} catch (PDOException $e) {
    $db_error = isset($db_error) ? $db_error . "; " : "";
    $db_error .= "Error fetching loans: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10">
    <h2 class="text-3xl font-bold mb-6 text-gray-800">Manage Pending Requests</h2>

    <?php if (isset($_GET['success'])): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">Success!</span> <?php echo htmlspecialchars(urldecode($_GET['success'])); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Error!</span> <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($db_error)): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Database Error!</span> <?php echo htmlspecialchars($db_error); ?>
        </div>
    <?php endif; ?>

    <!-- Pending Withdrawals Section -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Pending Withdrawals</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr class="border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Date Requested</th>
                        <th class="px-5 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php if (empty($pending_withdrawals)): ?>
                        <tr>
                            <td colspan="4" class="px-5 py-5 text-center">No pending withdrawal requests.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pending_withdrawals as $withdrawal): ?>
                            <tr class="border-b border-gray-200">
                                <td class="px-5 py-4"><?php echo htmlspecialchars($withdrawal['username'] . ' (' . $withdrawal['account_no'] . ')'); ?></td>
                                <td class="px-5 py-4"><?php echo number_format($withdrawal['amount'], 2); ?> UGX</td>
                                <td class="px-5 py-4"><?php echo date('M j, Y, g:i a', strtotime($withdrawal['requested_at'])); ?></td>
                                <td class="px-5 py-4">
                                    <form action="process_request_action.php" method="POST" class="inline-flex space-x-2">
                                        <input type="hidden" name="request_id" value="<?php echo $withdrawal['id']; ?>">
                                        <input type="hidden" name="request_type" value="withdrawal">
                                        <button type="submit" name="action" value="approve" class="text-sm bg-green-500 hover:bg-green-700 text-white py-1 px-3 rounded">Approve</button>
                                        <button type="submit" name="action" value="reject" class="text-sm bg-red-500 hover:bg-red-700 text-white py-1 px-3 rounded">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pending Loans Section -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Pending Loans</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr class="border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Date Requested</th>
                        <th class="px-5 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php if (empty($pending_loans)): ?>
                        <tr>
                            <td colspan="4" class="px-5 py-5 text-center">No pending loan requests.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pending_loans as $loan): ?>
                            <tr class="border-b border-gray-200">
                                <td class="px-5 py-4"><?php echo htmlspecialchars($loan['username'] . ' (' . $loan['account_no'] . ')'); ?></td>
                                <td class="px-5 py-4"><?php echo number_format($loan['amount'], 2); ?> UGX</td>
                                <td class="px-5 py-4"><?php echo date('M j, Y, g:i a', strtotime($loan['requested_at'])); ?></td>
                                <td class="px-5 py-4">
                                    <form action="process_request_action.php" method="POST" class="inline-flex space-x-2">
                                        <input type="hidden" name="request_id" value="<?php echo $loan['id']; ?>">
                                        <input type="hidden" name="request_type" value="loan">
                                        <button type="submit" name="action" value="approve" class="text-sm bg-green-500 hover:bg-green-700 text-white py-1 px-3 rounded">Approve</button>
                                        <button type="submit" name="action" value="reject" class="text-sm bg-red-500 hover:bg-red-700 text-white py-1 px-3 rounded">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
