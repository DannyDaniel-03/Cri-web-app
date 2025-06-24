<?php
require __DIR__ . '/init.php';

// Check if we got a username in the request
if (!isset($_GET['username'])) {
    http_response_code(400);
    echo json_encode(['exists' => false, 'error' => 'No username given']);
    exit;
}

$username = $_GET['username'];

// Connect to the database
$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// See if this username is already taken
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
$stmt->execute([$username]);
$count = $stmt->fetchColumn();

// Reply with a simple JSON result
echo json_encode(['exists' => $count > 0]);
