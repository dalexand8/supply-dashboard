<?php
// admin.php - Full updated version with variant management, duplicate checks, and inline errors
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$scroll_to_item = null; // For scrolling on error

// Handle new category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_category_name'])) {
    $new_name = trim($_POST['new_category_name']);
    if ($new_name) {
        try {
            $insert = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $insert->execute([$new_name]);
            $success = 'Category added successfully';
        } catch (PDOException $e) {
            $error = 'Error adding category: ' . $e->getMessage();
        }
    } else {
        $error = 'Name is required';
    }
}

// Handle new item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_item_name'])) {
    $new_name = trim($_POST['new_item_name']);
    $category_id = (int)($_POST['category_id'] ?? 0) ?: null;
    if ($new_name) {
        try {
            $insert = $pdo->prepare("INSERT INTO items (name, category_id) VALUES (?, ?)");
            $insert->execute([$new_name, $category_id]);
            $success = 'Item added successfully';
        } catch (PDOException $e) {
            $error = 'Error adding item: ' . $e->getMessage();
        }
    } else {
        $error = 'Name is required';
    }
}

// Handle add variant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_variant'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $variant_name = trim($_POST['new_variant'] ?? '');
    if ($item_id && $variant_name) {
        try {
            // Case-insensitive duplicate check
            $check = $pdo->prepare("SELECT id FROM item_variants WHERE item_id = ? AND LOWER(name) = LOWER(?)");
            $check->execute([$item_id, $variant_name]);
            if ($check->fetch()) {
                $error = 'This variant already exists for the item (case-insensitive).';
            } else {
                $insert = $pdo->prepare("INSERT INTO item_variants (item_id, name) VALUES (?, ?)");
                $insert->execute([$item_id, $variant_name]);
                $success = 'Variant added successfully.';
            }
        } catch (PDOException $e) {
            $error = 'Error adding variant: ' . $e->getMessage();
        }
    } else {
        $error = 'Variant name required.';
    }
    $scroll_to_item = $item_id; // Scroll to the item on error/success
}

// Fetch suggestions
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.id, 
            s.name, 
            s.variant_name, 
            s.category_id, 
            s.parent_item_id,
            c.name as category_name, 
            u.username 
        FROM suggestions s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN categories c ON s.category_id = c.id 
        WHERE s.approved = 0
    ");
    $stmt->execute();
    $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error loading suggestions: ' . $e->getMessage();
    $suggestions = [];
}

// Fetch categories
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error loading categories: ' . $e->getMessage();
    $categories = [];
}

// Fetch items with pagination
$total_items = 0;
$per_page = 25;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $per_page;

try {
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM items");
    $total_items = $count_stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id ORDER BY i.name LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error loading items: ' . $e->getMessage();
    $items = [];
}
$total_pages = ceil($total_items / $per_page);
$category_options = getOptions($pdo, 'categories');
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container mt-5">
    <h2>Admin Panel</h2>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <h3>Pending Item Suggestions</h3>
    <ul class="list-group mb-5">
        <?php foreach ($suggestions as $sug): ?>
            <?php
            // Fetch parent item name if variant suggestion
            $parent_name = '';
            if ($sug['parent_item_id']) {
                $pstmt = $pdo->prepare("SELECT name FROM items WHERE id = ?");
                $pstmt->execute([$sug['parent_item_id']]);
                $parent_name = $pstmt->fetchColumn();
            }
            ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <?php if ($sug['parent_item_id']): ?>
                        <strong>Variant Suggestion for "<?php echo htmlspecialchars($parent_name ?: 'Unknown Item'); ?>"</strong>: <?php echo htmlspecialchars($sug['variant_name']); ?>
                    <?php else: ?>
                        <strong>New Item Suggestion</strong>: <?php echo htmlspecialchars($sug['name']); ?>
                        <?php if ($sug['variant_name']): ?>
                            (Initial Variant: <?php echo htmlspecialchars($sug['variant_name']); ?>)
                        <?php endif; ?>
                        <?php if ($sug['category_name']): ?>
                            (Category: <?php echo htmlspecialchars($sug['category_name']); ?>)
                        <?php endif; ?>
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
    <form method="POST">
        <div class="mb-3">
            <label for="new_category_name" class="form-label">Category Name</label>
            <input type="text" class="form-control" id="new_category_name" name="new_category_name" required>
        </div>
        <button type="submit" class="btn btn-primary">Add Category</button>
    </form>
    
    <hr class="my-5">
    
    <h3>Items Management</h3>
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Category</th>
                <th>Variants</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <?php
                $vstmt = $pdo->prepare("SELECT id, name FROM item_variants WHERE item_id = ? ORDER BY name");
                $vstmt->execute([$item['id']]);
                $variants = $vstmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <tr id="item-<?php echo $item['id']; ?>" <?php echo ($scroll_to_item == $item['id']) ? 'class="table-warning"' : ''; ?>>
                    <td><?php echo $item['id']; ?></td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                    <td>
                        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                            <?php if (!empty($variants)): ?>
                                <?php foreach ($variants as $v): ?>
                                    <span class="badge bg-secondary fs-6">
                                        <?php echo htmlspecialchars($v['name']); ?>
                                        <button type="button" class="btn btn-sm btn-light border-0 p-0 ms-1" data-bs-toggle="modal" data-bs-target="#editVariantModal<?php echo $v['id']; ?>" title="Edit">✎</button>
                                        <a href="delete_variant.php?id=<?php echo $v['id']; ?>" class="text-white ms-1" onclick="return confirm('Delete variant?');" title="Delete">×</a>
                                    </span>
                                    
                                    <!-- Edit Variant Modal -->
                                    <div class="modal fade" id="editVariantModal<?php echo $v['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-sm">
                                            <div class="modal-content">
                                                <form method="POST" action="edit_variant.php">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Variant</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="variant_id" value="<?php echo $v['id']; ?>">
                                                        <input type="text" class="form-control" name="variant_name" value="<?php echo htmlspecialchars($v['name']); ?>" required>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Update</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <em class="text-muted">None</em>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($scroll_to_item == $item['id'] && $error): ?>
                            <div class="alert alert-danger alert-sm py-1 px-2 mb-2"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                            <div class="input-group input-group-sm w-auto">
                                <input type="text" class="form-control" name="new_variant" placeholder="New variant">
                                <button type="submit" name="add_variant" class="btn btn-success">Add</button>
                            </div>
                        </form>
                    </td>
                    <td>
                        <a href="edit_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning">Edit Item</a>
                        <a href="delete_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete item?');">Delete Item</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
                <tr><td colspan="5">No items found</td></tr>
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
    
    <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
</div>

<!-- Scroll to item on error -->
<script>
    <?php if ($scroll_to_item): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const element = document.getElementById('item-<?php echo $scroll_to_item; ?>');
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    <?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>