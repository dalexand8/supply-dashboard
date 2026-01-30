<?php
// shopping_list.php - Updated to show variants in grouped view
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}
$grouped_items = [];
$error = '';
$email_success = '';
$email_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    // ... your existing email code (unchanged) ...
}

// Load grouped data with variants
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
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
    
    <!-- Email form (unchanged) -->
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
    // Group by item_name for display
    $current_item = '';
    foreach ($grouped_items as $row): 
        if ($row['item_name'] !== $current_item):
            if ($current_item !== '') echo '</ul></li>'; // Close previous
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
    if ($current_item !== '') echo '</ul></div>'; // Close last
    if (empty($grouped_items)): ?>
        <p>No requests yet.</p>
    <?php endif; ?>
    <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
</div>

<?php include 'includes/footer.php'; ?>