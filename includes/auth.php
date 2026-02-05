<?php
// auth.php

ini_set('session.gc_maxlifetime', 86400);  // 24 hours
session_set_cookie_params(86400);

session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?expired=1');
    exit;
}

// Admin check for admin pages
if (basename($_SERVER['PHP_SELF']) === 'admin.php' && !$_SESSION['is_admin']) {
    header('Location: dashboard.php');
    exit;
}
?>