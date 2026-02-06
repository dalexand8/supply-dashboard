<?php
// includes/notify.php
require_once __DIR__ . '/../env.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function notify_admins($subject, $body_html) {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT email FROM users WHERE is_admin = 1 AND notify_supply = 1 AND email IS NOT NULL AND email != ''");
        $admin_emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($admin_emails)) return;
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_EMAIL');
        $mail->Password   = getenv('SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)getenv('SMTP_PORT');
        
        $mail->setFrom(getenv('SMTP_EMAIL'), 'Supply Dashboard');
        foreach ($admin_emails as $email) {
            $mail->addAddress($email);
        }
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body_html;
        $mail->AltBody = strip_tags($body_html);
        
        $mail->send();
    } catch (Exception $e) {
        error_log("Notification failed: " . $e->getMessage());
    }
}

function notify_user($to_email, $subject, $body_html) {
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid or missing email for user notification: " . $to_email);
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_EMAIL');
        $mail->Password   = getenv('SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)getenv('SMTP_PORT');

        $mail->setFrom(getenv('SMTP_EMAIL'), 'Supply Dashboard');
        $mail->addAddress($to_email);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body_html;
        $mail->AltBody = strip_tags($body_html);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("User notification failed to {$to_email}: " . $e->getMessage());
        return false;
    }
}
?>