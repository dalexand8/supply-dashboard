<?php
// suggest_item.php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$category_options = getOptions($pdo, 'categories');

// Suggestion form
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['suggest'])) {
    $name = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0) ?: null;
    if ($name) {
        try {
            $check = $pdo->prepare("SELECT id FROM items WHERE name = ?");
            $check->execute([$name]);
            if ($check->fetch()) {
                $error = 'This item already exists in the list.';
            } else {
                $insert = $pdo->prepare("INSERT INTO suggestions (name, category_id, user_id) VALUES (?, ?, ?)");
                $insert->execute([$name, $category_id, $_SESSION['user_id']]);
                $success = 'Item suggestion submitted for approval!';
            }
        } catch (PDOException $e) {
            $error = 'Error submitting suggestion: ' . $e->getMessage();
        }
    } else {
        $error = 'Item name is required';
    }
}

// Item browser
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

    $item_query = "SELECT i.name, COALESCE(c.name, 'Uncategorized') as category_name 
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
    $all_items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = array_slice($all_items, $offset, $per_page);
} catch (PDOException $e) {
    $error = 'Database error loading items: ' . $e->getMessage();
    $items = [];
    $total_items = 0;
    $total_pages = 1;
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>
    <div class="container mt-5 content">
        <h2>Suggest New Item</h2>
        <p>Suggest a new item to add to the supply list. Admins will review and approve it.</p>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error && strpos($error, 'Database') === false): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="suggest" value="1">
            <div class="mb-3">
                <label for="name" class="form-label">Item Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
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
            <button type="submit" class="btn btn-primary">Submit Suggestion</button>
        </form>
        
        <hr class="my-5">
        
        <h3>Browse Existing Items</h3>
        <p>Check if the item already exists before suggesting. (<?php echo $total_items; ?> total items)</p>
        
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
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
                                <a class="page-link" href="?cat=<?php echo urlencode($filter_cat); ?>&page=<?php echo $current_page - 1; ?>">Previous</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">Previous</span></li>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $current_page - 2);
                        $end = min($total_pages, $current_page + 2);
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?cat=<?php echo urlencode($filter_cat); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?cat=<?php echo urlencode($filter_cat); ?>&page=<?php echo $current_page + 1; ?>">Next</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">Next</span></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <p>No items found in this category.</p>
        <?php endif; ?>
        
        <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
    </div>
    <?php include 'includes/footer.php'; ?>