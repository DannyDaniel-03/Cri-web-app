<?php
//clears session, then destroys, and directs you to the login page!
session_start();
session_unset(); 
session_destroy();
header('Location: login.php');
exit;
?>