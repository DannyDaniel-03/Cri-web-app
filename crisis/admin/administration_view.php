<?php
require __DIR__ . '/../init.php';

// Handle POST request for changing user role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['new_role'])) {
    $targetUserId = $_POST['user_id'];
    $newRole      = $_POST['new_role'];
    $executorId   = $_SESSION['uid'];
    $executorRole = $_SESSION['role'];

    // Prevent changing your own role
    if ((int)$targetUserId === (int)$executorId) {
        header('Location: administration_view.php?error=You cannot change your own role.');
        exit;
    }

    // Get target user's current details
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        header('Location: administration_view.php?error=User not found.');
        exit;
    }
    $oldRole = $targetUser['role'];

    // Prevent changing to the same role
    if ($newRole === $oldRole) {
        header('Location: administration_view.php?error=Error: Unable to change rank to the same rank');
        exit;
    }

    $isAllowed = false;
    $validNewRoles = [];

    switch ($executorRole) {
        case 'developer':
            // Developer can change anyone to anything.
            $isAllowed = true;
            break;
        case 'admin':
            // Admin cannot change other admins or developers.
            if (in_array($oldRole, ['admin', 'developer'])) {
                header('Location: administration_view.php?error=Admins cannot change the role of other admins or developers.');
                exit;
            }
            // Admin can only assign 'ranker' or 'normal'.
            if (!in_array($newRole, ['ranker', 'normal'])) {
                 header('Location: administration_view.php?error=Invalid role assignment. Admins can only assign Ranker or Normal roles.');
                 exit;
            }
            $isAllowed = true;
            break;
        case 'ranker':
            // Ranker cannot change admins, other rankers, or developers.
            if (in_array($oldRole, ['admin', 'ranker', 'developer'])) {
                header('Location: administration_view.php?error=Rankers cannot change the role of admins, other rankers, or developers.');
                exit;
            }
            // Ranker can only assign 'authority' or 'normal'.
            if (!in_array($newRole, ['authority', 'normal'])) {
                 header('Location: administration_view.php?error=Invalid role assignment. Rankers can only assign Authority or Normal roles.');
                 exit;
            }
            $isAllowed = true;
            break;
    }

    if ($isAllowed) {
        // Update the user's role in the database
        $updateStmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $updateStmt->execute([$newRole, $targetUserId]);

        // Log the action to the audit trail
        $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, object_id, details) VALUES (?, 'rank_user', ?, ?)");
        $details = json_encode(['old_role' => $oldRole, 'new_role' => $newRole]);
        $logStmt->execute([$executorId, $targetUserId, $details]);

        header('Location: administration_view.php?success=1');
        exit;
    } else {
        header('Location: administration_view.php?error=Permission denied to perform this action.');
        exit;
    }
}


$currentPage = basename($_SERVER['PHP_SELF']);

if (!isset($_SESSION['uid']) || $_SESSION['role'] === 'normal') {
    header('Location: ../index.php');
    exit;
}

$executorRole = $_SESSION['role'];

// Defines the roles that each executor role is allowed to assign.
$assignableRolesByExecutor = [
    'developer' => ['admin', 'normal', 'authority', 'ranker', 'developer'],
    'admin'     => ['ranker', 'normal'],
    'ranker'    => ['authority', 'normal'],
    'authority' => [],
    'normal'    => [],
];


$canRank = in_array($executorRole, ['admin','ranker','developer']);

// default non-rankers to audit logs view
if (!$canRank && !isset($_GET['action'])) {
    header('Location: administration_view.php?action=');
    exit;
}

$error   = $_GET['error']   ?? null;
$success = isset($_GET['success']) && !$error;


$stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['uid']]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

$users = $pdo->query("SELECT id, username, role FROM users")->fetchAll(PDO::FETCH_ASSOC);


$visibleActions = [];
switch ($executorRole) {
    case 'developer':
    case 'ranker':
        // Developers and Rankers can view all types of logs
        $visibleActions = ['create_shelter', 'create_alert', 'rank_user'];
        break;
    case 'admin':
        // Admins can only view user ranking logs
        $visibleActions = ['rank_user'];
        break;
    case 'authority':
        // Authorities can only view shelter and alert creation logs
        $visibleActions = ['create_shelter', 'create_alert'];
        break;
}

// audit-log query with optional filter

$actionFilterRaw = $_GET['action'] ?? null;
// This is our base query to get the logs and the names of the people involved.
$sql =
  "SELECT al.*, actor.username AS actor,
          CASE WHEN al.action='rank_user'
               THEN target.username
               ELSE al.object_id END AS object_value
   FROM audit_logs al
   JOIN users actor      ON al.user_id = actor.id
   LEFT JOIN users target ON al.action='rank_user' AND al.object_id = target.id";

$params  = []; // This will hold the values for our query's question marks.
$clauses = []; // This will hold the different parts of our WHERE clause.

