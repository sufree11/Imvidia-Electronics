<?php
require_once 'db/session.php';

// clear session and redirect
$_SESSION = array();

session_destroy();

header("Location: login.php");
exit();
?>