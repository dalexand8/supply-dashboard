<?php
// delete_request.php

// Prevent any output before headers
ob_start();
header('Content-Type: application/json; charset=utf-8');

// Discard anything that was accidentally output
if (ob_get_length()) {
    ob_end_clean();
}

require 'includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Not logged in']));
}

$request_id = (int)($_POST['request_id'] ?? 0);

if ($request_id <= 0) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid request ID']));
}

try {
    // Check permission
    $stmt = $pdo->prepare("SELECT user_id, status FROM requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        die(json_encode(['success' => false, 'error' => 'Request not found']));
    }

    $is_owner   = (int)$row['user_id'] === (int)$_SESSION['user_id'];
    $is_pending = $row['status'] === 'Pending';
    $is_admin   = !empty($_SESSION['is_admin']);

    if (!$is_admin && (!$is_owner || !$is_pending)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'You can only delete your own pending requests']));
    }

    // Delete
    $stmt = $pdo->prepare("DELETE FROM requests WHERE id = ?");
    $stmt->execute([$request_id]);

    // Counts
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM requests GROUP BY status");
    $counts = [];
    $total = 0;
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $counts[$r['status']] = (int)$r['count'];
        $total += (int)$r['count'];
    }

    // Always 200 on success
    http_response_code(200);
    die(json_encode([
        'success' => true,
        'counts'  => $counts,
        'total'   => $total
    ], JSON_THROW_ON_ERROR | JSON_NUMERIC_CHECK));

} catch (Throwable $e) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'error'   => 'Server error'
    ], JSON_THROW_ON_ERROR));
}