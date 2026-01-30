<?php
// shopping_list.php - Fixed use statements at top
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// PHPMailer use statements at top
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$grouped_items = [];
$error = '';
$email_success = '';
$email_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $emails = $_POST['emails'] ?? '';
    $subject = $_POST['subject'] ?? 'Supply Shopping List';
    $message = $_POST['message'] ?? '';
    
    if (empty($emails)) {
        $email_error = 'Please enter at least one email address.';
    } else {
        $email_list = array_filter(array_map('trim', explode(',', $emails)));
        if (empty($email_list)) {
            $email_error = 'Invalid email addresses.';
        } else {
            // Generate list content
            try {
                $stmt = $pdo->query("
                    SELECT
                        item_name,
                        SUM(quantity) AS total_qty,
                        GROUP_CONCAT(CONCAT(location, ':', quantity) SEPARATOR ', ') AS location_details
                    FROM requests
                    GROUP BY item_name
                    ORDER BY item_name
                ");
                $grouped_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $email_error = 'Database error: ' . $e->getMessage();
            }
            
            if (empty($email_error)) {
                $body = "<h2>Supply Shopping List</h2>
<p>Generated on " . date('Y-m-d H:i:s') . "</p>
";
                if (!empty($message)) {
                    $body .= "<p>" . nl2br(htmlspecialchars($message)) . "</p>
";
                }
                $body .= "<ul>
";
                foreach ($grouped_items as $item) {
                    $body .= "<li><strong>" . htmlspecialchars($item['item_name']) . "</strong>: Total Qty " . $item['total_qty'] . " (" . htmlspecialchars($item['location_details']) . ")</li>
";
                }
                $body .= "</ul>
";
                if (empty($grouped_items)) {
                    $body .= "<p>No requests yet.</p>
";
                }
                
                // Load PHPMailer
                require 'PHPMailer/src/Exception.php';
                require 'PHPMailer/src/PHPMailer.php';
                require 'PHPMailer/src/SMTP.php';
                
                $mail = new PHPMailer(true);
                    try {
                    // Server settings
                    $mail->isSMTP();
                    // Inside the try block for PHPMailer
                    $mail->Host       = $_ENV['SMTP_HOST'];          // e.g., smtp.gmail.com
                    $mail->Port       = $_ENV['SMTP_PORT'];          // 587
                    $mail->Username   = $_ENV['SMTP_EMAIL'];
                    $mail->Password   = $_ENV['SMTP_PASS'];
                    $mail->setFrom($_ENV['SMTP_EMAIL'], 'Supply Dashboard');
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    
                    
                    // Recipients
                    $mail->setFrom($_ENV['SMTP_EMAIL'], 'Supply Dashboard');
                    foreach ($email_list as $email) {
                        $mail->addAddress($email);
                    }
                    
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = $body;
                    $mail->AltBody = strip_tags($body);
                    
                    $mail->send();
                    $email_success = 'Shopping list emailed successfully to ' . count($email_list) . ' recipient(s).';
                } catch (Exception $e) {
                    $email_error = "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
            }
        }
    }
}

// Normal list load
try {
    $stmt = $pdo->query("
        SELECT
            item_name,
            SUM(quantity) AS total_qty,
            GROUP_CONCAT(CONCAT(location, ':', quantity) SEPARATOR ', ') AS location_details
        FROM requests
        GROUP BY item_name
        ORDER BY item_name
    ");
    $grouped_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>
    <div class="container mt-5 content">
        <h2>Grouped Shopping List</h2>
        <?php if ($email_success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($email_success); ?></div>
        <?php endif; ?>
        <?php if ($email_error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($email_error); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <h4>Send Shopping List via Email</h4>
        <form method="POST" class="mb-4">
            <input type="hidden" name="send_email" value="1">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="emails" class="form-label">Recipient Emails (comma-separated)</label>
                    <input type="text" class="form-control" id="emails" name="emails" placeholder="email1@example.com, email2@example.com" required>
                </div>
                <div class="col-md-4">
                    <label for="subject" class="form-label">Subject</label>
                    <input type="text" class="form-control" id="subject" name="subject" value="Supply Shopping List - <?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="mb-3 mt-3">
                <label for="message" class="form-label">Additional Message (optional)</label>
                <textarea class="form-control" id="message" name="message" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Send Email</button>
        </form>
        
        <p>This page shows aggregated quantities for each item across all locations to make shopping easier.</p>
        <ul class="list-group">
            <?php foreach ($grouped_items as $item): ?>
                <li class="list-group-item">
                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>: Total Qty <?php echo $item['total_qty']; ?> (<?php echo htmlspecialchars($item['location_details']); ?>)
                </li>
            <?php endforeach; ?>
            <?php if (empty($grouped_items)): ?>
                <li class="list-group-item">No requests yet</li>
            <?php endif; ?>
        </ul>
        <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
    </div>
    <footer class="bg-primary text-white text-center py-3">
        <p>&copy; 2024 Supply Dashboard. All rights reserved.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
