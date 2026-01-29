<?php
// delete_my_request.php - User delete own pending requests
session_start();
include 'db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['id'])) {
    $req_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT user_id, status FROM requests WHERE id = ?");
        $stmt->execute([$req_id]);
        $req = $stmt->fetch();
       
        if ($req && $req['user_id'] == $_SESSION['user_id'] && $req['status'] == 'Pending') {
            $delete_stmt = $pdo->prepare("DELETE FROM requests WHERE id = ?");
            $delete_stmt->execute([$req_id]);
            $_SESSION['success'] = 'Request deleted successfully';
        } else {
            $_SESSION['error'] = 'This has already been processed';
        }
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        $_SESSION['error'] = 'Database error';
    }
}
header('Location: dashboard.php');
exit;
?>