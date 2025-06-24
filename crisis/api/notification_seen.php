<?php
require __DIR__.'/../init.php';
if(!isset($_SESSION['uid'])) exit;

// When the user interacts with a notification in their browser, the browser sends us some data.
// This line reads that data (which is in JSON format) sent from the browser.
$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['id'] ?? 0);
$uid  = (int)$_SESSION['uid'];
if($id){
    $stmt = $mysqli->prepare('UPDATE notifications SET seen = 1 WHERE id = ? AND user_id = ?');
    // This command tells the database: "Set the 'seen' status to 1 (meaning, yes, it has been seen)."
    // The "WHERE" part is a critical security check. It makes sure we only update the notification that
    // has the correct ID AND belongs to the currently logged-in user.
    // This prevents a user from marking someone else's notifications as seen.
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
}
?>