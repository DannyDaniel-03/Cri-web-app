<?php
require __DIR__ . '/init.php';
if (!isset($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}

// instead of setting data, it retrieves or 'fetches' it
$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$stmt = $pdo->prepare("SELECT username, lat, lng, role FROM users WHERE id = ?");
$stmt->execute([ $_SESSION['uid'] ]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$userLat = isset($user['lat']) ? (float)$user['lat'] : 0;
$userLon = isset($user['lng']) ? (float)$user['lng'] : 0;
$userName = htmlspecialchars($user['username'] ?? "User");

$allowedRoles = ['developer', 'authority', 'ranker'];
$canCreate = isset($_SESSION['role']) && in_array($_SESSION['role'], $allowedRoles);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Emergency Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/styles.css?v=4">
  <script src="js/notify.js?v=1"></script>
</head>
<body>
  <div class="navbar">
    <a href="index.php" class="nav-title" style="text-decoration:none; color:inherit;">🌐 Emergency Dashboard</a>
    <div class="nav-links">
      <?php if ($canCreate): ?>
        <a href="admin/create_alert.php">Create Alert</a>
        <a href="admin/create_shelter.php">Create Shelter</a>
      <?php endif; ?>
      <?php if ($_SESSION['role'] !== 'normal'): ?>
        <a href="admin/administration_view.php">Administration</a>
      <?php endif; ?>
      <a href="APIKey.php">API Key</a> 
      <a href="logout.php" style="color:#e63946;">Logout</a>
    </div>
  </div>

  <div class="dashboard-container">
    <aside class="alerts-sidebar">
      <h2>Alert Notifications</h2>
      <div id="greeting" class="greeting">Hello <?= $userName ?>, loading your alerts...</div>
      <ul id="alert-list" class="alert-list">
        <li>Loading nearby alerts...</li>
      </ul>
    </aside>
    <main class="main-content">
      <div id="map"></div>
      </main>
  </div>

  <div class="alert-news-section">
    <h2 style="text-align:center;margin-bottom:1.3rem;">Alert News (Last 2 Days)</h2>
    <ul id="alert-news-list" class="alert-news-list">
      <li>Loading news...</li>
    </ul>
  </div>

 <script>
    // Define global user variables
    const USER_LAT = <?= json_encode($userLat) ?>;
    const USER_LON = <?= json_encode($userLon) ?>;
    const USER_NAME = <?= json_encode($userName) ?>;
  </script>

  <script src="js/alerts.js?v=2"></script>

  <script>
function renderAlertNews() {
      fetch('api/alert_news.php').then(r => r.json()).then(news => {
        const list = document.getElementById('alert-news-list');
        if (!news.length) {
          list.innerHTML = '<li>No news in the last 2 days.</li>';
          return;
        }
        // Creates every string for the list
        const newsHtml = news.map(item => {
          if (item.type === 'custom') {
            const itemDate = new Date(item.time);
            const locationId = `news-location-${item.id}`;
            const hasLocation = item.lat && item.lng;
            let detailsString = '';
            if (item.severity) {
              detailsString = `Intensity: ${item.severity}`;
              if (item.radius > 0) {
                detailsString += ` — Radius: ${Number(item.radius).toLocaleString()} m`;
              }
            }
            return `
              <li>
                <div class="alert-header" style="margin-bottom: 8px;">
                  <strong>${item.title || 'Custom Alert'}</strong>
                  <span class="alert-time">${itemDate.toLocaleString()}</span>
                </div>
                <div class="alert-details" style="border-bottom: 1px solid #e9ecef; padding-bottom: 10px; margin-bottom: 10px;">
                  ${hasLocation ? `<span class="alert-location" id="${locationId}">📍 Fetching location...</span>` : ''}
                  ${detailsString ? `<span class="alert-severity" style="display: block;">${detailsString}</span>` : ''}
                </div>
              </li>
            `;
          } else {
            return `
              <li>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <span class="news-source">${item.source || 'Alert'}</span>
                  <span class="news-date">${item.time ? new Date(item.time).toLocaleString() : ''}</span>
                </div>
                <div style="font-weight:bold;">${item.title ? item.title : ''}</div>
                <div>${item.message ? item.message : ''}</div>
              </li>
            `;
          }
        }).join('');

        // Updating the DOM only one time with the complete list.
        list.innerHTML = newsHtml;

        // loop through again just to start the location lookups.
        news.forEach(item => {
          if (item.type === 'custom' && item.lat && item.lng) {
            getCityFromLatLng(item.lat, item.lng, `news-location-${item.id}`, 'news');
          }
        });
      });
    }
  </script>

  <script src="js/map.js"></script>
  
  <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&callback=initMap"></script>
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
