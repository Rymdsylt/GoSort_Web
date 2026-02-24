<?php
// Support both local development and Railway deployment
require_once __DIR__ . '/mariadb_credentials.php';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Create mysqli connection for backward compatibility
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
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
