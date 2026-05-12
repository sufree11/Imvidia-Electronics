<?php
require_once 'db/session.php';

$_SESSION = array();

session_destroy();

header("Location: login.php");
exit();
?>