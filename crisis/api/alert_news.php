<?php
require_once __DIR__ . '/../init.php';

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC // Add this line
    ]
);

$since = date('Y-m-d H:i:s', strtotime('-2 days'));
$news = [];

// We query all the alerts within the alerts table 
$res = $pdo->query("SELECT id, 'custom' as type, title, message, created_at, lat, lng, severity, radius FROM alerts WHERE created_at >= '$since'");
foreach ($res as $row) {
    $row['source'] = 'Custom Alert'; // This is kept as 'Custom Alert'
    $row['time'] = $row['created_at'];
    $news[] = $row;
}

// We also query all the earthquakes within the earthquakes table.
$res = $pdo->query("SELECT id, 'earthquake' as type, mag, place as title, time as created_at, url FROM earthquakes WHERE time >= '$since'");
foreach ($res as $row) {
    $row['message'] = "Magnitude {$row['mag']} at {$row['title']}";
    $row['source'] = 'USGS Earthquake';
    $row['time'] = $row['created_at'];
    $news[] = $row;
}

// This is for floods, from the flood monitoring data table, in which the way the json is provided is a bit messy.
$res = $pdo->query("SELECT id, 'flood' as type, data, fetched_at as created_at FROM flood_monitoring_data WHERE fetched_at >= '$since'");
foreach ($res as $row) {
    $item = json_decode($row['data'], true);
    $area = $item['eaAreaName'] ?? ($item['floodArea']['county'] ?? '');
    $severity = $item['severity'] ?? '';
    $mainMsg = trim($item['message'] ?? '');
    if ($mainMsg === '') $mainMsg = $item['description'] ?? '';
    if ($mainMsg === '') $mainMsg = $area;
    $title = ($severity ? $severity : 'Flood Alert') . ($area ? " - $area" : '');

    $row['title'] = $title;
    $row['message'] = $mainMsg;
    $row['source'] = 'UK Flood Monitoring';
    $row['time'] = $row['created_at'];
    $news[] = $row;
}

// Lastly we query the weather as the last part of the alert news provided in a json format
$res = $pdo->query("SELECT id, 'weather' as type, alert, fetched_at as created_at FROM nws_active_alerts WHERE fetched_at >= '$since'");
foreach ($res as $row) {
    $alert = json_decode($row['alert'], true);
    $headline = $alert['properties']['headline'] ?? 'Weather Alert';
    $desc = $alert['properties']['description'] ?? '';
    $row['title'] = $headline;
    $row['message'] = $desc;
    $row['source'] = 'NWS Weather';
    $row['time'] = $row['created_at'];
    $news[] = $row;
}

// Sorting is by the one that has happened the most recent
usort($news, function($a, $b) {
    return strtotime($b['time']) <=> strtotime($a['time']);
});

header('Content-Type: application/json');
echo json_encode($news);