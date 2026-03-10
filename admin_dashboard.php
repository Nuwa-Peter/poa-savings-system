<?php
require_once 'includes/auth_check.php';
// Only admins can access this page
check_permissions([1, 2, 3, 4]);

require_once 'config/db_connect.php';
require_once 'includes/currency_converter.php';
require_once 'templates/header.php';

$interest_run_this_month = false;
try {
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'last_interest_run'");
    $last_run_timestamp = $stmt->fetchColumn();
    if ($last_run_timestamp && (new DateTime($last_run_timestamp))->format('Y-m') === (new DateTime())->format('Y-m')) {
        $interest_run_this_month = true;
    }
} catch (PDOException $e) {
    // Gracefully handle error, maybe log it, but don't block the dashboard
}

// --- Fetch Aggregate Data ---
$system_stats = [
    'total_savings' => 0,
    'total_loan_balance' => 0,
    'pending_withdrawals' => 0,
    'pending_loans' => 0
];
$admin_personal_stats = [
    'total_savings' => 0,
    'loan_balance' => 0
];
$admin_user_id = $_SESSION['user_id'];

try {
    // 1. System-wide stats (Including all money in the system)
    $system_stats['total_savings'] = $pdo->query("SELECT SUM(amount) FROM savings")->fetchColumn() ?: 0;
    $system_stats['total_loan_balance'] = $pdo->query("SELECT SUM(balance) FROM loans WHERE status = 'approved'")->fetchColumn() ?: 0;

    // 3. Pending Withdrawals Count
    $system_stats['pending_withdrawals'] = $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'")->fetchColumn() ?: 0;

    // 4. Pending Loans Count
    $system_stats['pending_loans'] = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'pending'")->fetchColumn() ?: 0;

    // 2. Admin's personal stats
    $personal_savings_stmt = $pdo->prepare("SELECT SUM(amount) FROM savings WHERE user_id = ?");
    $personal_savings_stmt->execute([$admin_user_id]);
    $admin_personal_stats['total_savings'] = $personal_savings_stmt->fetchColumn() ?: 0;

    $personal_loan_stmt = $pdo->prepare("SELECT SUM(balance) FROM loans WHERE user_id = ? AND status = 'approved'");
    $personal_loan_stmt->execute([$admin_user_id]);
    $admin_personal_stats['loan_balance'] = $personal_loan_stmt->fetchColumn() ?: 0;

    // 5. Data for Society Cumulative Savings Chart (Each deposit)
    $society_savings_stmt = $pdo->query(
        "SELECT amount, created_at FROM savings ORDER BY created_at ASC"
    );
    $society_savings_history = $society_savings_stmt->fetchAll(PDO::FETCH_ASSOC);

    $society_cumulative_labels = [];
    $society_cumulative_values = [];
    $running_total = 0;
    foreach ($society_savings_history as $row) {
        $running_total += $row['amount'];
        $society_cumulative_labels[] = date("M j, Y H:i", strtotime($row['created_at']));
        $society_cumulative_values[] = $running_total;
    }

    // 6. Data for Admin's Personal Savings Trend Chart (Cumulative)
    $personal_trend_stmt = $pdo->prepare(
        "SELECT amount, created_at FROM savings WHERE user_id = ? ORDER BY created_at ASC"
    );
    $personal_trend_stmt->execute([$admin_user_id]);
    $personal_history = $personal_trend_stmt->fetchAll(PDO::FETCH_ASSOC);

    $personal_labels = [];
    $personal_cumulative_values = [];
    $personal_running_total = 0;
    foreach ($personal_history as $row) {
        $personal_running_total += $row['amount'];
        $personal_labels[] = date("M j, Y", strtotime($row['created_at']));
        $personal_cumulative_values[] = $personal_running_total;
    }

    // 7. Member-wise Financial Comparison Data
    $member_comp_stmt = $pdo->query("
        SELECT
            u.first_name,
            u.surname,
            COALESCE((SELECT SUM(amount) FROM savings WHERE user_id = u.id), 0) as total_saved,
            COALESCE((SELECT SUM(balance) FROM loans WHERE user_id = u.id AND status = 'approved'), 0) as loan_balance
        FROM users u
        ORDER BY total_saved DESC
    ");
    $member_comp_data = $member_comp_stmt->fetchAll(PDO::FETCH_ASSOC);

    $member_names = [];
    $member_savings = [];
    $member_loans = [];

    foreach ($member_comp_data as $row) {
        $member_names[] = $row['first_name'] . ' ' . $row['surname'];
        $member_savings[] = (float)$row['total_saved'];
        $member_loans[] = (float)$row['loan_balance'];
    }

} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
}

