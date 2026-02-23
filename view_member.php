<?php
require_once 'includes/auth_check.php';
// Only admins can view full member details
check_permissions([1, 2, 3, 4]);

require_once 'config/db_connect.php';
require_once 'templates/header.php';
require_once 'includes/CreditScoreHelper.php';

$member_id = $_GET['id'] ?? null;

if (!$member_id) {
    die("Member ID is required.");
}

try {
    // Fetch member details
    $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();

    if (!$member) {
        die("Member not found.");
    }

    // Fetch savings summary
    $savings_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_saved, COUNT(*) as transaction_count FROM savings WHERE user_id = ?");
    $savings_stmt->execute([$member_id]);
    $savings_summary = $savings_stmt->fetch();

    // Fetch recent savings
    $recent_savings_stmt = $pdo->prepare("SELECT amount, created_at FROM savings WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $recent_savings_stmt->execute([$member_id]);
    $recent_savings = $recent_savings_stmt->fetchAll();

    $scoreHelper = new CreditScoreHelper($pdo);
    $score = $scoreHelper->getScore($member_id);
    $scoreLabel = $scoreHelper->getScoreLabel($score);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

function getRoleName($roleId) {
    switch ($roleId) {
        case 1: return 'Root';
        case 2: return 'Chairman';
        case 3: return 'Secretary';
        case 4: return 'Treasurer';
        case 5: return 'Member';
        default: return 'Unknown';
    }
}

// Generate QR Code URL (pointing to a public profile)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$public_url = $protocol . "://" . $host . "/public_profile.php?id=" . $member['id'];

?>

<div class="container mx-auto mt-8 p-4">
    <div class="flex flex-col md:flex-row gap-6">
        <!-- Sidebar / Profile Card -->
        <div class="w-full md:w-1/3">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 text-center">
                <div class="mb-4 flex justify-center">
                    <?php display_avatar($member['avatar'], $member['username'], $member['first_name'], $member['surname'], 'h-32 w-32 text-4xl'); ?>
                </div>
                <h1 class="text-2xl font-bold text-slate-800"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['surname']); ?></h1>
                <p class="text-slate-500 font-medium">@<?php echo htmlspecialchars($member['username']); ?></p>

                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-bold uppercase">
                        <?php echo htmlspecialchars($member['role_name']); ?>
                    </span>
                    <span class="px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold uppercase">
                        ID: <?php echo htmlspecialchars($member['account_no']); ?>
                    </span>
                </div>

                <div class="mt-8 border-t border-slate-100 pt-6">
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-slate-500">Email</span>
                        <span class="text-slate-800 font-medium"><?php echo htmlspecialchars($member['email']); ?></span>
                    </div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-slate-500">Phone</span>
                        <span class="text-slate-800 font-medium"><?php echo htmlspecialchars($member['phone']); ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Joined</span>
                        <span class="text-slate-800 font-medium"><?php echo date('M j, Y', strtotime($member['created_at'])); ?></span>
                    </div>
                </div>

                <div class="mt-8">
                    <p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-2">Public Profile QR</p>
                    <div class="flex justify-center">
                         <?php
                            // Using the same logic we have in generate_pdf.php but for HTML
                            // Actually, TCPDF doesn't easily output just a QR in HTML.
                            // I'll use a simple placeholder or a small image generation script.
                            // For now, let's just use a placeholder text or a link.
                         ?>
                         <div class="p-2 border border-slate-200 rounded-lg bg-slate-50">
                            <i data-lucide="qr-code" class="w-24 h-24 text-slate-300"></i>
                         </div>
                    </div>
                    <p class="mt-2 text-[10px] text-slate-400">Scan to view public credentials</p>
                </div>

                <div class="mt-8">
                    <a href="download_member_profile.php?id=<?php echo $member['id']; ?>" class="w-full flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-900 text-white font-bold py-3 px-4 rounded-xl transition-all">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        Download Profile PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="w-full md:w-2/3 space-y-6">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Savings</p>
                    <p class="text-2xl font-bold text-slate-800"><?php echo number_format($savings_summary['total_saved'], 0); ?> <span class="text-sm font-normal text-slate-500">UGX</span></p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Credit Score</p>
                    <div class="flex items-center gap-2">
                        <p class="text-2xl font-bold <?php echo $scoreLabel['color']; ?>"><?php echo $score; ?></p>
                        <span class="text-[10px] bg-slate-100 px-2 py-0.5 rounded text-slate-600 font-bold uppercase"><?php echo $scoreLabel['label']; ?></span>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Transactions</p>
                    <p class="text-2xl font-bold text-slate-800"><?php echo $savings_summary['transaction_count']; ?></p>
                </div>
            </div>

            <!-- Savings History -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-800">Recent Savings History</h2>
                    <a href="view_savings.php?member_id=<?php echo $member['id']; ?>&view_mode=history" class="text-indigo-600 hover:text-indigo-700 text-sm font-semibold">View All</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Amount (UGX)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($recent_savings)): ?>
                                <tr>
                                    <td colspan="2" class="px-6 py-8 text-center text-slate-400 italic">No savings recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_savings as $saving): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                            <?php echo date('M j, Y, g:i a', strtotime($saving['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-slate-800">
                                            <?php echo number_format($saving['amount'], 0); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
