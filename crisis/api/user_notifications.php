<?php
require __DIR__ . '/../init.php';
// We're telling the browser that this file will send back data in JSON format, not a webpage.
header('Content-Type: application/json');

// This is the container for our final results. We'll sort notifications into two buckets:
// 'popup' for urgent, new alerts, and 'list' for the general notification history.
$out = ['popup' => [], 'list' => []];

// Return empty if not authenticated
if (!isset($_SESSION['uid'])) {
    echo json_encode($out);
    exit;
}

$uid = (int) $_SESSION['uid'];

// Fetch notifications for this user where the alert was created within the last 24 hours
$res = $mysqli->query(
    "SELECT
         n.id,
         a.id        AS alert_id,
         a.title,
         COALESCE(n.message, a.message) AS message,
         a.lat,
         a.lng,
         a.severity,
         a.created_at AS alert_created_at,
         n.seen
     FROM notifications n
     JOIN alerts a ON a.id = n.alert_id
     WHERE n.user_id = $uid
       AND a.created_at >= DATE_SUB(NOW(), INTERVAL 5 HOUR)
       AND n.seen = 0
     ORDER BY a.created_at DESC"
);

$now = new DateTime();
while ($r = $res->fetch_assoc()) {
    $alertCreated = new DateTime($r['alert_created_at']);
    $ageSeconds = $now->getTimestamp() - $alertCreated->getTimestamp();

    // Skip anything older than 24 hours
    if ($ageSeconds > 24 * 3600) {
        continue;
    }

    // Show as popup if unseen and within 5 hours
    if ($ageSeconds <= 5 * 3600 && !$r['seen']) {
        $bucket = 'popup';
    } else {
        // Otherwise, list the alert (seen or unseen) within 24 hours
        $bucket = 'list';
    }

    $out[$bucket][] = [
        'id'        => (int)   $r['id'],
        'alert_id'  => (int)   $r['alert_id'],
        'title'     =>          $r['title'],
        'message'   =>          $r['message'],
        'lat'       => (float) $r['lat'],
        'lng'       => (float) $r['lng'],
        'severity'  => (int)   $r['severity']
    ];
}

echo json_encode($out);
?>
