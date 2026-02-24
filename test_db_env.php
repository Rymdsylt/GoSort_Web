<?php
echo "<h1>Database Environment Variables</h1>";
echo "Host: " . getenv('MARIADB_HOST') . "<br>";
echo "Port: " . getenv('MARIADB_PORT') . "<br>";
echo "User: " . getenv('MARIADB_USER') . "<br>";
echo "DB: " . getenv('MARIADB_DATABASE') . "<br>";
echo "<hr>";
echo "<h2>Connection Test</h2>";
$host = getenv('MARIADB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MARIADB_PORT') ?: 3306);
$user = getenv('MARIADB_USER') ?: 'root';
$pass = getenv('MARIADB_PASSWORD') ?: '';
$db   = getenv('MARIADB_DATABASE') ?: 'gosort_db';
echo "Connecting to: $host:$port as $user ...<br>";
$conn = @new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    echo "<span style='color:red'>FAILED: " . $conn->connect_error . "</span>";
} else {
    echo "<span style='color:green'>SUCCESS! Connected to MariaDB.</span>";
    $conn->close();
}
?>
