<?php
require_once 'includes/auth_check.php';
// Only admins with reporting privileges can access this page
check_permissions([1, 2, 4]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

// --- Fetch Data for Reports ---
$loan_performance_data = [];
try {
    // 1. Loan Performance Report (Excluding root and chairman)
    $loan_stmt = $pdo->query(
        "SELECT
            l.status,
            COUNT(l.id) as count,
            SUM(l.amount) as total_amount,
            SUM(l.balance) as total_balance
         FROM loans l
         JOIN users u ON l.user_id = u.id
         WHERE u.id != 1 AND u.role_id != 2
         GROUP BY l.status"
    );
    $loan_performance_data = $loan_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Savings Growth Report (Excluding root and chairman)
    $savings_growth_stmt = $pdo->query(
        "SELECT
            DATE_FORMAT(s.created_at, '%Y-%m') as month,
            SUM(s.amount) as total_savings
         FROM savings s
         JOIN users u ON s.user_id = u.id
         WHERE u.id != 1 AND u.role_id != 2
         GROUP BY month
         ORDER BY month ASC"
    );
    $savings_growth_data = $savings_growth_stmt->fetchAll(PDO::FETCH_ASSOC);

    $savings_labels = [];
    $savings_values = [];
    foreach ($savings_growth_data as $row) {
        $savings_labels[] = date("M Y", strtotime($row['month'] . "-01"));
        $savings_values[] = $row['total_savings'];
    }

    // 3. Member Activity Report
    $member_activity_stmt = $pdo->query(
        "SELECT
            u.first_name,
            u.surname,
            u.account_no,
            COALESCE(SUM(s.amount), 0) as total_saved,
            COUNT(s.id) as savings_frequency,
            COUNT(DISTINCT l.id) as loans_taken
         FROM users u
         LEFT JOIN savings s ON u.id = s.user_id
         LEFT JOIN loans l ON u.id = l.user_id
         WHERE u.role_id = 5 AND u.id != 1 AND u.role_id != 2
         GROUP BY u.id
         ORDER BY total_saved DESC, savings_frequency DESC"
    );
    $member_activity_data = $member_activity_stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
}
?>

<div class="container mx-auto mt-10">
    <h2 class="text-3xl font-bold mb-6 text-gray-800">System Reports</h2>

    <?php if (isset($db_error)): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Database Error!</span> <?php echo htmlspecialchars($db_error); ?>
        </div>
    <?php endif; ?>

    <!-- Loan Performance Report -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Loan Performance Overview</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr class="border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-center">Number of Loans</th>
                        <th class="px-5 py-3 text-right">Total Amount Disbursed</th>
                        <th class="px-5 py-3 text-right">Total Outstanding Balance</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php if (empty($loan_performance_data)): ?>
                        <tr><td colspan="4" class="px-5 py-5 text-center">No loan data available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($loan_performance_data as $row): ?>
                            <tr class="border-b border-gray-200">
                                <td class="px-5 py-4 capitalize font-semibold"><?php echo htmlspecialchars($row['status']); ?></td>
                                <td class="px-5 py-4 text-center"><?php echo $row['count']; ?></td>
                                <td class="px-5 py-4 text-right"><?php echo number_format($row['total_amount'], 0); ?> UGX</td>
                                <td class="px-5 py-4 text-right"><?php echo number_format($row['total_balance'], 0); ?> UGX</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Savings Growth Report -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Monthly Savings Growth</h3>
        <div>
            <canvas id="savingsGrowthChart"></canvas>
        </div>
    </div>

    <!-- Good Savers Report -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Good Savers Report</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr class="border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        <th class="px-5 py-3">Rank</th>
                        <th class="px-5 py-3">Member</th>
                        <th class="px-5 py-3 text-right">Total Savings (UGX)</th>
                        <th class="px-5 py-3 text-center">Savings Frequency</th>
                        <th class="px-5 py-3 text-center">Loans Taken</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php if (empty($member_activity_data)): ?>
                        <tr><td colspan="5" class="px-5 py-5 text-center">No member activity data available.</td></tr>
                    <?php else: ?>
                        <?php $rank = 1; ?>
                        <?php foreach ($member_activity_data as $row): ?>
                            <tr class="border-b border-gray-200">
                                <td class="px-5 py-4 text-center"><?php echo $rank++; ?></td>
                                <td class="px-5 py-4">
                                    <p><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['surname']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($row['account_no']); ?></p>
                                </td>
                                <td class="px-5 py-4 text-right"><?php echo number_format($row['total_saved'], 0); ?></td>
                                <td class="px-5 py-4 text-center"><?php echo $row['savings_frequency']; ?></td>
                                <td class="px-5 py-4 text-center"><?php echo $row['loans_taken']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('savingsGrowthChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($savings_labels); ?>,
            datasets: [{
                label: 'Total Savings per Month',
                data: <?php echo json_encode($savings_values); ?>,
                borderColor: 'rgba(79, 70, 229, 1)',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
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
});
</script>

<?php
require_once 'templates/footer.php';
?>
