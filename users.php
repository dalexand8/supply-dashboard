<?php
// users.php - Admins and users separated
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {

$current_page = basename(__FILE__);

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
    $notify_supply = $is_admin ? (isset($_POST['notify_supply']) ? 1 : 0) : 0;
    $email = trim($_POST['email'] ?? '');
    
    if (empty($username) || empty($_POST['password']) || empty($email)) {
        $error = 'Username, password, and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Valid email address required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, is_admin, notify_supply, email) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $password, $is_admin, $notify_supply, $email]);
            $success = 'User registered successfully.';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Username or email already exists.';
            } else {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Handle updates
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
    } elseif (isset($_POST['toggle_notify'])) {
        try {
            $check = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
            $check->execute([$user_id]);
            if ($check->fetchColumn()) {
                $stmt = $pdo->prepare("UPDATE users SET notify_supply = NOT notify_supply WHERE id = ?");
                $stmt->execute([$user_id]);
                $success = 'Supply notification preference toggled.';
            }
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_email'])) {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $error = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Valid email address required.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$email, $user_id]);
                $success = 'Email updated.';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'Email already in use.';
                } else {
                    $error = 'Error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
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

// Fetch admins and non-admins separately
try {
    $admin_stmt = $pdo->query("
        SELECT u.id, u.username, u.email, u.notify_supply, MAX(l.login_time) as last_login
        FROM users u
        LEFT JOIN login_logs l ON u.id = l.user_id
        WHERE u.is_admin = 1
        GROUP BY u.id
        ORDER BY u.username
    ");
    $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);

    $user_stmt = $pdo->query("
        SELECT u.id, u.username, u.email, MAX(l.login_time) as last_login
        FROM users u
        LEFT JOIN login_logs l ON u.id = l.user_id
        WHERE u.is_admin = 0
        GROUP BY u.id
        ORDER BY u.username
    ");
    $non_admins = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $admins = [];
    $non_admins = [];
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
            <div class="col-md-4">
                <label for="email" class="form-label">Email (required)</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="form-check mt-2">
                    <input type="checkbox" class="form-check-input" id="is_admin" name="is_admin">
                    <label class="form-check-label" for="is_admin">Is Admin?</label>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="form-check mt-2">
                    <input type="checkbox" class="form-check-input" id="notify_supply" name="notify_supply" checked>
                    <label class="form-check-label" for="notify_supply">Supply Notifications? (Admins only)</label>
                </div>
            </div>
            <div class="col-md-12 text-end">
                <button type="submit" class="btn btn-primary">Register</button>
            </div>
        </div>
    </form>
    
    <h4>Existing Admins</h4>
    <?php if (!empty($admins)): ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Supply Notifications?</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $u): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td><?php echo htmlspecialchars($u['email'] ?? 'None'); ?></td>
                        <td><?php echo $u['notify_supply'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $u['last_login'] ? date('n/j/Y g:i A', strtotime($u['last_login'])) : 'Never'; ?></td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="toggle_admin" class="btn btn-sm btn-warning">Remove Admin</button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="toggle_notify" class="btn btn-sm btn-info">Toggle Notifications</button>
                            </form>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $u['id']; ?>">Edit Email/Password</button>
                            <a href="users.php?delete_id=<?php echo $u['id']; ?>" class="btn btn-sm bg-secondary" onclick="return confirm('Delete this user?');">Delete</a>
                        </td>
                    </tr>
                    
                    <!-- Edit Modal (same as before) -->
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
                                            <label for="email" class="form-label">Email (required)</label>
                                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" required>
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
            </tbody>
        </table>
    <?php else: ?>
        <p>No admins found.</p>
    <?php endif; ?>
    
    <h4 class="mt-5">Existing Users (Non-Admins)</h4>
    <?php if (!empty($non_admins)): ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($non_admins as $u): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td><?php echo htmlspecialchars($u['email'] ?? 'None'); ?></td>
                        <td><?php echo $u['last_login'] ? date('n/j/Y g:i A', strtotime($u['last_login'])) : 'Never'; ?></td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="toggle_admin" class="btn btn-sm btn-success">Make Admin</button>
                            </form>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $u['id']; ?>">Edit Email/Password</button>
                            <a href="users.php?delete_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-secondary" onclick="return confirm('Delete this user?');">Delete</a>
                        </td>
                    </tr>
                    
                    <!-- Edit Modal (same) -->
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
                                            <label for="email" class="form-label">Email (required)</label>
                                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" required>
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
            </tbody>
        </table>
    <?php else: ?>
        <p>No non-admin users found.</p>
    <?php endif; ?>
    
    <a href="admin.php" class="btn btn-secondary mt-3">Back to Admin Panel</a>
</div>

<?php include 'includes/footer.php'; ?>