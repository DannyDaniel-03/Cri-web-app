<?php
require __DIR__ . '/../init.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['developer', 'authority', 'ranker'])) {
    header('Location: ../index.php');
    exit;
}

// Fetch all shelters
$shelters = [];
$res = $mysqli->query('SELECT id, name, lat, lng FROM shelters');
while ($row = $res->fetch_assoc()) {
    $shelters[] = $row;
}

// Handle form submission - This block is now only reached if JavaScript validation passes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type             = $_POST['type'] ?? 'Unknown';
    $int              = (int) ($_POST['intensity'] ?? 0);
    $center           = json_decode($_POST['center'] ?? '{}', true);
    $radiusM          = (int) ($_POST['radius'] ?? 0);
    $lat              = $center['lat'] ?? null;
    $lng              = $center['lng'] ?? null;
    $selectedShelters = json_decode($_POST['shelters'] ?? '[]', true);

    if ($int >= 6 && empty($selectedShelters)) {
        $_SESSION['flash_error'] = 'Cannot create alert of intensity 6 or higher without indicating at least 1 shelter.';
        header('Location: create_alert.php');
        exit;
    }

    // Now we create the alert in a standard format called CAP (Common Alerting Protocol).
    // This is useful for sharing with other emergency systems.
    $capId   = 'sim' . time();
    $sentIso = date('c');
    $xml = "<alert xmlns='urn:oasis:names:tc:emergency:cap:1.2'>"
         . "<identifier>{$capId}</identifier>"
         . "<sender>" . CAP_SENDER . "</sender>"
         . "<sent>{$sentIso}</sent>"
         . "<status>Actual</status><msgType>Alert</msgType><scope>Public</scope>"
         . "<info><event>{$type}</event><description>Intensity {$int}</description>"
         . "<area><circle>{$lat},{$lng} {$radiusM}</circle></area></info></alert>";
    // We save this standard CAP alert to our database.
    $s = $mysqli->prepare('REPLACE INTO cap_alerts(identifier,xml,sent) VALUES (?,?,?)');
    $sentSql = date('Y-m-d H:i:s');
    $s->bind_param('sss', $capId, $xml, $sentSql);
    $s->execute();

    // We also save the alert to our own internal 'alerts' table for use in our app.
    $title   = "$type Alert";
    $message = "Intensity {$int} — radius {$radiusM} m";
    $s = $mysqli->prepare('INSERT INTO alerts(source,title,message,lat,lng,radius,severity) VALUES("authority",?,?,?,?,?,?)');
    $s->bind_param('ssddii', $title, $message, $lat, $lng, $radiusM, $int);
    $s->execute();
    $alertId = $s->insert_id;

    // Link shelters
    if (!empty($selectedShelters)) {
        $p = $mysqli->prepare('INSERT IGNORE INTO alert_shelters(alert_id,shelter_id) VALUES(?,?)');
        foreach ($selectedShelters as $sid) {
            $sid = (int) $sid;
            $p->bind_param('ii', $alertId, $sid);
            $p->execute();
        }
    }

    // We'll now find every user who is inside the alert's radius
    // and create a notification for them.  
    $notification_message = "$type Alert: Intensity {$int}";
    queue_notifications_for_alert($mysqli, $alertId, $lat, $lng, $radiusM, $notification_message);

    // Log the action
    $log = $mysqli->prepare(
        'INSERT INTO audit_logs (user_id, action, object_type, object_id, details) VALUES (?, "create_alert", "alert", ?, ?)'
    );
    $details = json_encode([
        'type'      => $type,
        'intensity' => $int,
        'radius'    => $radiusM,
        'lat'       => $lat,
        'lng'       => $lng,
        'shelters'  => $selectedShelters,
    ]);
    $log->bind_param('iis', $_SESSION['uid'], $alertId, $details);
    $log->execute();

    // Set success flash-message and redirect
    $_SESSION['flash_success'] = 'Alert successfully created.';
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Alert</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&libraries=drawing"></script>
    <style>
        .top-notification {
            background-color: #e63946;
            color: white;
            text-align: center;
            padding: 16px;
            position: fixed;
            top: -100px;
            left: 0;
            width: 100%;
            z-index: 1050;
            transition: top 0.5s ease-in-out;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .top-notification.show {
            top: 0;
        }
    </style>
</head>
<body>
    <div id="top-notification-banner" class="top-notification"></div>

    <div class="navbar">
        <a href="../index.php" class="nav-title" style="text-decoration:none; color:inherit;">🌐 Emergency Dashboard</a>
        <div class="nav-links">
            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['developer', 'authority', 'ranker'])): ?>
                <a href="create_alert.php" class="btn-primary">Create Alert</a>
                <a href="create_shelter.php">Create Shelter</a>
            <?php endif; ?>
            <?php if ($_SESSION['role'] !== 'normal'): ?>
                <a href="administration_view.php">Administration</a>
            <?php endif; ?>
            <a href="../APIKey.php">API Key</a>
            <a href="../logout.php" style="color:#e63946;">Logout</a>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
    <div class="flash-message success" style="margin:20px auto;max-width:600px;text-align:center;">
        <?= htmlspecialchars($_SESSION['flash_success']); ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <div class="auth-container">
        <div class="form-card">
            <h2>Create Alert</h2>
            <form id="create-alert-form" method="POST" onsubmit="return validateForm();">
                <label for="type">Type</label>
                <select id="type" name="type">
                    <option>Earthquake</option>
                    <option>Flood</option>
                    <option>Fire</option>
                    <option>Storm</option>
                </select>

                <label for="intensity">Intensity</label>
                <input id="intensity" name="intensity" type="number" required>

                <label>Draw Alert Radius</label>
                <div id="map"></div>

                <label>Select Shelters (optional)</label>
                <small class="text-secondary">Click shelter icons to include/exclude. A shelter is required for alerts with intensity 6 or higher.</small>

                <input type="hidden" id="center" name="center">
                <input type="hidden" id="radius" name="radius">
                <input type="hidden" id="shelters" name="shelters">

                <button type="submit" class="btn btn-primary">Create Alert</button>
            </form>
        </div>
    </div>

