<?php
// edit.php - Fixed variant dropdown (unique by name)
session_start();
require_once 'env.php'; // Load .env (critical for SMTP_EMAIL)
include 'db.php';

$current_page = basename(__FILE__);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}
$req_id = (int)$_GET['id'];
$request = null;
$error = '';
try {
    $stmt = $pdo->prepare("SELECT r.*, u.email AS requester_email, iv.name AS current_variant_name FROM requests r JOIN users u ON r.user_id = u.id LEFT JOIN item_variants iv ON r.variant_id = iv.id WHERE r.id = ?");
    $stmt->execute([$req_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
if (!$request || ($request['user_id'] != $_SESSION['user_id'] && !$_SESSION['is_admin'])) {
    header('Location: dashboard.php');
    exit;
}
$is_pending = ($request['status'] == 'Pending');
$statuses = ['Pending', 'Acknowledged', 'Ordered', 'Fulfilled', 'Backordered', 'Unavailable'];

// Fetch unique variants (dedupe by name, keep one id)
$variants = [];
try {
    $vstmt = $pdo->prepare("
        SELECT MIN(iv.id) AS id, iv.name
        FROM item_variants iv
        WHERE iv.item_id = (SELECT id FROM items WHERE name = ? LIMIT 1)
        GROUP BY iv.name
        ORDER BY iv.name
    ");
    $vstmt->execute([$request['item_name']]);
    $variants = $vstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error loading variants: ' . $e->getMessage();
}

$old_status = $request['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = $is_pending ? max(1, (int)($_POST['quantity'] ?? $request['quantity'])) : $request['quantity'];
    $status = $_SESSION['is_admin'] && isset($_POST['status']) ? $_POST['status'] : $request['status'];
    $variant_id = ($_SESSION['is_admin'] || $is_pending) ? ($_POST['variant_id'] ?? null) : $request['variant_id'];
    $variant_id = empty($variant_id) ? null : (int)$variant_id;
   
    try {
        $update_stmt = $pdo->prepare("UPDATE requests SET quantity = ?, status = ?, variant_id = ? WHERE id = ?");
        $update_stmt->execute([$quantity, $status, $variant_id, $req_id]);
        
        // Status change notification (your existing code)
        if ($_SESSION['is_admin'] && $status !== $old_status && !empty($request['requester_email'])) {
            require 'PHPMailer/src/Exception.php';
            require 'PHPMailer/src/PHPMailer.php';
            require 'PHPMailer/src/SMTP.php';
            
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
                $mail->addAddress($request['requester_email']);
                
                $mail->isHTML(true);
                $mail->Subject = 'Your Supply Request Status Updated';
                $mail->Body    = "<p>Hello,</p>
                                  <p>Your request for <strong>" . htmlspecialchars($request['item_name']) . "</strong>" .
                                  (!empty($request['current_variant_name']) ? " (" . htmlspecialchars($request['current_variant_name']) . ")" : '') .
                                  " (Qty: " . $request['quantity'] . ") has been updated from <strong>$old_status</strong> to <strong>$status</strong>.</p>
                                  <p>Thank you!</p>";
                $mail->AltBody = strip_tags($mail->Body);
                
                $mail->send();
            } catch (Exception $e) {
                error_log("Status notification failed: " . $e->getMessage());
            }
        }
        
        header('Location: dashboard.php');
        exit;
    } catch (PDOException $e) {
        $error = 'Error updating request: ' . $e->getMessage();
    }
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container mt-5">
    <h2>Edit Supply Request</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Location</label>
            <p class="form-control-plaintext"><?php echo htmlspecialchars($request['location']); ?></p>
        </div>
        <div class="mb-3">
            <label class="form-label">Item</label>
            <p class="form-control-plaintext"><?php echo htmlspecialchars($request['item_name']); ?></p>
        </div>
        <div class="mb-3">
            <label for="variant_id" class="form-label">Variant</label>
            <?php if ($_SESSION['is_admin'] || $is_pending): ?>
                <select class="form-select" id="variant_id" name="variant_id">
                    <option value="">None</option>
                    <?php foreach ($variants as $v): ?>
                        <option value="<?php echo $v['id']; ?>" <?php if ($v['id'] == $request['variant_id']) echo 'selected'; ?>><?php echo htmlspecialchars($v['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($variants)): ?>
                    <small class="text-muted">No variants available for this item.</small>
                <?php endif; ?>
            <?php else: ?>
                <p class="form-control-plaintext"><?php echo htmlspecialchars($request['current_variant_name'] ?? 'None'); ?></p>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <label for="quantity" class="form-label">Quantity</label>
            <?php if ($is_pending): ?>
                <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="<?php echo $request['quantity']; ?>" required>
            <?php else: ?>
                <p class="form-control-plaintext"><?php echo $request['quantity']; ?></p>
            <?php endif; ?>
        </div>
        <?php if ($_SESSION['is_admin']): ?>
            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <?php foreach ($statuses as $stat): ?>
                        <option value="<?php echo $stat; ?>" <?php if ($stat === $request['status']) echo 'selected'; ?>><?php echo $stat; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($is_pending || $_SESSION['is_admin']): ?>
            <button type="submit" class="btn btn-primary">Update</button>
        <?php else: ?>
            <p class="text-muted">This request is no longer editable.</p>
        <?php endif; ?>
    </form>
    <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
</div>

<?php include 'includes/footer.php'; ?>