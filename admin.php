<?php
require 'includes/auth.php';

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
    $scroll_to_item = $item_id;
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

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <h1 class="h2 mb-4">Admin Panel</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Pending Item Suggestions - OUTSIDE accordion -->
    <div class="card mb-5">
        <div class="card-header bg-primary text-white fw-bold">
            <i class="bi bi-lightbulb me-2"></i> Pending Item Suggestions
        </div>
        <div class="card-body">
            <ul class="list-group">
                <?php foreach ($suggestions as $sug): ?>
                    <?php
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
                            <a href="reject_suggestion.php?sug_id=<?php echo $sug['id']; ?>" class="btn btn-sm bg-secondary" onclick="return confirm('Reject this suggestion?');">Reject</a>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($suggestions)): ?>
                    <li class="list-group-item">No pending item suggestions</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Accordion for the rest (independent panels, all open) -->
    <div class="accordion" id="adminAccordion">
        <!-- Categories Management & Add New Category -->
       <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCategories" aria-expanded="false" aria-controls="collapseCategories">
                <i class="bi bi-tags me-2"></i> Categories Management & Add New Category
            </button>
        </h2>
        <div id="collapseCategories" class="accordion-collapse collapse">
            <div class="accordion-body">
                    <h4 class="mb-3">Add New Category</h4>
                    <form method="POST" class="mb-4">
                        <div class="input-group">
                            <input type="text" class="form-control" name="new_category_name" placeholder="Category name" required>
                            <button type="submit" class="btn btn-primary">Add Category</button>
                        </div>
                    </form>

                    <h4>Current Categories</h4>
                    <table class="table table-dark table-striped">
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
                                        <a href="delete_category.php?id=<?php echo $cat['id']; ?>" class="btn btn-sm bg-secondary" onclick="return confirm('Are you sure? Items in this category will be uncategorized.');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($categories)): ?>
                                <tr><td colspan="3">No categories found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Items Management with AJAX Pagination (10 rows tall) -->
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseItems" aria-expanded="false" aria-controls="collapseItems">
                <i class="bi bi-box-seam me-2"></i> Items Management (<span id="items-total">0</span> total)
            </button>
        </h2>
        <div id="collapseItems" class="accordion-collapse collapse">
            <div class="accordion-body">
                    <div id="items-table-container">
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
                            <tbody id="items-tbody">
                                <tr><td colspan="5">Loading items...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <nav aria-label="Items pagination" id="items-pagination" class="d-none mt-3">
                        <ul class="pagination justify-content-center" id="pagination-ul">
                            <!-- Filled by JS -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Add New Item -->
      <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAddItem" aria-expanded="false" aria-controls="collapseAddItem">
                <i class="bi bi-plus-circle me-2"></i> Add New Item
            </button>
        </h2>
        <div id="collapseAddItem" class="accordion-collapse collapse">
            <div class="accordion-body">
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
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Item</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Database Backup -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBackup" aria-expanded="false" aria-controls="collapseBackup">
                    <i class="bi bi-database-down me-2"></i> Database Backup
                </button>
            </h2>
            <div id="collapseBackup" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <p class="mb-3">Create a full backup of the database (SQL file download).</p>
                    <form method="POST" action="backup.php">
                        <button type="submit" class="btn btn-primary">Download Backup Now</button>
                    </form>
                    <small class="text-muted d-block mt-3">Backup includes all tables (users, requests, items, variants, categories, suggestions, logs).</small>
                </div>
            </div>
        </div>
