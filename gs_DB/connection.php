<?php
// Support both local development and Railway deployment
$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbname = getenv('DB_NAME') ?: 'gosort_db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$port = getenv('DB_PORT') ?: 3306;

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Create mysqli connection for backward compatibility
    $conn = new mysqli($host, $username, $password, $dbname, (int)$port);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Check if root user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute(['root']);
    if (!$stmt->fetch()) {
        $hashedPassword = password_hash('pcsadmin', PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['root', $hashedPassword]);
    }
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
