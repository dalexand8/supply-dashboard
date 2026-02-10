<?php
// update_request_qty.php - Fixed so admins can update ANY request

require 'includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Clean any stray output
ob_start();
if (ob_get_length()) ob_end_clean();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Not logged in']));
}

$request_id = (int)($_POST['request_id'] ?? 0);
$quantity   = (int)($_POST['quantity'] ?? 0);

if ($request_id <= 0 || $quantity < 1) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid request or quantity']));
}

try {
    // Fetch request details
    $stmt = $pdo->prepare("
        SELECT user_id, status, quantity 
        FROM requests 
        WHERE id = ?
    ");
    $stmt->execute([$request_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        die(json_encode(['success' => false, 'error' => 'Request not found']));
    }

    $is_admin   = !empty($_SESSION['is_admin']);
    $is_owner   = (int)$row['user_id'] === (int)$_SESSION['user_id'];
    $is_pending = $row['status'] === 'Pending';

    // Admins can ALWAYS update any request
    if ($is_admin) {
        // Admins allowed - no further checks
    }
    // Regular users only allowed if it's their own pending request
    else {
        if (!$is_owner || !$is_pending) {
            http_response_code(403);
            die(json_encode(['success' => false, 'error' => 'Not authorized or not pending']));
        }
    }

    // Perform the update
    $stmt = $pdo->prepare("UPDATE requests SET quantity = ? WHERE id = ?");
    $stmt->execute([$quantity, $request_id]);

    // Recalculate counts for dashboard badges
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM requests GROUP BY status");
    $counts = [];
    $total = 0;
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $counts[$r['status']] = (int)$r['count'];
        $total += (int)$r['count'];
    }

    die(json_encode([
        'success'      => true,
        'old_quantity' => (int)$row['quantity'],
        'counts'       => $counts,
        'total'        => $total
    ], JSON_NUMERIC_CHECK));

} catch (Exception $e) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'error'   => 'Database error'
    ]));
}