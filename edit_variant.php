<?php
// edit_variant.php - Update variant name
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['variant_id'])) {
    $variant_id = (int)$_POST['variant_id'];
    $variant_name = trim($_POST['variant_name'] ?? '');
    if ($variant_name) {
        try {
            $stmt = $pdo->prepare("UPDATE item_variants SET name = ? WHERE id = ?");
            $stmt->execute([$variant_name, $variant_id]);
            header('Location: admin.php');
            exit;
        } catch (PDOException $e) {
            error_log("Error updating variant: " . $e->getMessage());
        }
    }
}
header('Location: admin.php');
exit;
?>