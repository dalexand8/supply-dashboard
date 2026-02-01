<?php
// shopping_list.php - Clean production version (no debug)
session_start();
require_once 'env.php'; // Loads .env
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$grouped_items = [];
$error = '';
$email_success = '';
$email_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $emails = $_POST['emails'] ?? '';
    $subject = $_POST['subject'] ?? 'Supply Shopping List - ' . date('Y-m-d');
    $message = $_POST['message'] ?? '';
    
    if (empty($emails)) {
        $email_error = 'Please enter at least one email address.';
    } else {
        $email_list = array_filter(array_map('trim', explode(',', $emails)));
        if (empty($email_list)) {
            $email_error = 'Invalid email addresses.';
        } else {
            // Generate grouped list for email
            try {
                $stmt = $pdo->query("
                    SELECT 
                        r.item_name,
                        iv.name AS variant_name,
                        SUM(r.quantity) AS total_qty,
                        GROUP_CONCAT(CONCAT(r.location, ':', r.quantity) SEPARATOR ', ') AS location_details
                    FROM requests r
                    LEFT JOIN item_variants iv ON r.variant_id = iv.id
                    GROUP BY r.item_name, r.variant_id
                    ORDER BY r.item_name, iv.name
                ");
                $grouped_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $email_error = 'Database error generating list: ' . $e->getMessage();
            }
            
            if (empty($email_error)) {
                $body = "<h2>Supply Shopping List</h2>\n<p>Generated on " . date('Y-m-d H:i:s') . "</p>\n";
                if (!empty($message)) {
                    $body .= "<p>" . nl2br(htmlspecialchars($message)) . "</p>\n";
                }
                
                $current_item = '';
                foreach ($grouped_items as $row) {
                    if ($row['item_name'] !== $current_item) {
                        if ($current_item !== '') $body .= "</ul>\n";
                        $body .= "<h3>" . htmlspecialchars($row['item_name']) . "</h3><ul>\n";
                        $current_item = $row['item_name'];
                    }
                    $variant = $row['variant_name'] ? htmlspecialchars($row['variant_name']) : 'No variant';
                    $body .= "<li><strong>$variant</strong>: Qty " . $row['total_qty'];
                    if ($row['location_details']) {
                        $body .= " (" . htmlspecialchars($row['location_details']) . ")";
                    }
                    $body .= "</li>\n";
                }
                if ($current_item !== '') $body .= "</ul>\n";
                if (empty($grouped_items)) {
                    $body .= "<p>No requests yet.</p>\n";
                }
                
                require 'PHPMailer/src/Exception.php';
                require 'PHPMailer/src/PHPMailer.php';
                require 'PHPMailer/src/SMTP.php';
                
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = getenv('SMTP_EMAIL');
                    $mail->Password   = getenv('SMTP_PASS');
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
                    
                    $mail->setFrom(getenv('SMTP_EMAIL'), 'Supply Dashboard');
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
                    $email_error = 'Email failed: ' . $mail->ErrorInfo;
                }
            }
        }
    }
}

// Load grouped data for display
try {
    $stmt = $pdo->query("
        SELECT 
            r.item_name,
            iv.name AS variant_name,
            SUM(r.quantity) AS total_qty,
            GROUP_CONCAT(CONCAT(r.location, ':', r.quantity) SEPARATOR ', ') AS location_details
        FROM requests r
        LEFT JOIN item_variants iv ON r.variant_id = iv.id
        GROUP BY r.item_name, r.variant_id
        ORDER BY r.item_name, iv.name
    ");
    $grouped_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container mt-5">
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
    
    <h4 class="mt-4">Send Shopping List via Email</h4>
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
    
    <p>This page shows aggregated quantities for each item and variant across all locations to make shopping easier.</p>
    <?php 
    $current_item = '';
    foreach ($grouped_items as $row): 
        if ($row['item_name'] !== $current_item):
            if ($current_item !== '') echo '</ul></div>'; // Close previous card
            $current_item = $row['item_name'];
    ?>
        <div class="card mb-3">
            <div class="card-header bg-secondary text-white fw-bold">
                <?php echo htmlspecialchars($row['item_name']); ?>
            </div>
            <ul class="list-group list-group-flush">
    <?php endif; ?>
        <li class="list-group-item">
            <strong>
                <?php echo $row['variant_name'] ? htmlspecialchars($row['variant_name']) : 'No variant'; ?>
            </strong>: Qty <?php echo $row['total_qty']; ?>
            <?php if ($row['location_details']): ?>
                (<?php echo htmlspecialchars($row['location_details']); ?>)
            <?php endif; ?>
        </li>
    <?php 
    endforeach; 
    if ($current_item !== '') echo '</ul></div>'; // Close last card
    if (empty($grouped_items)): ?>
        <p>No requests yet.</p>
    <?php endif; ?>
    <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
</div>

<?php include 'includes/footer.php'; ?>