<?php
require 'includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$request_id = (int)($_POST['request_id'] ?? 0);
$quantity   = (int)($_POST['quantity'] ?? 0);

if ($request_id < 1 || $quantity < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    // Verify it's the user's request and still pending
    $stmt = $pdo->prepare("
        SELECT quantity, status 
        FROM requests 
        WHERE id = ? AND user_id = ? AND status = 'Pending'
    ");
    $stmt->execute([$request_id, $_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Not authorized or not pending']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE requests SET quantity = ? WHERE id = ?");
    $stmt->execute([$quantity, $request_id]);

    // Return updated counts if you want to keep top counters accurate
    // (optional - you can skip this part if you don't need it)
    // ... recalculate counts and total like in your delete script ...

    echo json_encode([
        'success' => true,
        'old_quantity' => $row['quantity']
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}