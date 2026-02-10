<?php
// admin.php - FIXED: no accordions, no pagination, kept original feel

session_start();
require 'db.php';

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: dashboard.php');
    exit;
}

$current_page = basename(__FILE__);

// Form handlers
$error = '';
$success = '';
$scroll_to_item = null;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_variant'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $variant_name = trim($_POST['new_variant'] ?? '');
    if ($item_id && $variant_name) {
        try {
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

// Fetch ALL items — no pagination
try {
    $stmt = $pdo->prepare("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id ORDER BY i.name");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error loading items: ' . $e->getMessage();
    $items = [];
}

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-xl-12">

            <h1 class="h2 mb-4">Admin Panel</h1>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Pending Suggestions -->
            <div class="card mb-4 shadow">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-lightbulb-fill me-2"></i> Pending Suggestions
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($suggestions as $sug): ?>
                            <?php
                            $parent_name = '';
                            if ($sug['parent_item_id']) {
                                $pstmt = $pdo->prepare("SELECT name FROM items WHERE id = ?");
                                $pstmt->execute([$sug['parent_item_id']]);
                                $parent_name = $pstmt->fetchColumn() ?: 'Unknown';
                            }
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2">
                                <div>
                                    <?php if ($sug['parent_item_id']): ?>
                                        <strong>Variant for “<?= htmlspecialchars($parent_name) ?>”:</strong> <?= htmlspecialchars($sug['variant_name']) ?>
                                    <?php else: ?>
                                        <strong><?= htmlspecialchars($sug['name']) ?></strong>
                                        <?php if ($sug['variant_name']): ?> (<?= htmlspecialchars($sug['variant_name']) ?>)<?php endif; ?>
                                        <?php if ($sug['category_name']): ?> — <?= htmlspecialchars($sug['category_name']) ?><?php endif; ?>
                                    <?php endif; ?>
                                    <small class="text-muted ms-2">by <?= htmlspecialchars($sug['username']) ?></small>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <a href="approve.php?sug_id=<?= $sug['id'] ?>" class="btn btn-success">Approve</a>
                                    <a href="reject_suggestion.php?sug_id=<?= $sug['id'] ?>" class="btn btn-outline-secondary" onclick="return confirm('Reject?');">Reject</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($suggestions)): ?>
                            <li class="list-group-item text-center text-muted py-3">No pending suggestions</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Categories -->
            <div class="card mb-4 shadow">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-tags me-2"></i> Categories
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" name="new_category_name" placeholder="New category name" required>
                            <button class="btn btn-primary" type="submit">Add</button>
                        </div>
                    </form>

                    <table class="table table-dark table-striped table-sm mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><?= $cat['id'] ?></td>
                                    <td><?= htmlspecialchars($cat['name']) ?></td>
                                    <td class="text-end">
                                        <a href="edit_category.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="delete_category.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($categories)): ?>
                                <tr><td colspan="3" class="text-center py-3 text-muted">No categories</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add New Item -->
            <div class="card mb-4 shadow">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-plus-circle me-2"></i> Add New Item
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label small mb-1">Item Name</label>
                                <input type="text" class="form-control form-control-sm" name="new_item_name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Category</label>
                                <select class="form-select form-select-sm" name="category_id">
                                    <option value="">Uncategorized</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-sm w-100">Add Item</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Items Management - ALL items, no pagination -->
            <div class="card mb-4 shadow">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-box-seam me-2"></i> Items Management (<?= count($items) ?> total)
                </div>
                <div class="card-body">
                    <table class="table table-dark table-striped align-middle">
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
                                <tr id="item-<?php echo $item['id']; ?>">
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
                                            <div class="alert alert-danger py-1 px-2 mb-2"><?php echo htmlspecialchars($error); ?></div>
                                        <?php endif; ?>
                                        
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <div class="input-group input-group-sm w-auto">
                                                <input type="text" class="form-control" name="new_variant" placeholder="New variant" required>
                                                <button type="submit" name="add_variant" class="btn btn-success">Add</button>
                                            </div>
                                        </form>
                                    </td>
                                    <td>
                                        <a href="edit_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning">Edit Item</a>
                                        <a href="delete_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm bg-secondary" onclick="return confirm('Delete item?');">Delete Item</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="5">No items found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Database Backup -->
            <div class="card mb-4 shadow">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-database me-2"></i> Database Backup
                </div>
                <div class="card-body">
                    <p class="mb-3">Download full database backup (SQL file)</p>
                    <form method="POST" action="backup.php">
                        <button type="submit" class="btn btn-primary">Download Backup</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>