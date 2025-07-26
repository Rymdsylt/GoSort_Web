<?php
require_once 'connection.php';

try {
    // Create the table only if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS sorters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_name VARCHAR(100) NOT NULL,
        location VARCHAR(255),
        status ENUM('online', 'offline', 'maintenance') DEFAULT 'offline',
        registration_token VARCHAR(64) UNIQUE,
        device_identity VARCHAR(100) UNIQUE,
        last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
} catch(PDOException $e) {
    echo "Error creating sorters table: " . $e->getMessage();
}
?>
