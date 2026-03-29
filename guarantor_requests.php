<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$user_id = $_SESSION['user_id'];
$guarantor_requests = [];

try {
    $stmt = $pdo->prepare(
        "SELECT lg.id, l.amount, u.username as borrower_name, l.requested_at
         FROM loan_guarantors lg
         JOIN loans l ON lg.loan_id = l.id
         JOIN users u ON l.user_id = u.id
         WHERE lg.guarantor_id = ? AND lg.status = 'pending'
         ORDER BY l.requested_at DESC"
    );
    $stmt->execute([$user_id]);
    $guarantor_requests = $stmt->fetchAll();
} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10">
    <h2 class="text-3xl font-bold mb-6 text-gray-800">Guarantor Requests</h2>

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

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Pending Requests for You to Guarantee</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr class="border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        <th class="px-5 py-3">Borrower</th>
                        <th class="px-5 py-3">Loan Amount</th>
                        <th class="px-5 py-3">Date Requested</th>
                        <th class="px-5 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php if (empty($guarantor_requests)): ?>
                        <tr>
                            <td colspan="4" class="px-5 py-5 text-center">You have no pending guarantor requests.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($guarantor_requests as $request): ?>
                            <tr class="border-b border-gray-200">
                                <td class="px-5 py-4"><?php echo htmlspecialchars($request['borrower_name']); ?></td>
                                <td class="px-5 py-4"><?php echo number_format($request['amount'], 2); ?> UGX</td>
                                <td class="px-5 py-4"><?php echo date('M j, Y, g:i a', strtotime($request['requested_at'])); ?></td>
                                <td class="px-5 py-4">
                                    <form action="process_guarantor_action.php" method="POST" class="inline-flex space-x-2">
                                        <input type="hidden" name="guarantor_request_id" value="<?php echo $request['id']; ?>">
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
