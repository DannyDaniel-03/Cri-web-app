<?php
require __DIR__ . '/init.php';

if (isset($_SESSION['uid'])) {
    header('Location: index.php');
    exit;
}

// Registration form thingy, retrieves data provided, then hashes the password as we can see with 'password_hash' a built in function then does the same as login
// with a secure database insertion, also instantly logs you in by setting up the session, and UNIQUELY provides your lat/lng position to see if you are eligible for any
// alerts to be provided while logging in 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'];
    $p = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $country = $_POST['country'];
    $lat = $_POST['lat'];
    $lng = $_POST['lng'];

    $stmt = $mysqli->prepare("INSERT INTO users(username, password, role, country, lat, lng) VALUES(?, ?, 'normal', ?, ?, ?)");
    $stmt->bind_param('sssdd', $u, $p, $country, $lat, $lng);
    $stmt->execute();
    $id = $stmt->insert_id;

    $_SESSION['uid'] = $id;
    $_SESSION['role'] = 'normal';
    $_SESSION['country'] = $country;
    $_SESSION['lat'] = $lat;
    $_SESSION['lng'] = $lng;
    queue_notifications_for_user($mysqli, $_SESSION['uid'], $_SESSION['lat'], $_SESSION['lng']);
    header('Location: index.php'); exit;
}
?>
<!-- where the for resides to provide us with the data, ALSO uses google API for a JS map provided by the API, in which it returns a hidden value "lng" and "lat"
 also special feature is that, if you press on a location where there is a country, the country value is automatically filled for you!-->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register</title>
  <style>
    #map{height:300px;width:100%;}
    body{font-family:Arial,Helvetica,sans-serif;background:#f2f2f2;margin:0;}
    .container{max-width:600px;margin:40px auto;padding:20px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);}
    input,select{width:100%;padding:10px;margin:8px 0;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;}
    button{width:100%;padding:10px;border:none;border-radius:4px;background:#28a745;color:#fff;font-size:16px;cursor:pointer;}
    button:hover{background:#218838;}
    label{display:block;margin-top:10px;}
    a{color:#007bff;text-decoration:none;}
    .status{font-size:20px;vertical-align:middle;display:inline-block;width:24px;text-align:center;}
  </style>
  <script src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>"></script>
</head>
<body>
  <div class="container">
    <h2 style="text-align:center;">Register</h2>
    <form method="POST" autocomplete="off">
      <label>Username:</label>
      <div style="display:flex;align-items:center;gap:4px;">
        <input id="username" name="username" required style="flex:1;">
        <span id="userStatus" class="status"></span>
      </div>
      <label>Password:</label>
      <input type="password" name="password" required>
      <label>Country:</label>
      <input type="text" id="countryDisplay" readonly>
      <input type="hidden" name="country" id="country">
      <div id="map"></div>
      <input type="hidden" name="lat" id="lat">
      <input type="hidden" name="lng" id="lng">
      <button type="submit">Register</button>
    </form>
    <p style="text-align:center;margin-top:10px;">Already have an account? <a href="login.php">Login</a></p>
  </div>
<script>
let map, marker, geocoder;
function initMap(){
  geocoder = new google.maps.Geocoder();
  map = new google.maps.Map(document.getElementById('map'),{center:{lat:20,lng:0},zoom:2});
  map.addListener('click',e=>placeMarker(e.latLng));
}
function placeMarker(loc){
  if(marker)marker.setPosition(loc);else marker=new google.maps.Marker({position:loc,map:map});
  document.getElementById('lat').value=loc.lat();
  document.getElementById('lng').value=loc.lng();
  geocoder.geocode({location:loc},(results,status)=>{
    if(status==='OK'&&results[0]){
      let country='';
      for(const c of results[0].address_components){
        if(c.types.includes('country')){country=c.long_name;break;}
      }
      if(country){
        document.getElementById('countryDisplay').value=country;
        document.getElementById('country').value=country;
      }
    }
  });
}
window.onload=initMap;

const inp=document.getElementById('username');
const statusEl=document.getElementById('userStatus');
let timer;
function checkUsername(){
  const val=inp.value.trim();
  if(!val){statusEl.textContent='';return;}
  fetch(`username_exists.php?u=${encodeURIComponent(val)}`)
    .then(r=>r.json())
    .then(d=>{if(d.exists){statusEl.textContent='✖';statusEl.style.color='red';}else{statusEl.textContent='✔';statusEl.style.color='green';}});
}
inp.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(checkUsername,500);});
</script>
</body>
</html>