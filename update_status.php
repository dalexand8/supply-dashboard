<?php
// update_status.php

require 'includes/auth.php';
include 'includes/notify.php';  // contains both notify_admins() and notify_user()

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

if (!isset($_POST['request_id']) || !isset($_POST['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$request_id = (int)$_POST['request_id'];
$new_status = trim($_POST['status']);

$allowed = ['Pending', 'Approved', 'Ordered', 'Backordered', 'Acknowledged', 'Fulfilled', 'Denied'];
if (!in_array($new_status, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

try {
    // Get current request details + requester info
    $stmt = $pdo->prepare("
        SELECT 
            r.status, 
            r.item_name, 
            r.quantity, 
            r.user_id, 
            u.email AS requester_email, 
            u.username AS requester_name
        FROM requests r
        JOIN users u ON r.user_id = u.id
        WHERE r.id = ?
    ");
    $stmt->execute([$request_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        exit;
    }

    $old_status        = $row['status'];
    $requester_user_id = (int)$row['user_id'];
    $requester_email   = $row['requester_email'] ?? '';
    $requester_name    = $row['requester_name'] ?? 'User';
    $item_name         = $row['item_name'];
    $quantity          = $row['quantity'];

    // Current logged-in user
    $current_user_id = (int)($_SESSION['user_id'] ?? 0);

    // Update the status
    $stmt = $pdo->prepare("UPDATE requests SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $request_id]);

    // Notify the requester ONLY if:
    // - status actually changed
    // - requester is different from the person making the change
    // - we have a valid email
    if ($old_status !== $new_status &&
        $requester_user_id > 0 &&
        $requester_user_id !== $current_user_id &&
        !empty($requester_email) &&
        filter_var($requester_email, FILTER_VALIDATE_EMAIL)) {

        $subject = "Your supply request status updated";
        $body = "<p>Hello {$requester_name},</p>";
        $body .= "<p>Your request for <strong>{$item_name}</strong> (Qty: {$quantity}) has been updated:</p>";
        $body .= "<ul>";
        $body .= "<li><strong>Old status:</strong> {$old_status}</li>";
        $body .= "<li><strong>New status:</strong> <strong style=\"color: #0d6efd;\">{$new_status}</strong></li>";
        $body .= "</ul>";
        $body .= "<p>Request ID: #{$request_id}</p>";
        $body .= "<p>Updated by: " . htmlspecialchars($_SESSION['username'] ?? 'Admin') . "</p>";
        $body .= "<p>You can view your request on the <a href=\"https://{$_SERVER['HTTP_HOST']}/dashboard.php\">dashboard</a>.</p>";

        notify_user($requester_email, $subject, $body);
    }

    // Get updated counts for frontend
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM requests GROUP BY status");
    $status_counts = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status_counts[$r['status']] = (int)$r['count'];
    }

    $total = array_sum($status_counts);

    echo json_encode([
        'success'     => true,
        'new_status'  => $new_status,
        'counts'      => $status_counts,
        'total'       => $total
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database error: ' . $e->getMessage()
    ]);
}

exit;