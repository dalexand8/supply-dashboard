<?php
// delete_bulk_requests.php
require 'includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$request_ids = $_POST['request_ids'] ?? '';
if (empty($request_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No IDs provided']);
    exit;
}

$ids = array_map('intval', explode(',', $request_ids));
$ids = array_filter($ids);

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid IDs']);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM requests WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    // Recalculate counts for top bar
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM requests GROUP BY status");
    $counts = [];
    $total = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $counts[$row['status']] = (int)$row['count'];
        $total += (int)$row['count'];
    }

    echo json_encode([
        'success' => true,
        'counts' => $counts,
        'total' => $total
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}