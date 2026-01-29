<?php
// backup.php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin'] || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}

// Log backup download
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$log = $pdo->prepare("INSERT INTO login_logs (user_id, username, ip_address, user_agent) VALUES (?, ?, ?, ?)");
$log->execute([$_SESSION['user_id'], $_SESSION['username'], $ip, $agent . ' - DATABASE BACKUP DOWNLOAD']);

$tables = ['users', 'categories', 'items', 'requests', 'suggestions', 'login_logs'];

$return = "-- Supply Dashboard Database Backup (SECURE)
";
$return .= "-- Generated on " . date('Y-m-d H:i:s') . " by " . $_SESSION['username'] . "

";

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $return .= "--
-- Table structure for table `$table`
--

";
        $return .= $row[1] . ";

";

        $stmt = $pdo->query("SELECT * FROM `$table`");
        if ($stmt->rowCount() > 0) {
            $return .= "--
-- Dumping data for table `$table`
--

";
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $return .= "INSERT INTO `$table` VALUES (";
                foreach ($row as $i => $value) {
                    if ($value === null) {
                        $return .= "NULL";
                    } else {
                        $value = addslashes($value);
                        $value = str_replace("
", "\n", $value);
                        $return .= '"' . $value . '"';
                    }
                    if ($i < count($row) - 1) $return .= ', ';
                }
                $return .= ");
";
            }
            $return .= "
";
        }
    } catch (PDOException $e) {
        $return .= "-- Error dumping table `$table`: " . $e->getMessage() . "

";
    }
}

$filename = "supply_dashboard_secure_backup_" . date('Y-m-d') . ".sql";
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $return;
exit;
?>
