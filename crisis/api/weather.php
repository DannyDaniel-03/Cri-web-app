<?php
require_once __DIR__ . '/../config.php';
// VERY similar to wx_alerts.php!
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['lat'], $_GET['lon'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing lat/lon parameters']);
    exit;
}
$lat = urlencode($_GET['lat']);
$lon = urlencode($_GET['lon']);

$opts      = stream_context_create([
    'http' => ['header' => "User-Agent: " . NWS_USER_AGENT]
]);
$pointsUrl = "https://api.weather.gov/points/{$lat},{$lon}";
$pointsRes = @file_get_contents($pointsUrl, false, $opts);
if ($pointsRes === false) {
    echo json_encode(['type' => 'FeatureCollection', 'features' => []]);
    exit;
}
$pointsJson = json_decode($pointsRes, true);
if (empty($pointsJson['properties']['forecast'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Invalid NWS points response']);
    exit;
}

$forecastUrl = $pointsJson['properties']['forecast'];
$response    = @file_get_contents($forecastUrl, false, $opts);
if ($response === false) {
    echo json_encode(['type' => 'FeatureCollection', 'features' => []]);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $ins = $pdo->prepare(
        "INSERT INTO weather_forecasts (lat, lng, forecast)
         VALUES (:lat, :lng, :f)
         ON DUPLICATE KEY UPDATE
           fetched_at = CURRENT_TIMESTAMP,
           forecast   = VALUES(f)"
    );
    $ins->execute([
        ':lat' => $_GET['lat'],
        ':lng' => $_GET['lon'],
        ':f'   => $response
    ]);
} catch (Exception $e) {
    error_log("weather.php DB error: " . $e->getMessage());
}

echo $response;
exit;
