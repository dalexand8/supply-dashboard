<?php
// delete_request.php - Admin only delete request
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['id'])) {
    $req_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM requests WHERE id = ?");
        $stmt->execute([$req_id]);
    } catch (PDOException $e) {
        error_log("Error deleting request: " . $e->getMessage());
    }
}
header('Location: dashboard.php');
exit;
?>