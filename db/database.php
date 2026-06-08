<?php
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $env = parse_ini_file($envPath);
    $host = $env['DB_HOST'];
    $username = $env['DB_USER'];
    $password = $env['DB_PASSWORD'];
    $dbname = $env['DB_NAME'];
    $port = $env['DB_PORT'];
    $flags = 0;
} else {
$host = getenv('DB_HOST') ?: 'db-imvidia-do-user-36450203-0.f.db.ondigitalocean.com';
$port = getenv('DB_PORT') ?: 25060;
$dbname = getenv('DB_NAME') ?: 'db';
$username = getenv('DB_USER') ?: 'db';
$password = getenv('DB_PASSWORD') ?: '';
$flags = MYSQLI_CLIENT_SSL;
}
$conn = mysqli_init();

$connected = mysqli_real_connect(
    $conn, 
    $host, 
    $username, 
    $password, 
    $dbname, 
    $port, 
    NULL, 
    $flags
);

if (!$connected) {
    die("Backend Connection Failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

?>