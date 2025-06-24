<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

if (!isset($_GET['lat'], $_GET['lon'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing lat/lon parameters']);
    exit;
}

// Grab the location from the request.
$lat = urlencode($_GET['lat']);
$lon = urlencode($_GET['lon']);

$opts = stream_context_create(['http' => ['header' => "User-Agent: " . NWS_USER_AGENT]]);

$url = "https://api.weather.gov/alerts/active?point={$lat},{$lon}";
// Now we actually make the call to the weather service.
// The '@' symbol just tells PHP not to complain loudly if the service is down.
$raw = @file_get_contents($url, false, $opts);
if ($raw === false) {
    echo json_encode(['type' => 'FeatureCollection', 'features' => []]);
    exit;
}
$alertsJson = json_decode($raw, true);

if (!empty($alertsJson['features'])) {
    $stmt = $pdo->prepare(
        // This command says: "Try to add this alert to our records.
        // If we already have an alert with the same ID, don't add a new one.
        // Instead, just update the time we fetched it and the alert details."
        "INSERT INTO nws_active_alerts (id, alert)
         VALUES (:id, :a)
         ON DUPLICATE KEY UPDATE fetched_at = CURRENT_TIMESTAMP, alert = VALUES(alert)"
    );
    foreach ($alertsJson['features'] as $feature) {
        $stmt->execute([
            ':id' => $feature['id'],
            ':a'  => json_encode($feature)
        ]);
    }
}

echo $raw;
?>