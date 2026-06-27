<?php
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

requireAdminLogin();

$admin_data = getAdminUserData();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Orders - ImVidia</title>
    <?php include 'includes/head.php'; ?>
</head>
<body class="bg-fixed bg-gray-50 text-gray-800 flex h-screen overflow-hidden dark:bg-slate-950 dark:text-gray-100" style="background-image: radial-gradient(circle, rgba(156, 163, 175, 0.2) 2.5px, transparent 2.5px); background-size: 40px 40px;">
    <?php include 'includes/navbar-admin.php'; ?>
</body>

