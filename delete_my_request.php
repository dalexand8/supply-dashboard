<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
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
    // Get old status + check ownership/Pending
    $stmt = $pdo->prepare("SELECT status FROM requests WHERE id = ? AND user_id = ? AND status = 'Pending'");
    $stmt->execute([$id, $_SESSION['user_id']]);
    $old_status = $stmt->fetchColumn();

    if (!$old_status) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not your Pending request']);
        exit;
    }

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