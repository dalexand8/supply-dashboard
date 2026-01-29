<?php
// api_items.php
include 'db.php';
$q = $_GET['q'] ?? '';
try {
    $query = "SELECT i.name, c.name as category FROM items i LEFT JOIN categories c ON i.category_id = c.id";
    if ($q) {
        $query .= " WHERE i.name LIKE ?";
    }
    $query .= " ORDER BY category, i.name";
    $stmt = $pdo->prepare($query);
    if ($q) {
        $stmt->execute(['%' . $q . '%']);
    } else {
        $stmt->execute();
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $grouped = [];
    foreach ($rows as $row) {
        $cat = $row['category'] ?? 'Uncategorized';
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }
        $grouped[$cat][] = ['id' => $row['name'], 'text' => $row['name']];
    }
    
    $results = [];
    foreach ($grouped as $cat => $children) {
        $results[] = ['text' => $cat, 'children' => $children];
    }
    
    echo json_encode($results);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
