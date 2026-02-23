<?php
// Public page - no auth check for basic info
require_once 'config/db_connect.php';
require_once 'includes/user_functions.php';
require_once 'includes/avatar_functions.php';

$member_id = $_GET['id'] ?? null;

if (!$member_id) {
    die("Invalid request.");
}

try {
    // Fetch limited member details for public view
    $stmt = $pdo->prepare("SELECT u.first_name, u.surname, u.username, u.account_no, u.avatar, u.created_at, u.role_id, r.role_name
                           FROM users u
                           LEFT JOIN roles r ON u.role_id = r.id
                           WHERE u.id = ? AND u.status = 'active'");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();

    if (!$member) {
        die("Member not found or account is inactive.");
    }

    // Fallback if roles table is missing or role_name is NULL
    if (!isset($member['role_name']) || is_null($member['role_name'])) {
        $member['role_name'] = getRoleName($member['role_id']);
    }

} catch (PDOException $e) {
    die("Error loading profile.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($member['first_name']); ?>'s Profile - POA Savings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
        <div class="bg-indigo-600 h-32 flex items-center justify-center relative">
            <div class="absolute -bottom-12">
                 <?php display_avatar($member['avatar'], $member['username'], $member['first_name'], $member['surname'], 'h-24 w-24 ring-4 ring-white shadow-lg'); ?>
            </div>
        </div>
        <div class="pt-16 pb-8 px-6 text-center">
            <div class="flex items-center justify-center gap-2 mb-1">
                <h1 class="text-2xl font-bold text-slate-800"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['surname']); ?></h1>
                <i data-lucide="check-circle" class="w-5 h-5 text-indigo-500 fill-indigo-500/10"></i>
            </div>
            <p class="text-slate-500 font-medium mb-4">@<?php echo htmlspecialchars($member['username']); ?></p>

            <div class="flex justify-center gap-2 mb-8">
                <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-full text-[10px] font-bold uppercase tracking-wider">
                    <?php echo htmlspecialchars($member['role_name']); ?>
                </span>
                <span class="px-3 py-1 bg-slate-50 text-slate-500 rounded-full text-[10px] font-bold uppercase tracking-wider">
                    Official Member
                </span>
            </div>

            <div class="grid grid-cols-2 gap-4 text-left">
                <div class="p-4 bg-slate-50 rounded-2xl">
                    <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Account ID</p>
                    <p class="text-slate-700 font-semibold"><?php echo htmlspecialchars($member['account_no']); ?></p>
                </div>
                <div class="p-4 bg-slate-50 rounded-2xl">
                    <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Member Since</p>
                    <p class="text-slate-700 font-semibold"><?php echo date('M Y', strtotime($member['created_at'])); ?></p>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-slate-100">
                <div class="flex items-center justify-center gap-2 text-slate-400">
                    <img src="assets/images/poa_light.png" alt="POA Logo" class="h-6 opacity-50">
                    <span class="text-xs">Verified by POA SACCO</span>
                </div>
            </div>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
