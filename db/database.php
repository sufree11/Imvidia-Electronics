<?php
$host = getenv('DB_HOST') ?: 'db-imvidia-do-user-36450203-0.f.db.ondigitalocean.com';
$port = getenv('DB_PORT') ?: 25060;
$dbname = getenv('DB_NAME') ?: 'db';
$username = getenv('DB_USER') ?: 'db';
$password = getenv('DB_PASSWORD') ?: '';

$conn = mysqli_init();

$connected = mysqli_real_connect(
    $conn, 
    $host, 
    $username, 
    $password, 
    $dbname, 
    $port, 
    NULL, 
    MYSQLI_CLIENT_SSL
);

if (!$connected) {
    die("Backend Connection Failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

?>