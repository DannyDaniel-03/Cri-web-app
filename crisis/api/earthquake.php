<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

// Connect to MySQL via PDO
$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// USGS query parameters
$start  = $_GET['starttime']   ?? date('Y-m-d', strtotime('-1 day'));
$end    = $_GET['endtime']     ?? date('Y-m-d');
$minMag = $_GET['minmagnitude'] ?? '1';
$limit  = $_GET['limit']       ?? '50';

$url = "https://earthquake.usgs.gov/fdsnws/event/1/query?format=geojson"
     . "&starttime={$start}&endtime={$end}&minmagnitude={$minMag}&limit={$limit}";

// Fetch from USGS
$response = @file_get_contents($url);
if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch USGS data']);
    exit;
}

// Decode and persist each earthquake
$data = json_decode($response, true);
$stmt = $pdo->prepare(
    "INSERT INTO earthquakes (id, mag, place, time, lat, lng, url)
     VALUES (:id, :mag, :place, :time, :lat, :lng, :url)
     ON DUPLICATE KEY UPDATE
       mag = VALUES(mag), place = VALUES(place), time = VALUES(time),
       lat = VALUES(lat), lng = VALUES(lng), url = VALUES(url)"
);
foreach ($data['features'] as $feature) {
    $p = $feature['properties'];
    $c = $feature['geometry']['coordinates'];
    $stmt->execute([
        ':id'    => $feature['id'],
        ':mag'   => $p['mag'],
        ':place' => $p['place'],
        ':time'  => date('Y-m-d H:i:s', $p['time'] / 1000),
        ':lat'   => $c[1],
        ':lng'   => $c[0],
        ':url'   => $p['url'],
    ]);
}

echo $response;
?>