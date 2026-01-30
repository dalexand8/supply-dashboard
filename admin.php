<?php
// admin.php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}
$suggestions = [];
$categories = [];
$items = [];
$total_items = 0;
$per_page = 25;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $per_page;
$error = '';
$success_category = '';
$error_category = '';
$success_item = '';
$error_item = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_category_name'])) {
    $new_name = trim($_POST['new_category_name']);
    if ($new_name) {
        try {
            $insert = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $insert->execute([$new_name]);
            $success_category = 'Category added successfully';
        } catch (PDOException $e) {
            $error_category = 'Error adding category: ' . $e->getMessage();
        }
    } else {
        $error_category = 'Name is required';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_item_name'])) {
    $new_name = trim($_POST['new_item_name']);
    $category_id = (int)($_POST['category_id'] ?? 0) ?: null;
    if ($new_name) {
        try {
            $insert = $pdo->prepare("INSERT INTO items (name, category_id) VALUES (?, ?)");
            $insert->execute([$new_name, $category_id]);
            $success_item = 'Item added successfully';
        } catch (PDOException $e) {
            $error_item = 'Error adding item: ' . $e->getMessage();
        }
    } else {
        $error_item = 'Name is required';
    }
}
try {
    $stmt = $pdo->prepare("SELECT s.id, s.name, s.category_id, c.name as category_name, u.username 
                           FROM suggestions s 
                           JOIN users u ON s.user_id = u.id 
                           LEFT JOIN categories c ON s.category_id = c.id 
                           WHERE s.approved = 0");
    $stmt->execute();
    $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM items");
    $total_items = $count_stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id ORDER BY i.name LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
$total_pages = ceil($total_items / $per_page);
$category_options = getOptions($pdo, 'categories');
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>
    <div class="container mt-5 content">
        <h2>Admin Panel</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
       <h3>Pending Item Suggestions</h3>
<ul class="list-group mb-5">
    <?php foreach ($suggestions as $sug): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <?php echo htmlspecialchars($sug['name']); ?>
                <?php if ($sug['category_name']): ?>
                    (Category: <?php echo htmlspecialchars($sug['category_name']); ?>)
                <?php endif; ?>
                <small class="text-muted"> - Suggested by <?php echo htmlspecialchars($sug['username']); ?></small>
            </div>
            <div>
                <a href="approve.php?sug_id=<?php echo $sug['id']; ?>" class="btn btn-sm btn-success me-2">Approve</a>
                <a href="reject_suggestion.php?sug_id=<?php echo $sug['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject this suggestion?');">Reject</a>
            </div>
        </li>
    <?php endforeach; ?>
    <?php if (empty($suggestions)): ?>
        <li class="list-group-item">No pending item suggestions</li>
    <?php endif; ?>
</ul>
        
        <h3>Categories Management</h3>
        <table class="table table-striped mb-4">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?php echo $cat['id']; ?></td>
                        <td><?php echo htmlspecialchars($cat['name']); ?></td>
                        <td>
                            <a href="edit_category.php?id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                            <a href="delete_category.php?id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure? Items in this category will be uncategorized.');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="3">No categories found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <h4>Add New Category</h4>
        <?php if ($success_category): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_category); ?></div>
        <?php endif; ?>
        <?php if ($error_category): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_category); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="new_category_name" class="form-label">Category Name</label>
                <input type="text" class="form-control" id="new_category_name" name="new_category_name" required>
            </div>
            <button type="submit" class="btn btn-primary">Add Category</button>
        </form>
        
        <hr class="my-5">
        
        <h3>Items Management</h3>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo $item['id']; ?></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                        <td>
                            <a href="edit_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                            <a href="delete_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure? This may affect existing requests.');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                    <tr><td colspan="4">No items found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Items pagination">
                <ul class="pagination justify-content-center">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $current_page - 1; ?>">Previous</a></li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php if ($i == $current_page) echo 'active'; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                    <?php endfor; ?>
                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $current_page + 1; ?>">Next</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
        
        <h4>Add New Item</h4>
        <?php if ($success_item): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_item); ?></div>
        <?php endif; ?>
        <?php if ($error_item): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_item); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="new_item_name" class="form-label">Item Name</label>
                <input type="text" class="form-control" id="new_item_name" name="new_item_name" required>
            </div>
            <div class="mb-3">
                <label for="category_id" class="form-label">Category</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="">Uncategorized</option>
                    <?php foreach ($category_options as $opt): ?>
                        <option value="<?php echo $opt['id']; ?>"><?php echo htmlspecialchars($opt['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Add Item</button>
        </form>
        
        <hr class="my-5">
        
        <h3>Export & Backup Tools</h3>
        <div class="row g-3 mb-3">
            <div class="col-auto">
                <a href="export_csv.php?table=requests" class="btn btn-info">Export Requests to CSV</a>
            </div>
            <div class="col-auto">
                <a href="export_csv.php?table=items" class="btn btn-info">Export Items to CSV</a>
            </div>
            <div class="col-auto">
                <a href="export_csv.php?table=suggestions" class="btn btn-info">Export Suggestions to CSV</a>
            </div>
            <div class="col-auto">
                <a href="export_csv.php?table=login_logs" class="btn btn-info">Export Login Logs to CSV</a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-auto">
                <form method="POST" action="backup.php" onsubmit="return confirm('Download full database backup? This includes all data (hashed passwords, etc.).');">
                    <button type="submit" class="btn btn-warning">Download Full Database Backup (.sql)</button>
                </form>
            </div>
        </div>
        
        <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
    </div>
   <?php include 'includes/footer.php'; ?>