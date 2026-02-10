<?php
// edit_category.php - Fixed + redirects after success

session_start();
require 'db.php';

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: dashboard.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin.php');
    exit;
}

$cat_id = (int)$_GET['id'];
$error = '';
$success = '';

// Fetch category
try {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$cat_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        header('Location: admin.php');
        exit;
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if ($name) {
        try {
            $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->execute([$name, $cat_id]);

            // Success → redirect to admin panel (most natural UX)
            header('Location: admin.php');
            exit;

            // If you prefer to stay on the page and show success:
            // $success = 'Category updated successfully';
            // $category['name'] = $name; // update displayed value
        } catch (PDOException $e) {
            $error = 'Error updating category: ' . $e->getMessage();
        }
    } else {
        $error = 'Category name is required';
    }
}

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-xl-8">

            <h1 class="h2 mb-4">Admin Panel – Edit Category</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card mb-4 shadow">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-tags me-2"></i>
                    Edit Category: <?= htmlspecialchars($category['name']) ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-4">
                            <label for="name" class="form-label small fw-bold">Category Name</label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?= htmlspecialchars($category['name']) ?>" required autofocus>
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i> Update Category
                            </button>

                            <a href="admin.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Back to Admin Panel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>