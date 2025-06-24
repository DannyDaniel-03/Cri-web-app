<?php
require __DIR__ . '/../init.php';

$error = $_SESSION['flash_error'] ?? null;
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['flash_error'], $_SESSION['form_data']);

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['developer', 'authority', 'ranker'])) {
    header('Location: ../index.php');
    exit;
}

// The information written in the form for the shelter
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $marker  = json_decode($_POST['marker'] ?? '{}', true);
    $lat     = isset($marker['lat']) ? (float) $marker['lat'] : null;
    $lng     = isset($marker['lng']) ? (float) $marker['lng'] : null;

    $validationError = null;
    if (empty($name)) {
        $validationError = "Name cannot be empty.";
    } elseif (is_numeric(substr($name, 0, 1))) { // Check if the first character is numeric
        $validationError = "Name cannot start with a number. Good example: 'a1231', bad example: '1231'";
    } elseif ($lat === null || $lng === null) {
        $validationError = "A location must be selected on the map.";
    }

    if ($validationError) {
        // If validation fails, store error and data in session and redirect back
        $_SESSION['flash_error'] = $validationError;
        $_SESSION['form_data'] = $_POST;
        header('Location: create_shelter.php');
        exit;
    }

    // Adding the new shelter
    $stmt = $mysqli->prepare(
        'INSERT INTO shelters (name, lat, lng, capacity, details) VALUES (?, ?, ?, 0, ?)'
    );
    $stmt->bind_param('sdds', $name, $lat, $lng, $details);
    $stmt->execute();
    $shelterId = $mysqli->insert_id;

    // Log the action
    $log = $mysqli->prepare(
        'INSERT INTO audit_logs (user_id, action, object_type, object_id, details)
         VALUES (?, "create_shelter", "shelter", ?, ?)'
    );
    $detailsJson = json_encode(['name' => $name, 'lat' => $lat, 'lng' => $lng]);
    $log->bind_param('iis', $_SESSION['uid'], $shelterId, $detailsJson);
    $log->execute();

    $_SESSION['flash_success'] = 'Shelter successfully created.';
    
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Success</title>
  <link rel="stylesheet" href="../css/styles.css">
  <meta http-equiv="refresh" content="1;url=../index.php">
</head>
<body>
  <div class="flash-message success" style="margin:20px auto;max-width:600px;text-align:center;">
    Shelter successfully created.<br>
    <small>Redirecting to dashboard…</small>
  </div>
</body>
</html>
HTML;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Shelter</title>
  <link rel="stylesheet" href="../css/styles.css">
  <script src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>"></script>
  <style>
    .input-error {
      color: #e63946; 
      font-size: 0.875rem;
      margin-top: 4px;
      min-height: 1.2em; /* Prevents layout shifting */
    }
  </style>
