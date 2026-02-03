<?php
require 'includes/auth.php';


$current_page = basename(__FILE__);

// Include notify.php at top with error check
if (file_exists('includes/notify.php')) {
    include 'includes/notify.php';
} else {
    error_log('includes/notify.php not found - notifications disabled');
    function notify_admins($subject, $body_html) {
        // Fallback dummy function if missing
        error_log("Notification attempted but notify.php missing: $subject");
    }
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$category_options = getOptions($pdo, 'categories');

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['suggest_new_item'])) {
        $name = trim($_POST['name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0) ?: null;
        $variant_name = trim($_POST['variant_name'] ?? '');
        if ($name) {
            try {
                $check = $pdo->prepare("SELECT id FROM items WHERE name = ?");
                $check->execute([$name]);
                if ($check->fetch()) {
                    $error = 'This item already exists.';
                } else {
                    $insert = $pdo->prepare("INSERT INTO suggestions (name, variant_name, category_id, user_id) VALUES (?, ?, ?, ?)");
                    $insert->execute([$name, $variant_name ?: null, $category_id, $_SESSION['user_id']]);
                    $success = 'New item suggestion submitted!';
                    
                    // Safe notification with error check
                    if (function_exists('notify_admins')) {
                        try {
                            $subject = 'New Item Suggestion';
                            $body = "<p>User <strong>" . htmlspecialchars($_SESSION['username']) . "</strong> suggested a new item:</p>";
                            $body .= "<p><strong>" . htmlspecialchars($name) . "</strong>";
                            if ($variant_name) $body .= " (Initial Variant: " . htmlspecialchars($variant_name) . ")";
                            if ($category_id) {
                                $cat_stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                                $cat_stmt->execute([$category_id]);
                                $cat_name = $cat_stmt->fetchColumn();
                                if ($cat_name) $body .= " (Category: " . htmlspecialchars($cat_name) . ")";
                            }
                            $body .= "</p>";
                            notify_admins($subject, $body);
                        } catch (Exception $e) {
                            error_log("Notification failed: " . $e->getMessage());
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        } else {
            $error = 'Item name required';
        }
    } elseif (isset($_POST['suggest_variant'])) {
        $parent_item_id = (int)$_POST['parent_item_id'];
        $variant_name = trim($_POST['variant_name'] ?? '');
        if ($parent_item_id && $variant_name) {
            try {
                $check = $pdo->prepare("SELECT id FROM item_variants WHERE item_id = ? AND name = ?");
                $check->execute([$parent_item_id, $variant_name]);
                if ($check->fetch()) {
                    $error = 'This variant already exists.';
                } else {
                    $insert = $pdo->prepare("INSERT INTO suggestions (parent_item_id, variant_name, user_id) VALUES (?, ?, ?)");
                    $insert->execute([$parent_item_id, $variant_name, $_SESSION['user_id']]);
                    $success = 'Variant suggestion submitted!';
                    
                    // Safe notification with error check
                    if (function_exists('notify_admins')) {
                        try {
                            $pstmt = $pdo->prepare("SELECT name FROM items WHERE id = ?");
                            $pstmt->execute([$parent_item_id]);
                            $parent_name = $pstmt->fetchColumn();
                            $subject = 'New Variant Suggestion';
                            $body = "<p>User <strong>" . htmlspecialchars($_SESSION['username']) . "</strong> suggested a variant:</p>";
                            $body .= "<p><strong>" . htmlspecialchars($variant_name) . "</strong> for item <strong>" . htmlspecialchars($parent_name ?: 'ID ' . $parent_item_id) . "</strong></p>";
                            notify_admins($subject, $body);
                        } catch (Exception $e) {
                            error_log("Notification failed: " . $e->getMessage());
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        } else {
            $error = 'Variant name required';
        }
    }
}

// Item browser (unchanged from your version)
$per_page = 50;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $per_page;
$filter_cat = $_GET['cat'] ?? 'all';

try {
    $count_query = "SELECT COUNT(*) FROM items";
    $count_params = [];
    if ($filter_cat === 'uncategorized') {
        $count_query .= " WHERE category_id IS NULL";
    } elseif ($filter_cat !== 'all' && is_numeric($filter_cat)) {
        $count_query .= " WHERE category_id = ?";
        $count_params[] = (int)$filter_cat;
    }
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $per_page);

    $item_query = "SELECT i.id, i.name, COALESCE(c.name, 'Uncategorized') as category_name 
                   FROM items i 
                   LEFT JOIN categories c ON i.category_id = c.id";
    $item_params = [];
    if ($filter_cat === 'uncategorized') {
        $item_query .= " WHERE i.category_id IS NULL";
    } elseif ($filter_cat !== 'all' && is_numeric($filter_cat)) {
        $item_query .= " WHERE i.category_id = ?";
        $item_params[] = (int)$filter_cat;
    }
    $item_query .= " ORDER BY i.name";
    $item_stmt = $pdo->prepare($item_query);
    $item_stmt->execute($item_params);
    $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $items = [];
    $total_items = 0;
    $total_pages = 1;
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container mt-5">
    <h2>Suggest New Item</h2>
    <p>Suggest a new item (with optional initial variant). Admins will review.</p>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error && strpos($error, 'Database') === false): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="suggest_new_item" value="1">
        <div class="mb-3">
            <label for="name" class="form-label">Item Name</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>
        <div class="mb-3">
            <label for="variant_name" class="form-label">Initial Variant (optional)</label>
            <input type="text" class="form-control" id="variant_name" name="variant_name" maxlength="100">
        </div>
        <div class="mb-3">
            <label for="category_id" class="form-label">Suggested Category (optional)</label>
            <select class="form-select" id="category_id" name="category_id">
                <option value="">Uncategorized</option>
                <?php foreach ($category_options as $opt): ?>
                    <option value="<?php echo $opt['id']; ?>"><?php echo htmlspecialchars($opt['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Submit New Item Suggestion</button>
    </form>
    
    <hr class="my-5">
    
    <h3>Browse Existing Items</h3>
    <p>Check if the item/variant exists. Suggest new variants directly in the table.</p>
    
    <form method="GET" class="mb-3">
        <div class="row g-3">
            <div class="col-auto">
                <label for="cat" class="visually-hidden">Category</label>
                <select class="form-select" id="cat" name="cat" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter_cat === 'all' ? 'selected' : ''; ?>>All Categories</option>
                    <option value="uncategorized" <?php echo $filter_cat === 'uncategorized' ? 'selected' : ''; ?>>Uncategorized</option>
                    <?php foreach ($category_options as $opt): ?>
                        <option value="<?php echo $opt['id']; ?>" <?php echo $filter_cat == $opt['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
    
    <?php if ($error && strpos($error, 'Database') !== false): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php elseif (!empty($items)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Current Variants</th>
                        <th>Add Variant Suggestion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $vstmt = $pdo->prepare("SELECT name FROM item_variants WHERE item_id = ? ORDER BY name");
                        $vstmt->execute([$item['id']]);
                        $variants = $vstmt->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                            <td>
                                <?php if (!empty($variants)): ?>
                                    <?php echo htmlspecialchars(implode(', ', $variants)); ?>
                                <?php else: ?>
                                    <em>None</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="parent_item_id" value="<?php echo $item['id']; ?>">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" name="variant_name" placeholder="New variant" required maxlength="100">
                                        <button type="submit" name="suggest_variant" class="btn btn-sm btn-primary">Suggest</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Item pagination">
                <ul class="pagination justify-content-center">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?cat=<?php echo $filter_cat; ?>&page=<?php echo $current_page - 1; ?>">Previous</a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled"><span class="page-link">Previous</span></li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                            <a class="page-link" href="?cat=<?php echo $filter_cat; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?cat=<?php echo $filter_cat; ?>&page=<?php echo $current_page + 1; ?>">Next</a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled"><span class="page-link">Next</span></li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <p>No items found.</p>
    <?php endif; ?>
    
    <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
</div>

<?php include 'includes/footer.php'; ?>