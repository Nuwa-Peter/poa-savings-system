<?php
require_once 'includes/auth_check.php';
check_permissions([1, 2, 3, 4, 5]); // All logged-in users
require_once 'templates/header.php';
require_once 'config/db_connect.php';
require_once 'includes/CreditScoreHelper.php';

$scoreHelper = new CreditScoreHelper($pdo);
$user_id = $_SESSION['user_id'];
$current_score = $scoreHelper->getScore($user_id);
$label = $scoreHelper->getScoreLabel($current_score);
?>

<div class="container mx-auto mt-10 p-4 max-w-4xl">
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-xl border border-slate-200 dark:border-slate-800 overflow-hidden transition-all">
        <!-- Header -->
        <div class="p-8 border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50">
            <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">About POA Savings</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 text-lg">Understanding our Savings & Credit System.</p>
        </div>

        <div class="p-8 space-y-12">
            <!-- System Purpose -->
            <section>
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg">
                        <i data-lucide="info" class="w-6 h-6 text-indigo-600"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white">System Purpose</h2>
                </div>
                <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                    POA (Power Of Accumulation) is designed to empower our society members through consistent savings and fair credit access.
                    The system automates financial tracking, interest calculation, and loan eligibility based on data-driven credit scores.
                </p>
            </section>

            <!-- Credit Score Transparency -->
            <section>
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                        <i data-lucide="shield-check" class="w-6 h-6 text-emerald-600"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Credit Score Transparency</h2>
                </div>

                <div class="mb-6 p-6 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <div>
                            <p class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1">Your Current Score</p>
                            <div class="flex items-center gap-3">
                                <span class="text-4xl font-black text-slate-900 dark:text-white"><?php echo $current_score; ?></span>
                                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase <?php echo $label['color']; ?> bg-white dark:bg-slate-900 border border-current shadow-sm">
                                    <?php echo $label['label']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="hidden sm:block h-12 w-px bg-slate-200 dark:bg-slate-700"></div>
                        <div class="flex flex-col gap-1">
                            <span class="text-xs text-slate-400 font-bold uppercase">Fairness Rank</span>
                            <div class="flex gap-1">
                                <?php for($i=0; $i<5; $i++): ?>
                                    <div class="h-2 w-8 rounded-full <?php echo ($i < 3) ? 'bg-indigo-500' : 'bg-slate-200 dark:bg-slate-700'; ?>"></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white mb-3">How it's Calculated</h3>
                        <ul class="space-y-3">
                            <li class="flex gap-3 text-sm text-slate-600 dark:text-slate-400">
                                <i data-lucide="check-circle-2" class="w-5 h-5 text-indigo-500 shrink-0"></i>
                                <span><strong>New Member Grace:</strong> New members start with a higher baseline (600) for the first 3 months.</span>
                            </li>
                            <li class="flex gap-3 text-sm text-slate-600 dark:text-slate-400">
                                <i data-lucide="check-circle-2" class="w-5 h-5 text-indigo-500 shrink-0"></i>
                                <span><strong>Savings Consistency:</strong> We reward the *frequency* of deposits. Saving every month is better than one big lump sum.</span>
                            </li>
                            <li class="flex gap-3 text-sm text-slate-600 dark:text-slate-400">
                                <i data-lucide="check-circle-2" class="w-5 h-5 text-indigo-500 shrink-0"></i>
                                <span><strong>Historical Repayment:</strong> Successfully closing loans boosts your score (+50 points).</span>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white mb-3">How to Increase Your Score</h3>
                        <ul class="space-y-3">
                            <li class="flex gap-3 text-sm text-slate-600 dark:text-slate-400">
                                <i data-lucide="trending-up" class="w-5 h-5 text-emerald-500 shrink-0"></i>
                                <span>Make at least one deposit every calendar month.</span>
                            </li>
                            <li class="flex gap-3 text-sm text-slate-600 dark:text-slate-400">
                                <i data-lucide="trending-up" class="w-5 h-5 text-emerald-500 shrink-0"></i>
                                <span>Ensure all loan installments are paid before the due date.</span>
                            </li>
                            <li class="flex gap-3 text-sm text-slate-600 dark:text-slate-400">
                                <i data-lucide="trending-up" class="w-5 h-5 text-emerald-500 shrink-0"></i>
                                <span>Avoid guaranteeing loans for members with Poor credit history.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Role Permissions -->
            <section>
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                        <i data-lucide="users" class="w-6 h-6 text-amber-600"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Role-Based Access</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Capabilities</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-indigo-600">Member</td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">View savings, Request loans, Manage goals, View dividends.</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-amber-600">Admin / Root</td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">Manage members, Approve loans, Distribute interest, View global analytics.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Footer Link -->
        <div class="p-8 bg-slate-50 dark:bg-slate-800/30 text-center border-t border-slate-100 dark:border-slate-800">
            <p class="text-slate-500 dark:text-slate-400 text-sm">Need more help? Contact the society administrator at <a href="mailto:admin@poa.dev" class="text-indigo-500 font-bold hover:underline">support@poa.dev</a></p>
        </div>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
