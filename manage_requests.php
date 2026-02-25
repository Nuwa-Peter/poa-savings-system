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

// Fetch pending loan requests along with guarantor status
try {
    $loans_stmt = $pdo->prepare(
        "SELECT
            l.id,
            u.username,
            u.account_no,
            l.amount,
            l.requested_at,
            gu.username as guarantor_name,
            lg.status as guarantor_status,
            lc.description as collateral_desc,
            lc.estimated_value as collateral_value,
            l.secretary_approval,
            l.chairman_approval
         FROM loans l
         JOIN users u ON l.user_id = u.id
         LEFT JOIN loan_guarantors lg ON l.id = lg.loan_id
         LEFT JOIN users gu ON lg.guarantor_id = gu.id
         LEFT JOIN loan_collateral lc ON l.id = lc.loan_id
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
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-8">
        <div class="p-6 border-b border-slate-200 flex flex-wrap items-center justify-between gap-4">
            <h3 class="text-xl font-bold text-slate-800 text-gray-700">Pending Withdrawals</h3>
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <input type="text" id="withdrawal-search" placeholder="Search withdrawals..." class="pl-9 pr-4 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all w-64">
            </div>
        </div>
        <div class="overflow-x-auto p-6">
            <table class="min-w-full leading-normal" data-interactive="true" data-search-input="withdrawal-search" data-pagination="5">
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
                                <td class="px-5 py-4"><?php echo number_format($withdrawal['amount'], 0); ?> UGX</td>
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
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-200 flex flex-wrap items-center justify-between gap-4">
            <h3 class="text-xl font-bold text-slate-800 text-gray-700">Pending Loans</h3>
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <input type="text" id="loan-search" placeholder="Search loans..." class="pl-9 pr-4 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all w-64">
            </div>
        </div>
        <div class="overflow-x-auto p-6">
            <table class="min-w-full leading-normal" data-interactive="true" data-search-input="loan-search" data-pagination="5">
                <thead>
                    <tr class="border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Collateral</th>
                        <th class="px-5 py-3">Guarantor</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php if (empty($pending_loans)): ?>
                        <tr>
                            <td colspan="5" class="px-5 py-5 text-center">No pending loan requests.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pending_loans as $loan): ?>
                            <tr class="border-b border-gray-200">
                                <td class="px-5 py-4">
                                    <p><?php echo htmlspecialchars($loan['username']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($loan['account_no']); ?></p>
                                </td>
                                <td class="px-5 py-4"><?php echo number_format($loan['amount'], 0); ?> UGX</td>
                                <td class="px-5 py-4 text-xs">
                                    <p class="font-semibold"><?php echo htmlspecialchars($loan['collateral_desc'] ?? 'N/A'); ?></p>
                                    <?php if ($loan['collateral_value']): ?>
                                        <p class="text-gray-500">Value: <?php echo number_format($loan['collateral_value'], 0); ?> UGX</p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4"><?php echo htmlspecialchars($loan['guarantor_name'] ?? 'N/A'); ?></td>
                                <td class="px-5 py-4 text-xs space-y-1">
                                    <div class="flex items-center space-x-1">
                                        <span class="w-2 h-2 rounded-full <?php echo $loan['guarantor_status'] === 'approved' ? 'bg-green-500' : 'bg-yellow-500'; ?>"></span>
                                        <span>Guarantor: <?php echo ucfirst(htmlspecialchars($loan['guarantor_status'] ?? 'pending')); ?></span>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <span class="w-2 h-2 rounded-full <?php echo $loan['secretary_approval'] ? 'bg-green-500' : 'bg-yellow-500'; ?>"></span>
                                        <span>Secretary: <?php echo $loan['secretary_approval'] ? 'Approved' : 'Pending'; ?></span>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <span class="w-2 h-2 rounded-full <?php echo $loan['chairman_approval'] ? 'bg-green-500' : 'bg-yellow-500'; ?>"></span>
                                        <span>Chairman: <?php echo $loan['chairman_approval'] ? 'Approved' : 'Pending'; ?></span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <form action="process_request_action.php" method="POST" class="inline-flex items-center space-x-2">
                                        <input type="hidden" name="request_id" value="<?php echo $loan['id']; ?>">
                                        <input type="hidden" name="request_type" value="loan">
                                        <input type="text" inputmode="numeric" data-type="currency" name="approved_amount" class="w-32 text-sm border-gray-300 rounded" placeholder="Amount" value="<?php echo (int)$loan['amount']; ?>">
                                        <button type="submit" name="action" value="approve"
                                            class="text-sm bg-green-500 hover:bg-green-700 text-white py-1 px-3 rounded disabled:bg-gray-400"
                                            <?php echo ($loan['guarantor_status'] !== 'approved') ? 'disabled title="Cannot approve until guarantor approves."' : 'title="Approving this loan will record it as disbursed and reduce the society\'s cash balance."'; ?>>
                                            Approve & Disburse
                                        </button>
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
