<?php
// users.php - Full merged user management with email editing
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Handle new user registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_user'])) {
    $username = trim($_POST['username'] ?? '');
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $email = trim($_POST['email'] ?? '');
    
    if (empty($username) || empty($_POST['password'])) {
        $error = 'Username and password are required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, is_admin, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $password, $is_admin, $email ?: null]);
            $success = 'User registered successfully.';
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage(); // e.g., duplicate username/email
        }
    }
}

// Handle updates (password reset, admin toggle, email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
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
    } elseif (isset($_POST['update_email'])) {
        $email = trim($_POST['email'] ?? '');
        try {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$email ?: null, $user_id]);
            $success = 'Email updated.';
        } catch (PDOException $e) {
            $error = 'Error updating email: ' . $e->getMessage();
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
        SELECT u.id, u.username, u.is_admin, u.email, MAX(l.login_time) as last_login
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
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container mt-5">
    <h2>Manage Users</h2>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <h4>Add New User</h4>
    <form method="POST" class="mb-5">
        <input type="hidden" name="register_user" value="1">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="col-md-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="col-md-3">
                <label for="email" class="form-label">Email (for notifications)</label>
                <input type="email" class="form-control" id="email" name="email">
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="form-check mt-2">
                    <input type="checkbox" class="form-check-input" id="is_admin" name="is_admin">
                    <label class="form-check-label" for="is_admin">Admin?</label>
                </div>
            </div>
            <div class="col-md-12 text-end">
                <button type="submit" class="btn btn-primary">Register</button>
            </div>
        </div>
    </form>
    
    <h4>Existing Users</h4>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Admin?</th>
                <th>Last Login</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                    <td><?php echo htmlspecialchars($u['email'] ?? 'None'); ?></td>
                    <td><?php echo $u['is_admin'] ? 'Yes' : 'No'; ?></td>
                    <td><?php echo $u['last_login'] ? date('n/j/Y g:i A', strtotime($u['last_login'])) : 'Never'; ?></td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <button type="submit" name="toggle_admin" class="btn btn-sm btn-warning">Toggle Admin</button>
                        </form>
                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $u['id']; ?>">Edit</button>
                        <a href="users.php?delete_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?');">Delete</a>
                    </td>
                </tr>
                
                <!-- Edit User Modal (password reset + email) -->
                <div class="modal fade" id="editUserModal<?php echo $u['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit <?php echo htmlspecialchars($u['username']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email (for notifications)</label>
                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>">
                                        <button type="submit" name="update_email" class="btn btn-sm btn-secondary mt-2">Update Email</button>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">Reset Password (leave blank to keep current)</label>
                                        <input type="password" class="form-control" name="new_password" minlength="6">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="reset_password" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
                <tr><td colspan="5">No users found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <a href="admin.php" class="btn btn-secondary">Back to Admin Panel</a>
</div>

<?php include 'includes/footer.php'; ?>