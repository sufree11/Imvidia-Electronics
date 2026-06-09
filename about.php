<?php
require_once 'db/session.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$user = checkCustomerOrGuest();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>About Us - ImVidia</title>
    <?php include 'includes/head.php'; ?>
</head>
<body class="bg-fixed bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100" style="background-image: radial-gradient(circle, rgba(156, 163, 175, 0.2) 2.5px, transparent 2.5px); background-size: 40px 40px;">
    <?php include 'includes/navbar-customer.php'; ?>

    <main class="flex-grow container mx-auto px-4 py-12">
        <h1 class="text-4xl font-bold mb-6 text-imvidia-dark">About Us</h1>