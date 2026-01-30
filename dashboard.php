<?php
// dashboard.php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$locations = ['Turlock office', 'Modesto office', 'Merced office', 'Atwater Office', 'Sonora office'];
$location_colors = [
    'Turlock office' => 'bg-success',
    'Modesto office' => 'bg-info',
    'Merced office' => 'bg-warning',
    'Atwater Office' => 'bg-danger',
    'Sonora office' => 'bg-primary'
];
$requests_by_location = [];
$error = '';
try {
    foreach ($locations as $loc) {
        $stmt = $pdo->prepare("
            SELECT r.id, r.item_name, r.quantity, r.user_id, r.status, r.created_at, u.username
            FROM requests r
            JOIN users u ON r.user_id = u.id
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

    <div class="container mt-5 content">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <h2>Supply Requests Dashboard</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <hr>
        <?php foreach ($locations as $loc): ?>
            <div class="card mb-4 office-card">
                <div class="card-header <?php echo $location_colors[$loc] ?? 'bg-secondary'; ?> text-white fw-bold">
                    <?php echo $loc; ?>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($requests_by_location[$loc] ?? [] as $req): ?>
                        <li class="list-group-item">
                            <?php echo htmlspecialchars($req['item_name']); ?> (Qty: <?php echo $req['quantity']; ?>) by <?php echo htmlspecialchars($req['username']); ?>
                            <?php if ($req['created_at']): ?>
                                - Submitted: <?php echo date('n/j/Y', strtotime($req['created_at'])); ?>
                            <?php endif; ?>
                            - Status: <span class="badge bg-<?php echo getStatusClass($req['status']); ?>"><?php echo $req['status']; ?></span>
                            <?php if ($_SESSION['is_admin'] || ($_SESSION['user_id'] == $req['user_id'] && $req['status'] == 'Pending')): ?>
                                <a href="edit.php?id=<?php echo $req['id']; ?>" class="btn btn-sm btn-warning float-end me-2">Edit</a>
                            <?php endif; ?>
                            <?php if ($_SESSION['user_id'] == $req['user_id'] && $req['status'] == 'Pending'): ?>
                                <a href="delete_my_request.php?id=<?php echo $req['id']; ?>" class="btn btn-sm btn-danger float-end me-2" onclick="return confirm('Are you sure?');">Delete</a>
                            <?php endif; ?>
                            <?php if ($_SESSION['is_admin']): ?>
                                <a href="delete_request.php?id=<?php echo $req['id']; ?>" class="btn btn-sm btn-danger float-end" onclick="return confirm('Are you sure?');">Admin Delete</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($requests_by_location[$loc])): ?>
                        <li class="list-group-item">No requests yet</li>
                    <?php endif; ?>
                </ul>
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