<script>
    const itemsTbody = document.getElementById('items-tbody');
    const itemsTotal = document.getElementById('items-total');
    const paginationUl = document.getElementById('pagination-ul');
    const paginationNav = document.getElementById('items-pagination');

    function loadItems(page = 1) {
        fetch(`api_items_pagination.php?page=${page}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    itemsTbody.innerHTML = `<tr><td colspan="5">Error: ${data.error}</td></tr>`;
                    return;
                }

                itemsTotal.textContent = data.total_items;

                let rows = '';
                if (data.items.length === 0) {
                    rows = '<tr><td colspan="5">No items found</td></tr>';
                } else {
                    data.items.forEach(item => {
                        let variantsHtml = '<div class="d-flex flex-wrap gap-2 align-items-center mb-2">';
                        if (item.variants.length > 0) {
                            item.variants.forEach(v => {
                                variantsHtml += `<span class="badge bg-secondary fs-6">
                                    ${v.name}
                                    <button type="button" class="btn btn-sm btn-light border-0 p-0 ms-1" data-bs-toggle="modal" data-bs-target="#editVariantModal${v.id}" title="Edit">✎</button>
                                    <a href="delete_variant.php?id=${v.id}" class="text-white ms-1" onclick="return confirm('Delete variant?');" title="Delete">×</a>
                                </span>`;
                                variantsHtml += `<div class="modal fade" id="editVariantModal${v.id}" tabindex="-1">
                                    <div class="modal-dialog modal-sm">
                                        <div class="modal-content">
                                            <form method="POST" action="edit_variant.php">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Variant</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="variant_id" value="${v.id}">
                                                    <input type="text" class="form-control" name="variant_name" value="${v.name}" required>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Update</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>`;
                            });
                        } else {
                            variantsHtml += '<em class="text-muted">None</em>';
                        }
                        variantsHtml += '</div>';

                        variantsHtml += `<form method="POST" class="d-inline">
                            <input type="hidden" name="item_id" value="${item.id}">
                            <div class="input-group input-group-sm w-auto">
                                <input type="text" class="form-control" name="new_variant" placeholder="New variant">
                                <button type="submit" name="add_variant" class="btn btn-success">Add</button>
                            </div>
                        </form>`;

                        rows += `<tr id="item-${item.id}">
                            <td>${item.id}</td>
                            <td>${item.name}</td>
                            <td>${item.category_name || 'Uncategorized'}</td>
                            <td>${variantsHtml}</td>
                            <td>
                                <a href="edit_item.php?id=${item.id}" class="btn btn-sm btn-warning">Edit Item</a>
                                <a href="delete_item.php?id=${item.id}" class="btn btn-sm bg-secondary" onclick="return confirm('Delete item?');">Delete Item</a>
                            </td>
                        </tr>`;
                    });
                }

                // Pad to 10 rows for consistent height (no jump on last page)
                const currentCount = data.items.length || 0;
                for (let i = currentCount; i < 10; i++) {
                    rows += '<tr class="table-placeholder"><td colspan="5">&nbsp;</td></tr>';
                }

                itemsTbody.innerHTML = rows;

                // Pagination
                if (data.total_pages > 1) {
                    let pagination = '';
                    if (data.current_page > 1) {
                        pagination += `<li class="page-item"><a class="page-link" href="#" data-page="1">First</a></li>`;
                        pagination += `<li class="page-item"><a class="page-link" href="#" data-page="${data.current_page - 1}">Previous</a></li>`;
                    }

                    let start = Math.max(1, data.current_page - 2);
                    let end = Math.min(data.total_pages, data.current_page + 2);
                    for (let i = start; i <= end; i++) {
                        pagination += `<li class="page-item ${i == data.current_page ? 'active' : ''}">
                            <a class="page-link" href="#" data-page="${i}">${i}</a>
                        </li>`;
                    }

                    if (data.current_page < data.total_pages) {
                        pagination += `<li class="page-item"><a class="page-link" href="#" data-page="${data.current_page + 1}">Next</a></li>`;
                        pagination += `<li class="page-item"><a class="page-link" href="#" data-page="${data.total_pages}">Last</a></li>`;
                    }

                    paginationUl.innerHTML = pagination;
                    paginationNav.classList.remove('d-none');
                } else {
                    paginationNav.classList.add('d-none');
                }
            })
            .catch(err => {
                itemsTbody.innerHTML = '<tr><td colspan="5">Load error</td></tr>';
                console.error(err);
            });
    }

    loadItems(1);

    paginationUl.addEventListener('click', (e) => {
        if (e.target.tagName === 'A' && e.target.dataset.page) {
            e.preventDefault();
            loadItems(e.target.dataset.page);
        }
    });
</script>

<style>
    /* Placeholder rows match normal height (adjust if needed) */
    .table-placeholder td {
        height: 85px; /* Tweak this to match your row height */
        padding: 0;
    }
</style>

<?php include 'includes/footer.php'; ?>