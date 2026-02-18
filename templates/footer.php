<?php
// Securely start a session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION['user_id']);
?>
        <?php if ($is_logged_in): ?>
                </main>

                <!-- Mobile Bottom Navigation -->
                <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white/80 backdrop-blur-lg border-t border-slate-100 px-6 py-3 z-50 shadow-[0_-5px_15px_rgba(0,0,0,0.05)]">
                    <div class="flex justify-between items-center max-w-md mx-auto">
                        <a href="dashboard.php" class="flex flex-col items-center gap-1 text-indigo-600">
                            <i data-lucide="home" class="w-6 h-6"></i>
                            <span class="text-[10px] font-bold">Home</span>
                        </a>
                        <a href="view_savings.php" class="flex flex-col items-center gap-1 text-slate-400 hover:text-indigo-600 transition-colors">
                            <i data-lucide="piggy-bank" class="w-6 h-6"></i>
                            <span class="text-[10px] font-bold">Savings</span>
                        </a>
                        <a href="request_loan.php" class="flex flex-col items-center gap-1 text-slate-400 hover:text-indigo-600 transition-colors">
                            <i data-lucide="landmark" class="w-6 h-6"></i>
                            <span class="text-[10px] font-bold">Loans</span>
                        </a>
                        <a href="settings.php" class="flex flex-col items-center gap-1 text-slate-400 hover:text-indigo-600 transition-colors">
                            <i data-lucide="user" class="w-6 h-6"></i>
                            <span class="text-[10px] font-bold">Profile</span>
                        </a>
                    </div>
                </nav>

                <footer class="text-center py-4 text-gray-500 text-sm mb-20 lg:mb-0">
                    &copy; <?php echo date('Y'); ?> POA Savings and Credit Society
                </footer>
            </div>
        <?php endif; ?>
    </body>
</html>
