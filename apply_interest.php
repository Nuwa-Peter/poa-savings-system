<?php
require_once 'includes/auth_check.php';
// Only Root (1) and Chairman (2) can apply interest.
check_permissions([1, 2]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';

$processed_loans = 0;
$total_interest_applied = 0;
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Find all approved, unpaid loans where at least a month has passed since the last interest application.
        $stmt = $pdo->prepare(
            "SELECT id, balance, interest_rate, last_interest_applied_at FROM loans
             WHERE status = 'approved'
             AND last_interest_applied_at <= NOW() - INTERVAL 1 MONTH"
        );
        $stmt->execute();
        $loans_to_update = $stmt->fetchAll();

        if (empty($loans_to_update)) {
            $success_message = "No loans required an interest update at this time.";
        } else {
            $update_stmt = $pdo->prepare(
                "UPDATE loans SET balance = ?, last_interest_applied_at = NOW() WHERE id = ?"
            );

            foreach ($loans_to_update as $loan) {
                // Calculate how many full months have passed.
                $last_applied = new DateTime($loan['last_interest_applied_at']);
                $now = new DateTime();
                $interval = $last_applied->diff($now);
                $months_passed = ($interval->y * 12) + $interval->m;

                if ($months_passed > 0) {
                    // Apply compound interest for each month that has passed.
                    $new_balance = $loan['balance'];
                    for ($i = 0; $i < $months_passed; $i++) {
                        $interest_to_apply = $new_balance * ($loan['interest_rate'] / 100);
                        $new_balance += $interest_to_apply;
                        $total_interest_applied += $interest_to_apply;
                    }

                    $update_stmt->execute([$new_balance, $loan['id']]);
                    $processed_loans++;
                }
            }
             $success_message = "Successfully applied interest to {$processed_loans} loan(s). Total interest added: " . number_format($total_interest_applied, 0) . " UGX.";
        }

        // Log the bulk action
        if ($processed_loans > 0) {
            $log_action = "Admin ran interest script. Applied interest to {$processed_loans} loans.";
            $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
            $log_stmt->execute([$_SESSION['user_id'], $log_action]);
        }

        // Update the system setting to mark interest as run for this month
        $update_setting_stmt = $pdo->prepare("UPDATE system_settings SET setting_value = NOW() WHERE setting_key = 'last_interest_run'");
        $update_setting_stmt->execute();

        $pdo->commit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $errors[] = "Database error: " . $e->getMessage();
    }
}
?>

<div class="container mx-auto mt-10">
    <h2 class="text-3xl font-bold mb-6 text-gray-800">Apply Monthly Loan Interest</h2>

    <?php if ($success_message): ?>
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">Success!</span> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">Error!</span>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <p class="text-gray-700 mb-4">
            This action will calculate and apply the <strong>2% monthly interest</strong> to the balance of all active, approved loans.
            Interest is applied if at least one full month has passed since the last time interest was calculated for a loan.
            This process is idempotent; running it multiple times in the same month will not apply interest more than once.
        </p>
        <p class="text-gray-600 text-sm mb-6">
            <strong>Note:</strong> This is a manual process that should be run by an administrator at the beginning of each month to ensure all loan balances are up-to-date.
        </p>

        <form action="apply_interest.php" method="POST">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Run Interest Application Script
            </button>
        </form>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
