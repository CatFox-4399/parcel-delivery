<?php
/**
 * logout.php — Session Termination
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    // Log the logout action before destroying the session
    log_activity(current_user_id(), 'logout', 'User logged out.');

    // If this is a rider going offline, mark them offline
    if (current_role() === 'rider') {
        try {
            $stmt = db()->prepare('UPDATE riders SET is_online = 0 WHERE user_id = ?');
            $stmt->execute([current_user_id()]);
        } catch (PDOException $e) {
            error_log('[Logout] Could not set rider offline: ' . $e->getMessage());
        }
    }
}

logout_user();
redirect('/login.php');
