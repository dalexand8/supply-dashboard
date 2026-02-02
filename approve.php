<?php
// approve.php - Handle item/variant suggestions
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {

$current_page = basename(__FILE__);

    header('Location: login.php');
    exit;
}
if (isset($_GET['sug_id'])) {
    $sug_id = (int)$_GET['sug_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM suggestions WHERE id = ?");
        $stmt->execute([$sug_id]);
        $sug = $stmt->fetch();
       
        if ($sug) {
            if ($sug['parent_item_id']) {
    // Variant add
    $insert = $pdo->prepare("INSERT INTO item_variants (item_id, name) VALUES (?, ?)");
    $insert->execute([$sug['parent_item_id'], $sug['variant_name']]);
} else {
    // New item
    $insert = $pdo->prepare("INSERT INTO items (name, category_id) VALUES (?, ?)");
    $insert->execute([$sug['name'], $sug['category_id']]);
    $item_id = $pdo->lastInsertId();
    if ($sug['variant_name']) {
        $vinsert = $pdo->prepare("INSERT INTO item_variants (item_id, name) VALUES (?, ?)");
        $vinsert->execute([$item_id, $sug['variant_name']]);
    }
}
            // Mark approved
            $update = $pdo->prepare("UPDATE suggestions SET approved = 1 WHERE id = ?");
            $update->execute([$sug_id]);
        }
    } catch (PDOException $e) {
        error_log("Error approving suggestion: " . $e->getMessage());
    }
}
header('Location: admin.php');
exit;
?>