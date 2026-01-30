<?php
// api_variants.php - Fetch variants for selected item
include 'db.php';
$item_name = $_GET['item'] ?? '';
if (empty($item_name)) {
    echo json_encode([]);
    exit;
}
try {
    $stmt = $pdo->prepare("SELECT id, name FROM items WHERE name = ?");
    $stmt->execute([$item_name]);
    $item = $stmt->fetch();
    if (!$item) {
        echo json_encode([]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, name FROM item_variants WHERE item_id = ? ORDER BY name");
    $stmt->execute([$item['id']]);
    $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($variants);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>