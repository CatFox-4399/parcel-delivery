<?php
/**
 * index.php — Application Entry Point
 * Redirects authenticated users to their dashboard.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    if (current_role() === 'admin') {
        redirect('/admin/dashboard.php');
    } else {
        redirect('/rider/dashboard.php');
    }
}

redirect('/login.php');
