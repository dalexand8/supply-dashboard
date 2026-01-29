<?php
// submit.php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$locations = ['Turlock office', 'Modesto office', 'Merced office', 'Atwater Office', 'Sonora office'];
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = $_POST['location'] ?? '';
    $item = $_POST['item'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 1);
    $other = isset($_POST['other']) ? substr($_POST['other'], 0, 25) : '';
   
    try {
        if ($item === 'other' && $other) {
            $item_name = $other;
            $suggested = 1;
            $stmt = $pdo->prepare("INSERT INTO suggestions (name, category_id, user_id) VALUES (?, NULL, ?)");
            $stmt->execute([$item_name, $_SESSION['user_id']]);
        } else {
            $item_name = $item;
            $suggested = 0;
        }
       
        $stmt = $pdo->prepare("INSERT INTO requests (location, item_name, suggested, user_id, quantity, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$location, $item_name, $suggested, $_SESSION['user_id'], $quantity]);
       
        $success = 'Request submitted successfully';
        header('Location: dashboard.php');
        exit;
    } catch (PDOException $e) {
        $error = 'Error submitting request: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Request - Supply Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
                        <a class="nav-link" href="suggest_item.php">Suggest New Item</a>
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
        <h2>Submit Supply Request</h2>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="location" class="form-label">Location</label>
                <select class="form-select" id="location" name="location" required>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo $loc; ?>"><?php echo $loc; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="item" class="form-label">Item</label>
                <select class="form-select js-example-basic-single" id="item" name="item" required>
                </select>
            </div>
            <div class="mb-3" id="other_field" style="display:none;">
                <label for="other" class="form-label">Other Item (max 25 chars)</label>
                <input type="text" class="form-control" id="other" name="other" maxlength="25">
            </div>
            <div class="mb-3">
                <label for="quantity" class="form-label">Quantity</label>
                <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" required>
            </div>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
        <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
    </div>
    <footer class="bg-primary text-white text-center py-3">
        <p>&copy; 2024 Supply Dashboard. All rights reserved.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#item').select2({
                placeholder: 'Select an item',
                allowClear: true,
                ajax: {
                    url: 'api_items.php',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0
            });
            $('#item').append(new Option('Other', 'other', false, false));
            $('#item').val(null).trigger('change');
            $('#item').on('change', function() {
                if ($(this).val() === 'other') {
                    $('#other_field').show();
                } else {
                    $('#other_field').hide();
                }
            });
        });
    </script>
</body>
</html>