?>

<div class="container mx-auto mt-10 px-4 lg:px-0">
    <h2 class="text-3xl font-bold mb-6 text-gray-800 hidden lg:block">Administrator Dashboard</h2>

    <!-- Quick Actions (Desktop) -->
    <div class="hidden lg:block mb-8 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Quick Actions</h3>
        <div class="flex flex-wrap gap-3">
            <a href="manage_requests.php" class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-5 rounded-xl transition-all shadow-sm hover:shadow-indigo-200">
                <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                <span>Manage Requests</span>
            </a>
            <a href="add_member.php" class="flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-semibold py-2.5 px-5 rounded-xl transition-all shadow-sm">
                <i data-lucide="user-plus" class="w-4 h-4 text-indigo-600"></i>
                <span>Add Member</span>
            </a>
            <a href="add_saving.php" class="flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-semibold py-2.5 px-5 rounded-xl transition-all shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-600"></i>
                <span>Add Saving</span>
            </a>
            <a href="view_savings.php?view_mode=history" class="flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-semibold py-2.5 px-5 rounded-xl transition-all shadow-sm">
                <i data-lucide="edit-3" class="w-4 h-4 text-orange-600"></i>
                <span>Rectify Savings</span>
            </a>
            <?php if (in_array($_SESSION['role_id'], [1, 2])): ?>
                <a href="apply_interest.php" class="flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-semibold py-2.5 px-5 rounded-xl transition-all shadow-sm">
                    <i data-lucide="percent" class="w-4 h-4 text-orange-600"></i>
                    <span>Apply Interest</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$interest_run_this_month && in_array($_SESSION['role_id'], [1, 2])): ?>
        <div class="p-4 mb-6 text-sm text-yellow-700 bg-yellow-100 rounded-lg shadow-md" role="alert">
            <span class="font-medium">Action Required!</span> The monthly loan interest script has not been run for the current month.
            <a href="apply_interest.php" class="font-bold underline ml-2">Run it now</a>.
        </div>
    <?php endif; ?>

    <?php if (isset($db_error)): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Database Error!</span> <?php echo htmlspecialchars($db_error); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">Success!</span> <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Error!</span> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Mobile Admin Dashboard View -->
    <div id="mobile-admin-dashboard" class="lg:hidden space-y-6 -mt-4 pb-20">
        <!-- Mobile Header -->
        <div class="bg-gradient-to-br from-slate-800 to-slate-900 -mx-4 px-6 pt-10 pb-16 rounded-b-[3rem] shadow-lg relative overflow-hidden">
            <div class="relative z-10 text-white">
                <p class="text-slate-300 text-sm font-medium opacity-80 mb-1">Total System Savings</p>
                <h1 class="text-4xl font-bold tracking-tight mb-4"><?php echo number_format($system_stats['total_savings'], 0); ?> <span class="text-lg font-normal opacity-70">UGX</span></h1>

                <div class="flex gap-4">
                    <div class="bg-white/10 backdrop-blur-md px-4 py-2 rounded-2xl border border-white/10 flex-1">
                        <p class="text-[10px] uppercase font-bold text-slate-300 mb-0.5">Active Loans</p>
                        <p class="text-lg font-bold"><?php echo number_format($system_stats['total_loan_balance'], 0); ?></p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md px-4 py-2 rounded-2xl border border-white/10 flex-1">
                        <p class="text-[10px] uppercase font-bold text-slate-300 mb-0.5">Pending</p>
                        <p class="text-lg font-bold"><?php echo $system_stats['pending_withdrawals'] + $system_stats['pending_loans']; ?></p>
                    </div>
                </div>
            </div>
            <!-- Decorative blobs -->
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl"></div>
        </div>

        <!-- Mobile Quick Actions Grid -->
        <div class="grid grid-cols-4 gap-4 px-2">
            <a href="manage_requests.php" class="flex flex-col items-center gap-2 group">
                <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 shadow-sm border border-indigo-100 group-active:scale-95 transition-transform">
                    <i data-lucide="clipboard-list" class="w-6 h-6"></i>
                </div>
                <span class="text-[11px] font-bold text-slate-600">Requests</span>
            </a>
            <a href="add_member.php" class="flex flex-col items-center gap-2 group">
                <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 shadow-sm border border-blue-100 group-active:scale-95 transition-transform">
                    <i data-lucide="user-plus" class="w-6 h-6"></i>
                </div>
                <span class="text-[11px] font-bold text-slate-600">Add Mem</span>
            </a>
            <a href="add_saving.php" class="flex flex-col items-center gap-2 group">
                <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 shadow-sm border border-emerald-100 group-active:scale-95 transition-transform">
                    <i data-lucide="plus-circle" class="w-6 h-6"></i>
                </div>
                <span class="text-[11px] font-bold text-slate-600">Add Sav</span>
            </a>
            <a href="view_savings.php?view_mode=history" class="flex flex-col items-center gap-2 group">
                <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 shadow-sm border border-orange-100 group-active:scale-95 transition-transform">
                    <i data-lucide="edit-3" class="w-6 h-6"></i>
                </div>
                <span class="text-[11px] font-bold text-slate-600">Rectify</span>
            </a>
        </div>

        <!-- System Liquidity Mini Chart -->
        <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 mx-1">
            <h3 class="text-sm font-bold text-slate-800 mb-4 flex justify-between items-center">
                <span>System Liquidity</span>
                <i data-lucide="pie-chart" class="w-4 h-4 text-slate-400"></i>
            </h3>
            <div class="h-40 flex justify-center">
                <canvas id="mobileLiquidityChart"></canvas>
            </div>
        </div>

        <!-- Mobile Recent Activity -->
        <div class="space-y-3 px-1">
            <h3 class="text-sm font-bold text-slate-800 px-1">Recent Disbursements</h3>
            <?php
            try {
                $md_stmt = $pdo->query("SELECT l.amount, l.approved_at, u.first_name, u.surname FROM loans l JOIN users u ON l.user_id = u.id WHERE l.status = 'approved' ORDER BY l.approved_at DESC LIMIT 3");
                $md_recent = $md_stmt->fetchAll();
                foreach ($md_recent as $d):
            ?>
                <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between group active:bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                            <i data-lucide="banknote" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($d['first_name'] . ' ' . $d['surname']); ?></p>
                            <p class="text-[10px] text-slate-400 font-medium"><?php echo date('d M Y', strtotime($d['approved_at'])); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-blue-600"><?php echo number_format($d['amount'], 0); ?></p>
                    </div>
                </div>
            <?php endforeach; } catch (Exception $e) {} ?>

            <h3 class="text-sm font-bold text-slate-800 px-1 pt-2">Recent Savings</h3>
            <?php
            // Fetch recent savings again for mobile to ensure we have them if the block above didn't run (it should have, but being explicit)
            try {
                $m_stmt = $pdo->query("SELECT s.id, s.amount, s.created_at, u.first_name, u.surname FROM savings s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC LIMIT 5");
                $m_recent = $m_stmt->fetchAll();
                foreach ($m_recent as $saving):
            ?>
                <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between group active:bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <i data-lucide="arrow-down-left" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($saving['first_name'] . ' ' . $saving['surname']); ?></p>
                            <p class="text-[10px] text-slate-400 font-medium"><?php echo date('d M Y', strtotime($saving['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-emerald-600">+<?php echo number_format($saving['amount'], 0); ?></p>
                        <a href="edit_saving.php?id=<?php echo $saving['id']; ?>" class="text-[10px] font-bold text-indigo-600 uppercase">Edit</a>
                    </div>
                </div>
            <?php endforeach; } catch (Exception $e) {} ?>
            <a href="view_savings.php?view_mode=history" class="block text-center py-3 text-sm font-bold text-slate-600 bg-slate-50 rounded-2xl">
                View All Records
            </a>
        </div>
    </div>

    <!-- Desktop Stats Cards -->
    <div class="hidden lg:grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                    <i data-lucide="piggy-bank" class="w-6 h-6"></i>
                </div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Savings</span>
            </div>
            <p class="text-3xl font-bold text-slate-900"><?php echo number_format($system_stats['total_savings'], 0); ?> <span class="text-sm font-normal text-slate-500">UGX</span></p>
            <p class="text-xs text-slate-500 mt-1">~ $<?php echo number_format(convert_ugx_to_usd($system_stats['total_savings']), 2); ?> USD</p>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-rose-50 rounded-lg text-rose-600">
                    <i data-lucide="landmark" class="w-6 h-6"></i>
                </div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Outstanding Loans</span>
            </div>
            <p class="text-3xl font-bold text-slate-900"><?php echo number_format($system_stats['total_loan_balance'], 0); ?> <span class="text-sm font-normal text-slate-500">UGX</span></p>
            <p class="text-xs text-slate-500 mt-1">~ $<?php echo number_format(convert_ugx_to_usd($system_stats['total_loan_balance']), 2); ?> USD</p>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-amber-50 rounded-lg text-amber-600">
                    <i data-lucide="arrow-up-right" class="w-6 h-6"></i>
                </div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Withdrawals</span>
            </div>
            <p class="text-3xl font-bold text-slate-900"><?php echo $system_stats['pending_withdrawals']; ?></p>
            <p class="text-xs text-slate-500 mt-1">Pending approval</p>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                    <i data-lucide="clock" class="w-6 h-6"></i>
                </div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Loan Apps</span>
            </div>
            <p class="text-3xl font-bold text-slate-900"><?php echo $system_stats['pending_loans']; ?></p>
            <p class="text-xs text-slate-500 mt-1">Pending review</p>
        </div>
    </div>

    <!-- Main Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
        <!-- Personal Savings Trend Chart -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-xl font-bold text-slate-800 mb-4">My Personal Savings</h3>
            <canvas id="personalSavingsChart"></canvas>
        </div>

        <!-- Society Savings Trend Chart (Cumulative) -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-xl font-bold text-slate-800 mb-4">Society Savings Trend</h3>
            <canvas id="societyCumulativeChart"></canvas>
        </div>
    </div>

    <!-- Secondary Analytics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-bold text-slate-800 mb-4 text-center">Debt vs Savings</h3>
            <div class="h-80 flex justify-center">
                <canvas id="liquidityChart"></canvas>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-bold text-slate-800 mb-4 text-center">Member Savings vs Loans</h3>
            <div class="h-80">
                <canvas id="memberComparisonChart"></canvas>
            </div>
        </div>
    </div>


    <!-- Recent Loan Disbursements -->
    <div class="hidden lg:block mt-8 bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Recent Loan Disbursements (Cash Given)</h3>
        <div class="overflow-auto max-h-96">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Member</th>
                        <th class="py-3 px-4 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount (UGX)</th>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Disbursed At</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm">
                    <?php
                    try {
                        $disbursements_stmt = $pdo->query("SELECT l.amount, l.approved_at, u.first_name, u.surname FROM loans l JOIN users u ON l.user_id = u.id WHERE l.status = 'approved' ORDER BY l.approved_at DESC LIMIT 5");
                        $disbursements = $disbursements_stmt->fetchAll();
                        if (count($disbursements) > 0):
                            foreach ($disbursements as $d):
                    ?>
                                <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                    <td class="py-3 px-4 font-medium text-slate-700"><?php echo htmlspecialchars($d['first_name'] . ' ' . $d['surname']); ?></td>
                                    <td class="py-3 px-4 text-right font-semibold text-emerald-600"><?php echo number_format($d['amount'], 0); ?></td>
                                    <td class="py-3 px-4 text-slate-500"><?php echo date('d M Y, H:i', strtotime($d['approved_at'])); ?></td>
                                </tr>
                    <?php
                            endforeach;
                        else:
                            echo '<tr><td colspan="3" class="py-4 text-center text-gray-500">No disbursements yet.</td></tr>';
                        endif;
                    } catch (PDOException $e) {
                        echo '<tr><td colspan="3" class="py-4 text-center text-red-500">Error fetching disbursements.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Savings Transactions -->
    <div class="hidden lg:block mt-8 bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Recent Savings Transactions</h3>
        <div class="overflow-auto max-h-96">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Member</th>
                        <th class="py-3 px-4 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount (UGX)</th>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm">
                    <?php
                    $recent_savings = [];
                    try {
                        $stmt = $pdo->query("SELECT s.id, s.amount, s.created_at, u.first_name, u.surname FROM savings s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC LIMIT 10");
                        $recent_savings = $stmt->fetchAll();
                    } catch (PDOException $e) {
                        echo '<tr><td colspan="4" class="py-4 text-center text-red-500">Could not fetch savings.</td></tr>';
                    }

                    if (count($recent_savings) > 0):
                        foreach ($recent_savings as $saving):
                    ?>
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                <td class="py-3 px-4 font-medium text-slate-700"><?php echo htmlspecialchars($saving['first_name'] . ' ' . $saving['surname']); ?></td>
                                <td class="py-3 px-4 text-right font-semibold text-slate-900"><?php echo number_format($saving['amount'], 0); ?></td>
                                <td class="py-3 px-4 text-slate-500"><?php echo date('d M Y', strtotime($saving['created_at'])); ?></td>
                                <td class="py-3 px-4 flex items-center space-x-4">
                                    <a href="edit_saving.php?id=<?php echo $saving['id']; ?>" class="text-indigo-600 hover:text-indigo-900 inline-flex items-center gap-1">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        <span>Rectify</span>
                                    </a>
                                    <a href="delete_saving.php?id=<?php echo $saving['id']; ?>" class="text-rose-600 hover:text-rose-900 inline-flex items-center gap-1" onclick="return confirm('Are you sure?');">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        <span>Delete</span>
                                    </a>
                                </td>
                            </tr>
                    <?php
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="4" class="py-10">
                                <?php echo renderEmptyState('list', 'No Recent Savings', 'Recent savings transactions will appear here.', 'Add a Saving', 'add_saving.php'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Admin's Personal Account View -->
    <div class="hidden lg:block mt-8 bg-gray-50 p-6 rounded-lg shadow-inner border">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">My Personal Account</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h4 class="text-lg font-semibold text-gray-700 mb-2">My Total Savings</h4>
                <p class="text-3xl font-bold text-indigo-600"><?php echo number_format($admin_personal_stats['total_savings'], 0); ?> <span class="text-xl">UGX</span></p>
                <p class="text-md text-gray-500 mt-2">~ $<?php echo number_format(convert_ugx_to_usd($admin_personal_stats['total_savings']), 2); ?> USD</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h4 class="text-lg font-semibold text-gray-700 mb-2">My Loan Balance</h4>
                <p class="text-3xl font-bold text-red-600"><?php echo number_format($admin_personal_stats['loan_balance'], 0); ?> <span class="text-xl">UGX</span></p>
                <p class="text-md text-gray-500 mt-2">~ $<?php echo number_format(convert_ugx_to_usd($admin_personal_stats['loan_balance']), 2); ?> USD</p>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const societyCtx = document.getElementById('societyCumulativeChart').getContext('2d');
    new Chart(societyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($society_cumulative_labels ?? []); ?>,
            datasets: [{
                label: 'Cumulative Society Savings',
                data: <?php echo json_encode($society_cumulative_values ?? []); ?>,
                borderColor: 'rgba(79, 70, 229, 1)',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                fill: true,
                tension: 0.1,
                pointRadius: 2
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'UGX ' + value.toLocaleString(); }
                    }
                },
                x: {
                    display: false // Hide X axis for cleaner look if many points
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Cumulative: UGX ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    const personalCtx = document.getElementById('personalSavingsChart').getContext('2d');
    new Chart(personalCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($personal_labels ?? []); ?>,
            datasets: [{
                label: 'My Balance',
                data: <?php echo json_encode($personal_cumulative_values ?? []); ?>,
                borderColor: 'rgba(16, 185, 129, 1)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'UGX ' + value.toLocaleString(); }
                    }
                }
            }
        }
    });

    const liqCtx = document.getElementById('liquidityChart').getContext('2d');
    if (liqCtx) {
        new Chart(liqCtx, {
            type: 'doughnut',
            data: {
                labels: ['Total Savings', 'Outstanding Loans'],
                datasets: [{
                    data: [<?php echo $system_stats['total_savings']; ?>, <?php echo $system_stats['total_loan_balance']; ?>],
                    backgroundColor: ['rgba(79, 70, 229, 0.8)', 'rgba(244, 63, 94, 0.8)'],
                    hoverOffset: 15,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': UGX ' + context.parsed.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    const mobileLiqCtx = document.getElementById('mobileLiquidityChart').getContext('2d');
    if (mobileLiqCtx) {
        new Chart(mobileLiqCtx, {
            type: 'doughnut',
            data: {
                labels: ['Savings', 'Loans'],
                datasets: [{
                    data: [<?php echo $system_stats['total_savings']; ?>, <?php echo $system_stats['total_loan_balance']; ?>],
                    backgroundColor: ['rgba(79, 70, 229, 0.8)', 'rgba(244, 63, 94, 0.8)'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    const memberCompCtx = document.getElementById('memberComparisonChart').getContext('2d');
    if (memberCompCtx) {
        new Chart(memberCompCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($member_names); ?>,
                datasets: [
                    {
                        label: 'Total Savings',
                        data: <?php echo json_encode($member_savings); ?>,
                        backgroundColor: 'rgba(79, 70, 229, 0.7)',
                        borderColor: 'rgba(79, 70, 229, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Loan Balance',
                        data: <?php echo json_encode($member_loans); ?>,
                        backgroundColor: 'rgba(244, 63, 94, 0.7)',
                        borderColor: 'rgba(244, 63, 94, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return value.toLocaleString(); }
                        }
                    }
                },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': UGX ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>
