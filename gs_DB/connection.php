<?php
// Support both local development and Railway deployment
require_once __DIR__ . '/mariadb_credentials.php';

try {
    // Create persistent PDO connection (p: prefix reuses connections across requests)
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_PERSISTENT => true]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Create persistent mysqli connection for backward compatibility (p: prefix)
    $conn = new mysqli("p:$db_host", $db_user, $db_pass, $db_name, $db_port);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
