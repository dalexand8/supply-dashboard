<?php
// delete_request.php
require 'includes/auth.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

if (!isset($_POST['request_id']) || !is_numeric($_POST['request_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
    exit;
}

$request_id = (int)$_POST['request_id'];

try {
    // Get current status before delete (for count update)
    $stmt = $pdo->prepare("SELECT status FROM requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $current_status = $stmt->fetchColumn();

    if ($current_status === false) {
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        exit;
    }

    // Delete
    $stmt = $pdo->prepare("DELETE FROM requests WHERE id = ?");
    $stmt->execute([$request_id]);

    // Get updated counts
    $stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM requests GROUP BY status");
    $counts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $counts[$row['status']] = (int)$row['cnt'];
    }
    $total = array_sum($counts);

    echo json_encode([
        'success' => true,
        'counts'  => $counts,
        'total'   => $total
    ]);
} catch (Exception $e) {
    error_log("Delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database error'
    ]);
}

exit;