// Always restrict logs to what the executor is allowed to see.
if (!empty($visibleActions)) {
    // Creates a string of question marks for the IN clause, like: ?,?,?
    $placeholders = implode(',', array_fill(0, count($visibleActions), '?'));
    $clauses[] = "al.action IN ($placeholders)";
    // Add the visible actions to the parameters for the prepared statement
    $params = array_merge($params, $visibleActions);
} else {
    // If a user has no visible actions (e.g., 'normal' role who somehow gets here),
    // prevent any logs from showing.
    $clauses[] = "1=0"; // This will always evaluate to false
}

// If a specific filter button (other than 'All') was clicked, add that to the query.
if ($actionFilterRaw !== null && $actionFilterRaw !== '' && in_array($actionFilterRaw, $visibleActions)) {
    $clauses[] = "al.action = ?";
    $params[]  = $actionFilterRaw;
}

if ($clauses) {
    $sql .= ' WHERE ' . implode(' AND ', $clauses);
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

//These are little helpers to figure out which tab should be active

$showLogs        = array_key_exists('action', $_GET);
$showRank        = !$showLogs && $canRank;
$filterAllActive = ($actionFilterRaw === null || $actionFilterRaw === '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administration View</title>
  <link rel="stylesheet" href="../css/styles.css">
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      <?php if ($canRank): ?>
      document.getElementById('btn-rank')
              .addEventListener('click', () => location.href = 'administration_view.php');
      <?php endif; ?>
      document.getElementById('btn-logs')
              .addEventListener('click', () => location.href = 'administration_view.php?action=');
    });
  </script>
