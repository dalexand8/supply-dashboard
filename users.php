<?php
// users.php - Admin user management
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Handle password reset or admin toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_POST['user_id'];
    if (isset($_POST['reset_password'])) {
        $new_pass = $_POST['new_password'] ?? '';
        if (strlen($new_pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            try {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $user_id]);
                $success = 'Password reset successfully.';
            } catch (PDOException $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['toggle_admin'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET is_admin = NOT is_admin WHERE id = ?");
            $stmt->execute([$user_id]);
            $success = 'Admin status toggled.';
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        // Check if user has requests
        $check = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE user_id = ?");
        $check->execute([$delete_id]);
        if ($check->fetchColumn() > 0) {
            $error = 'Cannot delete user with existing requests.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$delete_id]);
            $success = 'User deleted successfully.';
        }
    } catch (PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Fetch users with last login
try {
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.is_admin, MAX(l.login_time) as last_login
        FROM users u
        LEFT JOIN login_logs l ON u.id = l.user_id
        GROUP BY u.id
        ORDER BY u.username
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - Supply Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body { display: flex; flex-direction: column; }
        .content { flex: 1 0 auto; }
        footer { flex-shrink: 0; }
    </style>
</head>
<body>
 <?php include 'includes/navbar.php'; ?>
    <div class="container mt-5 content">
        <h2>Manage Users</h2>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Admin?</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td><?php echo $u['is_admin'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $u['last_login'] ? date('n/j/Y g:i A', strtotime($u['last_login'])) : 'Never'; ?></td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="toggle_admin" class="btn btn-sm btn-warning">Toggle Admin</button>
                            </form>
                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#resetModal<?php echo $u['id']; ?>">Reset Password</button>
                            <a href="users.php?delete_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?');">Delete</a>
                        </td>
                    </tr>
                    
                    <!-- Reset Password Modal -->
                    <div class="modal fade" id="resetModal<?php echo $u['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Reset Password for <?php echo htmlspecialchars($u['username']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" name="new_password" required minlength="6">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr><td colspan="4">No users found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <a href="admin.php" class="btn btn-secondary">Back to Admin Panel</a>
    </div>
    <footer class="bg-primary text-white text-center py-3">
        <p>&copy; 2024 Supply Dashboard. All rights reserved.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>