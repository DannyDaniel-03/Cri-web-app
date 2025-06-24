<?php
// This is some fancy math (the Haversine formula) to calculate the real-world distance
// between two points on a globe, taking the curve of the Earth into account.
function haversine($lat1, $lon1, $lat2, $lon2){
    $R = 6371000;
    // We have to convert our latitude and longitude from degrees to radians for the math to work.
    $phi1 = deg2rad($lat1);
    $phi2 = deg2rad($lat2);
    $dphi = deg2rad($lat2 - $lat1);
    $dlambda = deg2rad($lon2 - $lon1);
    $a = sin($dphi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dlambda / 2) ** 2;
    return 2 * $R * asin(sqrt($a));
}

// This function checks for any recent alerts near a specific user.
// We probably run this when a user logs in or updates their location.
function queue_notifications_for_user($mysqli, $user_id, $userLat, $userLng){
    $fiveAgo = (new DateTime('-24 hours'))->format('Y-m-d H:i:s');
    $sql = "SELECT id, lat, lng, radius FROM alerts WHERE created_at >= '$fiveAgo'";
    $rs  = $mysqli->query($sql);
    // Each alert, we check if it has a radius, the distance between it and the user, and see if it shoul be viewed or not.
    while($a = $rs->fetch_assoc()){
        $dist   = haversine($userLat, $userLng, $a['lat'], $a['lng']);
        $radius = $a['radius'] ?? 3000;
        if($dist <= ($radius + 3000)){
            $aid = (int)$a['id'];
            $mysqli->query("INSERT IGNORE INTO notifications(user_id, alert_id) VALUES($user_id, $aid)");
        }
    }
}

// This function does the opposite: when a new alert is created, it finds all the users who should be notified.
// This is how we send out a mass notification to everyone in an area.
function queue_notifications_for_alert($mysqli, $alertId, $alertLat, $alertLng, $radiusM, $message) {
    $users_result = $mysqli->query('SELECT id, lat, lng FROM users WHERE lat IS NOT NULL AND lng IS NOT NULL');

    $notification_stmt = $mysqli->prepare(
        'INSERT INTO notifications (user_id, alert_id, message) VALUES (?, ?, ?)'
    );
    // same idea as previous function, sees the distance if its appropriate
    while ($user = $users_result->fetch_assoc()) {
        $distance = haversine($user['lat'], $user['lng'], $alertLat, $alertLng);

        if ($distance <= $radiusM + 3000) {
            $notification_stmt->bind_param('iis', $user['id'], $alertId, $message);
            $notification_stmt->execute();
        }
    }
}

// UNUSED -- remember to delete!!
function nearest_city($lat,$lng,$country){
    $url = 'https://secure.geonames.org/findNearbyPlaceNameJSON?'.
           http_build_query([
               'lat'=>$lat,'lng'=>$lng,'country'=>$country,
               'cities'=>'cities15000','maxRows'=>1,'username'=>'shuka'
           ]);
    $json = @file_get_contents($url);
    if($json){
        $d = json_decode($json,true);
        return $d['geonames'][0]['name'] ?? '';
    }
    return '';
}
?>