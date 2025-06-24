<?php
require __DIR__ . '/../init.php';
header('Content-Type: application/json');

// Return empty if not authenticated
if (!isset($_SESSION['uid'])) {
    echo json_encode([]);
    exit;
}

$uid = (int) $_SESSION['uid'];

// This time, we'll use a "prepared statement", which is a very secure way to ask the database for information.
// It helps prevent common types of hacking.
$stmt = $mysqli->prepare(
    'SELECT
	a.id,
        a.title,
        a.message,
        a.severity,
        a.lat,
        a.lng,
        n.created_at,
        n.seen
     FROM notifications n
     JOIN alerts a ON a.id = n.alert_id
     WHERE n.user_id = ?
     AND a.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
     ORDER BY n.created_at DESC'
);
// Here, we safely tell the database which user ID to use for the '?' in the query above.
$stmt->bind_param('i', $uid);
$stmt->execute();
// And finally, we gather up all the results from the database into a list.
$res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Send the complete list of notifications back to the user's browser.
echo json_encode($res);
?>