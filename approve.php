<?php
// approve.php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['sug_id'])) {
    $sug_id = (int)$_GET['sug_id'];
    try {
        $stmt = $pdo->prepare("SELECT name, category_id FROM suggestions WHERE id = ?");
        $stmt->execute([$sug_id]);
        $sug = $stmt->fetch();
       
        if ($sug) {
            $name = $sug['name'];
            $category_id = $sug['category_id'];
            $check = $pdo->prepare("SELECT id FROM items WHERE name = ?");
            $check->execute([$name]);
            if (!$check->fetch()) {
                $insert = $pdo->prepare("INSERT INTO items (name, category_id) VALUES (?, ?)");
                $insert->execute([$name, $category_id]);
            }
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
