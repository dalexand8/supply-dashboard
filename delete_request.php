<?php
require 'includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$request_id = (int)($_POST['request_id'] ?? 0);
if ($request_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM requests WHERE id = ?");
    $stmt->execute([$request_id]);

    // Recalculate counts
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM requests GROUP BY status");
    $counts = [];
    $total = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $counts[$row['status']] = (int)$row['count'];
        $total += (int)$row['count'];
    }

    echo json_encode(['success' => true, 'counts' => $counts, 'total' => $total]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}