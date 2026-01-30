<?php
// edit.php - Fixed variant dropdown (unique, robust)
session_start();
include 'db.php';
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
    $stmt = $pdo->prepare("SELECT r.*, iv.name AS current_variant_name FROM requests r LEFT JOIN item_variants iv ON r.variant_id = iv.id WHERE r.id = ?");
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

// Fetch unique variants for this item (one per name)
$variants = [];
try {
    $vstmt = $pdo->prepare("
        SELECT MIN(iv.id) AS id, iv.name
        FROM item_variants iv
        WHERE iv.item_id = (SELECT id FROM items WHERE name = ?)
        GROUP BY iv.name
        ORDER BY iv.name
    ");
    $vstmt->execute([$request['item_name']]);
    $variants = $vstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error loading variants: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = $is_pending ? max(1, (int)($_POST['quantity'] ?? $request['quantity'])) : $request['quantity'];
    $status = $_SESSION['is_admin'] && isset($_POST['status']) ? $_POST['status'] : $request['status'];
    $variant_id = ($_SESSION['is_admin'] || $is_pending) ? ($_POST['variant_id'] ?? null) : $request['variant_id'];
   
    try {
        $update_stmt = $pdo->prepare("UPDATE requests SET quantity = ?, status = ?, variant_id = ? WHERE id = ?");
        $update_stmt->execute([$quantity, $status, $variant_id, $req_id]);
       
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