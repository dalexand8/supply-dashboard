<?php
// delete_item.php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['id'])) {
    $item_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$item_id]);
    } catch (PDOException $e) {
        error_log("Error deleting item: " . $e->getMessage());
    }
}
header('Location: admin.php');
exit;
?>