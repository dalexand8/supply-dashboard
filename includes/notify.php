<?php
// includes/notify.php - Fixed use statements at top
require_once __DIR__ . '/../env.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

// use statements at top (correct placement)
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
?>