<script>
    let map, drawingMgr, circle;
    // We take the list of shelters from PHP and make it available to our Javascript code.
    const sheltersData = <?= json_encode($shelters, JSON_NUMERIC_CHECK) ?>;
    // We'll use a Set to keep track of selected shelters because it's fast and handles duplicates automatically.
    let selectedShelters = new Set();

    // Function to show the top notification
    function showTopNotification(message) {
        const banner = document.getElementById('top-notification-banner');
        banner.textContent = message;
        banner.classList.add('show');

        // Hide the banner after 5 seconds
        setTimeout(() => {
            banner.classList.remove('show');
        }, 5000);
    }

    // This is our main validation function that runs right before the form is submitted
    function validateForm() {
        // First, update the hidden inputs with the latest map data
        if (!prepare()) {
            return false; // Stop if prepare() fails (e.g., no circle drawn)
        }

        const intensity = document.getElementById('intensity').value;
        const sheltersValue = document.getElementById('shelters').value;
        const sheltersArray = JSON.parse(sheltersValue || '[]');

        // This is our client-side validation. It gives the user instant feedback, if the alert is very intense but no shelters are selected
        if (intensity >= 6 && sheltersArray.length === 0) {
            showTopNotification('Cannot create alert of intensity 6 or higher without indicating at least 1 shelter.');
            return false; // This prevents the form from submitting
        }

        return true; // Allow the form to submit
    }

    function initMap() {
        map = new google.maps.Map(document.getElementById('map'), {
            center: {lat: 0, lng: 0},
            zoom: 2,
            disableDefaultUI: true,
        });

        // This is Google's special tool for letting users draw shapes on the map.
        drawingMgr = new google.maps.drawing.DrawingManager({
            drawingMode: google.maps.drawing.OverlayType.CIRCLE, // Makes it circle drawing mode
            drawingControl: true, // Shows the tools for drawing
            drawingControlOptions: { drawingModes: ['circle'] }, // Only allows circles
            circleOptions: { editable: true, fillColor: '#ff0000', fillOpacity: 0.1, strokeWeight: 2 },
        });
        drawingMgr.setMap(map);

        // This happens when a user finishes drawing a circle
        google.maps.event.addListener(drawingMgr, 'circlecomplete', c => {
            if (circle) circle.setMap(null); // If there was a old circle, it will be removed
            circle = c; // Saves the new circle 
            drawingMgr.setDrawingMode(null); // Turns off drawing mode
        });

        // Puts all the shelters onto the map
        sheltersData.forEach(s => {
            const marker = new google.maps.Marker({
                position: { lat: s.lat, lng: s.lng },
                map: map,
                title: s.name,
                label: { text: '🏠', fontSize: '24px' },
            });
            marker.shelterId = s.id;
            // Adds a listener per shelter, so when it gets clicked it gets selected
            marker.addListener('click', () => {
                // if already selected, un select it.
                if (selectedShelters.has(s.id)) {
                    selectedShelters.delete(s.id);
                    marker.setAnimation(null);
                } else {
                    selectedShelters.add(s.id);
                    marker.setAnimation(google.maps.Animation.BOUNCE);
                }
            });
        });
    }

    // Prepares everything to be ready before the form is submitted
    function prepare() {
        if (!circle) {
            alert('Please draw a circle to define the alert area');
            return false;
        }
        // We get the center and radius from the circle object
        const c = circle.getCenter();
        // we put these information into our hidden form inputs.
        document.getElementById('center').value = JSON.stringify({ lat: c.lat(), lng: c.lng() });
        document.getElementById('radius').value = Math.round(circle.getRadius());
        document.getElementById('shelters').value = JSON.stringify(Array.from(selectedShelters));
        return true;
    }

    window.onload = initMap;
</script>
<footer class="site-footer">
    <div class="footer-content">
        <span>Powered by public data and APIs:</span>
        <a href="https://earthquake.usgs.gov/" target="_blank" rel="noopener">USGS Earthquake API</a>
        |
        <a href="https://www.weather.gov/documentation/services-web-api" target="_blank" rel="noopener">NOAA NWS API</a>
        |
        <a href="https://environment.data.gov.uk/flood-monitoring/doc/reference" target="_blank" rel="noopener">UK Flood Monitoring API</a>
        |
        <a href="https://developers.google.com/maps/documentation/javascript" target="_blank" rel="noopener">Google Maps JS API</a>
        <span class="footer-icons">
            | Icons by <a href="https://thenounproject.com/" target="_blank" rel="noopener">The Noun Project</a>
        </span>
    </div>
</footer>
</body>
</html>