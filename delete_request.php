<?php
session_start();
require 'db.php';

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    // Get old status
    $stmt = $pdo->prepare("SELECT status FROM requests WHERE id = ?");
    $stmt->execute([$id]);
    $old_status = $stmt->fetchColumn() ?: 'Pending';

    // Delete
    $delete = $pdo->prepare("DELETE FROM requests WHERE id = ?");
    $delete->execute([$id]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'status' => $old_status]);
    exit;
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Delete failed']);
    exit;
}
?>