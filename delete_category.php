<?php
// delete_category.php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['id'])) {
    $cat_id = (int)$_GET['id'];
    try {
        // Set items in this category to uncategorized
        $update = $pdo->prepare("UPDATE items SET category_id = NULL WHERE category_id = ?");
        $update->execute([$cat_id]);
        
        // Delete the category
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$cat_id]);
    } catch (PDOException $e) {
        error_log("Error deleting category: " . $e->getMessage());
    }
}
header('Location: admin.php');
exit;
?>