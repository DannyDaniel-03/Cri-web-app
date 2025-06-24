<?php
require __DIR__.'/../init.php';
header('Content-Type: application/json');

$alertId = (int)($_GET['alert_id'] ?? 0);
$out = [];

if($alertId){
    $stmt = $mysqli->prepare("
      SELECT s.id, s.name, s.lat, s.lng
        FROM shelters s
        JOIN alert_shelters a ON s.id = a.shelter_id
       WHERE a.alert_id = ?
    ");
    $stmt->bind_param('i', $alertId);
    $stmt->execute();
    $rs = $stmt->get_result();
    while($row = $rs->fetch_assoc()){
        $out[] = $row;
    }
}

echo json_encode($out);
