<?php
require __DIR__ . '/init.php';

// Ensure the user is logged in
if (!isset($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Fetch the current user's API key
$stmt = $pdo->prepare("SELECT api_key FROM users WHERE id = ?");
$stmt->execute([$_SESSION['uid']]);
$apiKey = $stmt->fetchColumn();

// If the user wants to make a new key, its created right here, it generates a cryptographically secure key which produces 32 bytes then turns those bytes into a 
// 64 character hexadecimal string.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $newApiKey = bin2hex(random_bytes(32));
    $updateStmt = $pdo->prepare("UPDATE users SET api_key = ? WHERE id = ?");
    $updateStmt->execute([$newApiKey, $_SESSION['uid']]);
    $apiKey = $newApiKey;
}

// placeholder / current keys
$apiKeyPlaceholder = $apiKey ? htmlspecialchars($apiKey) : 'YOUR_API_KEY_HERE';
$domainPlaceholder = $_SERVER['HTTP_HOST'];

// this uses a heredoc to create a multi line PHP usage
$phpSnippet = <<<PHP
<?php
\$apiKey = '{$apiKeyPlaceholder}';
\$apiUrl = 'https://{$domainPlaceholder}/crisis/api/alerts.php?api_key=' . \$apiKey;

\$ch = curl_init();
curl_setopt(\$ch, CURLOPT_URL, \$apiUrl);
curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);

\$response = curl_exec(\$ch);
\$httpCode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
curl_close(\$ch);

if (\$httpCode == 200) {
    \$alerts = json_decode(\$response, true);
    print_r(\$alerts);
} else {
    echo "API Error: " . \$response;
}
PHP;

// This one creates the one line curl example (-k is used to ignore any license issues)
$curlSnippet = "curl -k \"https://{$domainPlaceholder}/crisis/api/alerts.php?api_key={$apiKeyPlaceholder}\"";

?>

<!-- Again, more HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>API Key Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/styles.css?v=5"> </head>
<body>
  <div class="navbar">
    <a href="index.php" class="nav-title" style="text-decoration:none; color:inherit;">🌐 Emergency Dashboard</a>
    <div class="nav-links">
      <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['developer', 'authority', 'ranker'])): ?>
        <a href="admin/create_alert.php">Create Alert</a>
        <a href="admin/create_shelter.php">Create Shelter</a>
      <?php endif; ?>
      <?php if ($_SESSION['role'] !== 'normal'): ?>
        <a href="admin/administration_view.php">Administration</a>
      <?php endif; ?>
      <a href="../APIKey.php" class="btn-primary">API Key</a> 
      <a href="../logout.php" style="color:#e63946;">Logout</a>
    </div>
  </div>

  <div class="auth-container">
    <div class="form-card">
      <h2>Your API Key</h2>
      <p>Use this key in your application to access the alerts API.</p>
      
      <div class="api-key-display">
        <code><?= $apiKeyPlaceholder ?></code>
      </div>

      <form method="POST">
        <button type="submit" name="generate" class="btn btn-primary btn-full-width">Generate New Key</button>
      </form>

      <p class="key-generation-warning">
          Generating a new key will invalidate the old one.
      </p>

      <div class="api-usage-section">
        <h3 class="api-usage-title">Usage Examples</h3>

        <div class="code-block">
            <h4>PHP cURL Snippet</h4>
            <pre><code><?= htmlspecialchars($phpSnippet) ?></code></pre>
        </div>

        <div class="code-block">
            <h4>Command-Line cURL</h4>
            <pre><code><?= htmlspecialchars($curlSnippet) ?></code></pre>
        </div>
      </div>

    </div>
  </div>
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