<?php
session_start();
require 'db.php';

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM items");
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $per_page);

    $stmt = $pdo->prepare("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id ORDER BY i.name LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Load variants for each item
    foreach ($items as &$item) {
        $vstmt = $pdo->prepare("SELECT id, name FROM item_variants WHERE item_id = ? ORDER BY name");
        $vstmt->execute([$item['id']]);
        $item['variants'] = $vstmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'items' => $items,
        'total_items' => $total_items,
        'total_pages' => $total_pages,
        'current_page' => $page
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}