</head>
<body>
  <div class="navbar">
    <a href="../index.php" class="nav-title" style="text-decoration:none; color:inherit;">🌐 Emergency Dashboard</a>
    <div class="nav-links">
        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['developer', 'authority', 'ranker'])): ?>
          <a href="create_alert.php">Create Alert</a>
          <a href="create_shelter.php" class="btn-primary">Create Shelter</a>
        <?php endif; ?>
        <?php if ($_SESSION['role'] !== 'normal'): ?>
          <a href="administration_view.php">Administration</a>
        <?php endif; ?>
        <a href="../APIKey.php">API Key</a>
        <a href="../logout.php" style="color:#e63946;">Logout</a>
    </div>
  </div>

  <div class="auth-container">
    <div class="form-card">
      <h2>Create Shelter</h2>

      <?php if ($error): ?>
        <div class="flash-message error" style="margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST" onsubmit="return validateForm();" novalidate>
        <label for="name">Name</label>
        <input id="name" type="text" name="name" required value="<?= htmlspecialchars($formData['name'] ?? '') ?>">
        <div id="name-error" class="input-error"></div>

        <label for="details">Details</label>
        <textarea id="details" name="details" rows="4"><?= htmlspecialchars($formData['details'] ?? '') ?></textarea>

        <label>Location (click map)</label>
        <div id="map"></div>
        <input type="hidden" id="marker" name="marker" value='<?= htmlspecialchars($formData['marker'] ?? '{}') ?>'>

        <button type="submit" class="btn btn-primary">Create Shelter</button>
      </form>
    </div>
  </div>

  <script>
    // Real time validations
    let map, marker;
    const nameInput = document.getElementById('name');
    const nameError = document.getElementById('name-error');


    function validateName() {
        const nameValue = nameInput.value.trim();
        // Regular expression to check if the string starts with an alphabet character.
        const startsWithLetter = /^[a-zA-Z]/.test(nameValue);

        if (nameValue === '') {
            nameError.textContent = "Name cannot be empty.";
            return false;
        } else if (!startsWithLetter) {
            nameError.textContent = "Cannot be null, or start with a number. Good example: 'a1231', bad example: '1231'";
            return false;
        } else {
            nameError.textContent = '';
            return true;
        }
    }
    
    // Add an 'input' event listener to validate the name as the user types.
    nameInput.addEventListener('input', validateName);

    function initMap() {
        map = new google.maps.Map(document.getElementById('map'), {
            center: {lat: 0, lng: 0},
            zoom: 2,
            disableDefaultUI: true, // hides un-needed ui
        });

        map.addListener('click', e => {
            if (marker) {
                marker.setMap(null); // Remove the old marker
            }
            marker = new google.maps.Marker({ position: e.latLng, map: map });
            // Update the hidden input value when a marker is placed
            const pos = marker.getPosition();
            document.getElementById('marker').value = JSON.stringify({ lat: pos.lat(), lng: pos.lng() });
        });

        // If form was submitted with an error, re-place the marker on the map
        try {
            const oldMarkerData = JSON.parse(document.getElementById('marker').value);
            if (oldMarkerData && oldMarkerData.lat && oldMarkerData.lng) {
                const position = new google.maps.LatLng(oldMarkerData.lat, oldMarkerData.lng);
                marker = new google.maps.Marker({ position: position, map: map });
                map.setCenter(position);
                map.setZoom(8);
            }
        } catch (e) {
            console.error("Could not parse old marker data:", e);
        }
    }

    //A simple check before submitting the form to make sure a location was chosen
    function prepareMarker() {
      if (!marker) {
          alert('Please select a location on the map');
          return false;
      }
      return true;
    }

    //This is the master validation function that runs when the user hits "submit"
    function validateForm() {
        // We'll run all our checks.
        const isNameValid = validateName();
        const isLocationSet = prepareMarker();

        // The form is submitted only if all checks return true
        return isNameValid && isLocationSet;
    }

    // Initialize the map when the window loads.
    window.onload = initMap;
  </script>

  <footer class="site-footer">
    <div class="footer-content">
        <span>Powered by public data and APIs:</span>
        <a href="https://earthquake.usgs.gov/" target="_blank" rel="noopener">USGS Earthquake API</a>
        &nbsp;|&nbsp;
        <a href="https://www.weather.gov/documentation/services-web-api" target="_blank" rel="noopener">NOAA NWS API</a>
        &nbsp;|&nbsp;
        <a href="https://environment.data.gov.uk/flood-monitoring/doc/reference" target="_blank" rel="noopener">UK Flood Monitoring API</a>
        &nbsp;|&nbsp;
        <a href="https://developers.google.com/maps/documentation/javascript" target="_blank" rel="noopener">Google Maps JS API</a>
        <span class="footer-icons">
            &nbsp;|&nbsp; Icons by <a href="https://thenounproject.com/" target="_blank" rel="noopener">The Noun Project</a>
        </span>
    </div>
  </footer>
</body>
</html>
