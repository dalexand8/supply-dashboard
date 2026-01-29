<?php
// edit.php - Edit supply request (quantity if pending, status if admin)
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
    $stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ?");
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = $is_pending ? max(1, (int)($_POST['quantity'] ?? $request['quantity'])) : $request['quantity'];
    $status = $_SESSION['is_admin'] && isset($_POST['status']) ? $_POST['status'] : $request['status'];
   
    try {
        $update_stmt = $pdo->prepare("UPDATE requests SET quantity = ?, status = ? WHERE id = ?");
        $update_stmt->execute([$quantity, $status, $req_id]);
       
        header('Location: dashboard.php');
        exit;
    } catch (PDOException $e) {
        $error = 'Error updating request: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Request - Supply Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body { display: flex; flex-direction: column; }
        .content { flex: 1 0 auto; }
        footer { flex-shrink: 0; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Supply Dashboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="submit.php">Submit Request</a>
                    </li>
                    <?php if ($_SESSION['is_admin']): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="shopping_list.php">Shopping List</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">Admin Panel</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="login_activity.php">Login Activity Log</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register User</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center text-light" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username'] ?? 'User'); ?>&background=0D8ABC&color=fff&bold=true" alt="Avatar" width="32" height="32" class="rounded-circle me-2">
                            <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="change_password.php">Change Password</a></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-5 content">
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
    <footer class="bg-primary text-white text-center py-3">
        <p>&copy; 2024 Supply Dashboard. All rights reserved.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>