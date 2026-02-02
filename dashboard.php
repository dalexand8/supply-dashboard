<?php
// dashboard.php - Fixed to show variants
session_start();
include 'db.php';
if (!isset($_SESSION['user_id'])) {

$current_page = basename(__FILE__);

    header('Location: location.php');
    exit;
}
$locations = ['Turlock office', 'Modesto office', 'Merced office', 'Atwater Office', 'Sonora office'];
$location_colors = [
    'Turlock office' => 'bg-secondary',
    'Modesto office' => 'bg-secondary',
    'Merced office' => 'bg-secondary',
    'Atwater Office' => 'bg-secondary',
    'Sonora office' => 'bg-secondary'
];
$requests_by_location = [];
$error = '';
try {
    foreach ($locations as $loc) {
        $stmt = $pdo->prepare("
            SELECT r.id, r.item_name, r.quantity, r.user_id, r.status, r.created_at, u.username, iv.name as variant_name
            FROM requests r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN item_variants iv ON r.variant_id = iv.id
            WHERE r.location = ?
            ORDER BY r.id DESC
        ");
        $stmt->execute([$loc]);
        $requests_by_location[$loc] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container mt-5">
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <h2>Supply Requests Dashboard</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <hr>
    <div class="accordion" id="officesAccordion">
    <?php foreach ($locations as $loc): ?>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button fw-bold <?php echo $location_colors[$loc] ?? 'bg-secondary'; ?> text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo htmlspecialchars(str_replace(' ', '', $loc)); ?>" aria-expanded="true" aria-controls="collapse<?php echo htmlspecialchars(str_replace(' ', '', $loc)); ?>">
                    <?php echo $loc; ?>
                </button>
            </h2>
            <div id="collapse<?php echo htmlspecialchars(str_replace(' ', '', $loc)); ?>" class="accordion-collapse collapse show" data-bs-parent="#officesAccordion">
                <div class="accordion-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($requests_by_location[$loc] ?? [] as $req): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="fw-bold">
                                        <?php echo htmlspecialchars($req['item_name']); ?>
                                        <?php if (!empty($req['variant_name'])): ?>
                                            (<?php echo htmlspecialchars($req['variant_name']); ?>)
                                        <?php endif; ?>
                                        <span class="text-muted fw-normal ms-2">Qty: <?php echo $req['quantity']; ?></span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <?php if ($_SESSION['is_admin'] || ($_SESSION['user_id'] == $req['user_id'] && $req['status'] == 'Pending')): ?>
                                            <a href="edit.php?id=<?php echo $req['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['user_id'] == $req['user_id'] && $req['status'] == 'Pending'): ?>
                                            <a href="delete_my_request.php?id=<?php echo $req['id']; ?>" class="btn btn-sm bg-secondary" onclick="return confirm('Are you sure?');">Delete</a>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['is_admin']): ?>
                                            <a href="delete_request.php?id=<?php echo $req['id']; ?>" class="btn btn-sm bg-secondary" onclick="return confirm('Are you sure?');">Admin Delete</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-muted small mb-1">
                                    <?php if ($req['created_at']): ?>
                                        Submitted: <?php echo date('n/j/Y', strtotime($req['created_at'])); ?>
                                    <?php endif; ?>
                                    by <?php echo htmlspecialchars($req['username']); ?>
                                </div>
                                <div>
                                    Status: <span class="badge bg-<?php echo getStatusClass($req['status']); ?>">
                                        <?php echo $req['status']; ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($requests_by_location[$loc])): ?>
                            <li class="list-group-item">No requests yet</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>

<?php
function getStatusClass($status) {
    switch ($status) {
        case 'Pending': return 'secondary';
        case 'Acknowledged': return 'info';
        case 'Ordered': return 'primary';
        case 'Fulfilled': return 'success';
        case 'Backordered': return 'warning';
        case 'Unavailable': return 'danger';
        default: return 'secondary';
    }
}
?>