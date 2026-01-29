<?php
// export_csv.php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    die('Unauthorized');
}
$table = $_GET['table'] ?? '';
$allowed = ['requests', 'items', 'suggestions', 'login_logs'];
if (!in_array($table, $allowed)) {
    die('Invalid table');
}

$filename = $table . "_export_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Get columns
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    fputcsv($output, $columns);

    // Get data
    $stmt = $pdo->query("SELECT * FROM `$table`");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}
exit;
?>
