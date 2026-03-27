<?php
// Securely start a session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION['user_id']);
?>
        <?php if ($is_logged_in): ?>
                </main>
            </div>
        <?php endif; ?>
    </body>
</html>
