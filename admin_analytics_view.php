<?php
require_once 'includes/auth_check.php';
// Only Root (1) and Chairman (2) can access this page
check_permissions([1, 2]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

try {
    // --- Data for Membership Growth Chart ---
    $member_growth_stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(id) as new_members
         FROM users
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY month
         ORDER BY month ASC"
    );
    $member_growth_stmt->execute();
    $member_growth_data = $member_growth_stmt->fetchAll(PDO::FETCH_ASSOC);

    $growth_labels = json_encode(array_column($member_growth_data, 'month'));
    $growth_values = json_encode(array_column($member_growth_data, 'new_members'));


    // --- Data for Financial Summary Chart ---
    $total_savings_stmt = $pdo->query("SELECT SUM(amount) FROM savings");
    $total_savings = $total_savings_stmt->fetchColumn() ?: 0;

    $total_loans_disbursed_stmt = $pdo->query("SELECT SUM(amount) FROM loans WHERE status = 'approved'");
    $total_loans_disbursed = $total_loans_disbursed_stmt->fetchColumn() ?: 0;

    $total_loan_balance_stmt = $pdo->query("SELECT SUM(balance) FROM loans WHERE status = 'approved'");
    $total_loan_balance = $total_loan_balance_stmt->fetchColumn() ?: 0;

    $financial_summary_data = json_encode([$total_savings, $total_loans_disbursed, $total_loan_balance]);

} catch (PDOException $e) {
    $error = "Failed to load analytics data.";
}
?>

<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold mb-6" style="color: var(--text-primary);">System Analytics</h1>

    <?php if (isset($error)): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert">
            <span class="font-medium">Error!</span> <?php echo $error; ?>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Membership Growth Chart -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold text-gray-700 dark:text-white mb-4">Membership Growth (Last 6 Months)</h2>
                <canvas id="membershipGrowthChart"></canvas>
            </div>

            <!-- Financial Summary Chart -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold text-gray-700 dark:text-white mb-4">Financial Summary (UGX)</h2>
                <canvas id="financialSummaryChart"></canvas>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Membership Growth Chart
    const growthCtx = document.getElementById('membershipGrowthChart').getContext('2d');
    new Chart(growthCtx, {
        type: 'line',
        data: {
            labels: <?php echo $growth_labels; ?>,
            datasets: [{
                label: 'New Members per Month',
                data: <?php echo $growth_values; ?>,
                borderColor: 'rgba(59, 130, 246, 1)',
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Financial Summary Chart
    const financialCtx = document.getElementById('financialSummaryChart').getContext('2d');
    new Chart(financialCtx, {
        type: 'bar',
        data: {
            labels: ['Total Savings', 'Total Loans Disbursed', 'Outstanding Loan Balance'],
            datasets: [{
                label: 'Amount in UGX',
                data: <?php echo $financial_summary_data; ?>,
                backgroundColor: [
                    'rgba(16, 185, 129, 0.6)',
                    'rgba(239, 68, 68, 0.6)',
                    'rgba(245, 158, 11, 0.6)'
                ],
                borderColor: [
                    'rgba(16, 185, 129, 1)',
                    'rgba(239, 68, 68, 1)',
                    'rgba(245, 158, 11, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'UGX ' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>

<?php
require_once 'templates/footer.php';
?>
