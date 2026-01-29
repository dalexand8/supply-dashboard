<?php
// reject_suggestion.php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['sug_id'])) {
    $sug_id = (int)$_GET['sug_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM suggestions WHERE id = ?");
        $stmt->execute([$sug_id]);
    } catch (PDOException $e) {
        error_log("Error rejecting suggestion: " . $e->getMessage());
    }
}
header('Location: admin.php');
exit;
?>
