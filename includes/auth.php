<?php
session_start();

ini_set('session.gc_maxlifetime', 86400);  // 24 hours inactivity timeout
session_set_cookie_params(86400);          // Cookie matches (keeps login 24 hours)

require 'db.php';  // Your DB connection

if (!isset($_SESSION['user_id'])) {
    // Session expired or not logged in
    header('Location: login.php?expired=1');  // Your login page + message flag
    exit;
}

// Optional: Admin check (for admin pages)
if (basename($_SERVER['PHP_SELF']) === 'admin.php' && !$_SESSION['is_admin']) {
    header('Location: dashboard.php');
    exit;
}
?>