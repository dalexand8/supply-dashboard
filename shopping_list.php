<?php
// shopping_list.php - Fixed email with your .env vars
session_start();
require_once 'env.php'; // Make sure this loads .env
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

// Debug .env load (remove after testing)
$debug = '';
$debug .= 'SMTP_EMAIL: ' . (getenv('SMTP_EMAIL') ?: 'EMPTY/MISSING') . '<br>';
$debug .= 'SMTP_PASS: ' . (getenv('SMTP_PASS') ? 'SET' : 'EMPTY/MISSING') . '<br>';

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
            // Generate list
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
                $email_error = 'Database error: ' . $e->getMessage();
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
                    $mail->SMTPDebug = 0; // Set to 2 for verbose debug if needed
                    $mail->isSMTP();
                    $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = getenv('SMTP_EMAIL'); // Your .env name
                    $mail->Password   = getenv('SMTP_PASS');
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
                    
                    $from_email = getenv('SMTP_EMAIL');
                    if (empty($from_email)) {
                        throw new Exception('SMTP_EMAIL not set in .env');
                    }
                    $mail->setFrom($from_email, 'Supply Dashboard');
                    
                    foreach ($email_list as $email) {
                        $mail->addAddress($email);
                    }
                    
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = $body;
                    $mail->AltBody = strip_tags($body);
                    
                    $mail->send();
                    $email_success = 'Shopping list emailed successfully!';
                } catch (Exception $e) {
                    $email_error = 'Email failed: ' . $mail->ErrorInfo;
                }
            }
        }
    }
}

// Load grouped data for display (same as before)
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
    
    <!-- Debug .env (remove after testing) -->
    <?php if ($debug): ?>
        <div class="alert alert-info"><?php echo $debug; ?></div>
    <?php endif; ?>
    
    <?php if ($email_success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($email_success); ?></div>
    <?php endif; ?>
    <?php if ($email_error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($email_error); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Email form and grouped display (same as your last version) -->
    <!-- ... keep your existing form and grouped list code here ... -->
    
</div>

<?php include 'includes/footer.php'; ?>