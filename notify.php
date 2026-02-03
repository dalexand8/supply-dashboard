<?php
function notify_admins($subject, $body) {
    require 'db.php';  // For PDO if needed

    try {
        $stmt = $pdo->query("SELECT email FROM users WHERE is_admin = 1");
        $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($admins)) return;

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: supply-dashboardy@cvhh.net\r\n";  // Change to your domain

        foreach ($admins as $email) {
            mail($email, $subject, $body, $headers);
        }
    } catch (Exception $e) {
        // Silent fail or log
    }
}
?>