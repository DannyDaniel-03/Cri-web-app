<?php
require __DIR__ . '/init.php';

// you know what this is
if (isset($_SESSION['uid'])) {
    header('Location: index.php');
    exit;
}

// the actuak authentication, and checking, basically you have account? if yes, it checks if the password provided matches the hashed, if yes, then go in, of course uses
// prepare statement for extra security!
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'];
    $p = $_POST['password'];

    $stmt = $mysqli->prepare("SELECT id, password, role, country, lat, lng FROM users WHERE username = ?");
    $stmt->bind_param('s', $u);
    $stmt->execute();
    $res = $stmt->get_result();
    $r = $res->fetch_assoc();

    if ($r && password_verify($p, $r['password'])) {
        $_SESSION['uid'] = $r['id'];
        $_SESSION['role'] = $r['role'];
        $_SESSION['country'] = $r['country'];
        $_SESSION['lat'] = $r['lat'];
        $_SESSION['lng'] = $r['lng'];
        queue_notifications_for_user($mysqli, $_SESSION['uid'], $_SESSION['lat'], $_SESSION['lng']);
        header('Location: index.php'); exit;
    } else { echo '<p style="color:red;text-align:center;">Invalid credentials</p>'; }
}
?>

<!-- Page below, yes. -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;background:#f2f2f2;margin:0;}
    .container{max-width:400px;margin:80px auto;padding:20px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);}
    input{width:100%;padding:10px;margin:8px 0;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;}
    button{width:100%;padding:10px;border:none;border-radius:4px;background:#007bff;color:#fff;font-size:16px;cursor:pointer;}
    button:hover{background:#0069d9;}
    a{color:#007bff;text-decoration:none;}
  </style>
</head>
<body>
  <div class="container">
    <h2 style="text-align:center;">Login</h2>
    <form method="POST">
      <label>Username:</label>
      <input name="username" required>
      <label>Password:</label>
      <input type="password" name="password" required>
      <button type="submit">Login</button>
    </form>
    <p style="text-align:center;margin-top:10px;">Don't have an account? <a href="register.php">Register</a></p>
  </div>
</body>
</html>