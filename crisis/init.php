<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

session_start();
// Security measure vs possible rank removal mid action
$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
if (isset($_SESSION['uid'])) {
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['uid']]);
        $dbRole = $stmt->fetchColumn();
        if ($dbRole && $dbRole !== ($_SESSION['role'] ?? null)) {
            $_SESSION['role'] = $dbRole;
        }
    } catch (Exception $e) {
    }
}

define('Maps_API_KEY', 'AIzaSyD4UQwZT80stMevKhySQa7yk1_0WkjE99w'); 

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die('DB connection error: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>