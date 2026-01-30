<?php
// delete_variant.php - Admin delete variant
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['id'])) {
    $variant_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM item_variants WHERE id = ?");
        $stmt->execute([$variant_id]);
    } catch (PDOException $e) {
        error_log("Error deleting variant: " . $e->getMessage());
    }
}
header('Location: admin.php');
exit;
?>