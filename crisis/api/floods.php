<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

// Connect to MySQL
$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Build query parameters
$params = [];
if (isset($_GET['lat'], $_GET['lon'])) {
    $params[] = 'lat=' . urlencode($_GET['lat']);
    $params[] = 'long=' . urlencode($_GET['lon']);
    $params[] = 'dist=50';
}
// This is the base address for the UK's flood monitoring API.
// We'll add our location parameters to the end if we have them.
$url = 'https://environment.data.gov.uk/flood-monitoring/id/floods'
     . (!empty($params) ? '?' . implode('&', $params) : '');

// Fetch flood data
$raw = @file_get_contents($url);
if ($raw === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch flood data']);
    exit;
}
$floodsJson = json_decode($raw, true);

// Persist each flood item
if (!empty($floodsJson['items'])) {
    $stmt = $pdo->prepare(
        // This command says: "Try to add this flood alert to our records.
        // If we already have an alert with the same ID, don't add a new one.
        // Just update the time we fetched it and the alert's data."
        "INSERT INTO flood_monitoring_data (id, data)
         VALUES (:id, :d)
         ON DUPLICATE KEY UPDATE fetched_at = CURRENT_TIMESTAMP, data = VALUES(data)"
    );
    foreach ($floodsJson['items'] as $item) {
        $stmt->execute([
            ':id' => $item['@id'], // The '@id' is the unique ID for the alert from their system.
            ':d'  => json_encode($item)
        ]);
    }
}

// Finally, we send the original, untouched data from the flood service back to the user's browser.
echo $raw;
?>