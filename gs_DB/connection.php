<?php
$host = 'localhost';
$dbname = 'gosort_db';
$username = 'root';
$password = '';

try {
    // Try to connect directly to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

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