</head>
<body>
  <div class="navbar">
  <a href="../index.php" class="nav-title" style="text-decoration:none; color:inherit;">🌐 Emergency Dashboard</a>
  <div class="nav-links">
    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['developer', 'authority', 'ranker'])): ?>
      <a href="create_alert.php">Create Alert</a>
      <a href="create_shelter.php">Create Shelter</a>
    <?php endif; ?>
    <?php if ($_SESSION['role'] !== 'normal'): ?>
      <a href="administration_view.php" class="btn-primary">Administration</a>
    <?php endif; ?>
    <a href="../APIKey.php">API Key</a>
    <a href="../logout.php" style="color:#e63946;">Logout</a>
  </div>
  </div>


  <div class="container" style="padding:20px; max-width:900px; margin:auto;">
    <h1 style="text-align:center; margin-bottom:10px; font-size:2.7em; font-weight:600; letter-spacing:1px;">
      Administration View
    </h1>

    <div style="text-align:center; margin-bottom:28px; font-size:1.4em; color:#222;">
      <span><strong>User:</strong> <?= htmlspecialchars($current['username']) ?></span> |
      <span><strong>Role:</strong> <?= htmlspecialchars($current['role']) ?></span>
    </div>

    <div style="margin-bottom:20px; text-align:center;">
      <?php if ($canRank): ?>
        <button id="btn-rank"
                class="btn <?= $showRank ? 'btn-primary' : 'btn-secondary' ?>">Rank Users</button>
      <?php endif; ?>
      <button id="btn-logs"
              class="btn <?= $showLogs ? 'btn-primary' : 'btn-secondary' ?>">Audit Logs</button>
    </div>

    <?php if ($canRank): ?>
    <div id="container-rank" style="display:<?= $showRank ? 'block' : 'none' ?>;">
      <h2>Users</h2>
      <?php if ($success): ?>
        <div class="flash-message success">Rank changed successfully</div>
      <?php elseif ($error): ?>
        <div class="flash-message error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="table-responsive-wrapper">
      <table style="width:100%; border-collapse:collapse; border:1px solid #ccc;">
        <thead>
          <tr>
            <th style="border:1px solid #ccc; padding:8px; width: 40%;">Username</th>
            <th style="border:1px solid #ccc; padding:8px;">Role</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td style="border:1px solid #ccc; padding:8px;">
              <?= htmlspecialchars($u['username']) ?>
            </td>

            <td style="border:1px solid #ccc; padding:8px;">
              <?php
              $targetRole = $u['role'];
              $targetId = $u['id'];
              $canChange = false;

              // Determine if the current executor has permission to change the target user's role.
              if ((int)$targetId !== (int)$_SESSION['uid']) { // Cannot change own role
                  switch ($executorRole) {
                      case 'developer':
                          $canChange = true;
                          break;
                      case 'admin':
                          if (!in_array($targetRole, ['admin', 'developer'])) $canChange = true;
                          break;
                      case 'ranker':
                          if (!in_array($targetRole, ['admin', 'ranker', 'developer'])) $canChange = true;
                          break;
                  }
              }

              if ($canChange) {
                // we get the list of roles this executor is allowed to assign.
                  $rolesForDropdown = $assignableRolesByExecutor[$executorRole] ?? [];
              ?>
                <form method="POST" action="administration_view.php" style="display:flex; align-items:center; gap:8px; margin:0;">
                  <input type="hidden" name="user_id" value="<?= $targetId ?>">
                  <select name="new_role" class="form-card" style="width:100%; margin:0; padding:6px;">
                    <?php foreach ($rolesForDropdown as $r): ?>
                      <option value="<?= $r ?>" <?= $targetRole === $r ? 'selected' : '' ?>>
                        <?= ucfirst($r) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-secondary" style="margin:0;">Set</button>
                </form>
              <?php } else {
                  echo ucfirst(htmlspecialchars($targetRole));
                  if ((int)$targetId === (int)$_SESSION['uid']) {
                      echo ' <em style="font-size:0.9em; color:#6c757d;">(You)</em>';
                  }
              }
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- This is the container for the "Audit Logs" tab -->
    <div id="container-logs" style="display:<?= $showLogs ? 'block' : 'none' ?>;">
      <h2>Audit Logs</h2>

      <div class="actions" style="margin-bottom:10px;">
        <form method="GET" style="display:inline;">
          <button type="submit" name="action" value=""
                  class="btn <?= $filterAllActive ? 'btn-primary' : 'btn-secondary' ?>">All</button>
        </form>
        <!-- Loop through the actions this user can see and create a filter button for each one -->
        <?php foreach ($visibleActions as $act): ?>
          <form method="GET" style="display:inline;">
            <button type="submit" name="action" value="<?= htmlspecialchars($act) ?>"
                    class="btn <?= ($actionFilterRaw === $act) ? 'btn-primary' : 'btn-secondary' ?>">
              <?= htmlspecialchars(str_replace('_', ' ', $act)) ?>
            </button>
          </form>
        <?php endforeach; ?>
      </div>
      <div class="table-responsive-wrapper">
      <table style="width:100%; border-collapse:collapse; border:1px solid #ccc;">
        <thead>
          <tr>
            <th style="border:1px solid #ccc; padding:8px;">ID</th>
            <th style="border:1px solid #ccc; padding:8px;">Actor</th>
            <th style="border:1px solid #ccc; padding:8px;">Action</th>
            <th style="border:1px solid #ccc; padding:8px;">Object</th>
            <th style="border:1px solid #ccc; padding:8px;">Details</th>
            <th style="border:1px solid #ccc; padding:8px;">Time</th>
          </tr>
        </thead>
        <tbody>
        <!-- If there are no logs to show, display a message -->
        <?php if (empty($logs)): ?>
            <tr>
              <td colspan="6" style="padding: 16px; text-align: center; color: #6c757d;">No audit logs found for this filter.</td>
            </tr>
        <?php else: ?>
        <!-- Otherwise, loop through each log and display its information -->
        <?php foreach ($logs as $l): ?>
          <tr>
            <td style="border:1px solid #ccc; padding:8px;"><?= $l['id'] ?></td>
            <td style="border:1px solid #ccc; padding:8px;"><?= htmlspecialchars($l['actor']) ?></td>
            <td style="border:1px solid #ccc; padding:8px;"><?= htmlspecialchars(str_replace('_', ' ', $l['action'])) ?></td>
            <td style="border:1px solid #ccc; padding:8px;"><?= htmlspecialchars($l['object_value']) ?></td>
            <td style="border:1px solid #ccc; padding:8px;">
              <?php
              $info = json_decode($l['details'], true);
              if (json_last_error() === JSON_ERROR_NONE) {
                  // We show different details depending on what kind of action it was.
                  switch ($l['action']) {
                      case 'create_shelter':
                        echo "<strong>Name:</strong> " . htmlspecialchars($info['name']) . "<br>";
                        echo "<strong>Lat:</strong> "  . htmlspecialchars($info['lat'])  . "<br>";
                        echo "<strong>Long:</strong> " . htmlspecialchars($info['lng']);
                        break;
                      case 'create_alert':
                        echo "<strong>Type:</strong> "      . htmlspecialchars($info['type'])      . "<br>";
                        echo "<strong>Intensity:</strong> " . htmlspecialchars($info['intensity']) . "<br>";
                        echo "<strong>Radius:</strong> "    . number_format($info['radius']) . " m<br>";
                        echo "<strong>Lat:</strong> "       . htmlspecialchars($info['lat'])       . "<br>";
                        echo "<strong>Long:</strong> "      . htmlspecialchars($info['lng'])       . "<br>";
                        if (!empty($info['shelters'])) {
                          $ph    = implode(',', array_fill(0, count($info['shelters']), '?'));
                          $stmt2 = $pdo->prepare("SELECT name FROM shelters WHERE id IN ($ph)");
                          $stmt2->execute($info['shelters']);
                          $snames = $stmt2->fetchAll(PDO::FETCH_COLUMN);
                          echo "<strong>Shelters:</strong> " . htmlspecialchars(implode(', ', $snames));
                        }
                        break;
                      case 'rank_user':
                        echo "<strong>Old Role:</strong> " . htmlspecialchars(ucfirst($info['old_role'])) . "<br>";
                        echo "<strong>New Role:</strong> " . htmlspecialchars(ucfirst($info['new_role']));
                        break;
                  }
              }
              ?>
            </td>
            <td style="border:1px solid #ccc; padding:8px;"><?= htmlspecialchars($l['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
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