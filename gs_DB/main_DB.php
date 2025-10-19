<?php
$host = "localhost";
$user = "root";
$pass = "";

try {
    $conn = new mysqli($host, $user, $pass);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $conn->query("CREATE DATABASE IF NOT EXISTS gosort_db");
    $conn->select_db("gosort_db");

    // Create bin_fullness table
    $conn->query("
        CREATE TABLE IF NOT EXISTS bin_fullness (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_identity VARCHAR(50) NOT NULL,
            bin_name VARCHAR(20) NOT NULL,
            distance DECIMAL(10,2) NOT NULL,
            timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_bin_entry (device_identity, bin_name, timestamp)
        )
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role ENUM('admin','utility') NOT NULL,
            userName VARCHAR(50) NOT NULL,
            lastName VARCHAR(50) NOT NULL,
            email VARCHAR(100) UNIQUE,
            password VARCHAR(255),
            assigned_floor VARCHAR(50) DEFAULT NULL,
            registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS trash_sorted (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sorted ENUM('biodegradable', 'non-biodegradable', 'hazardous', 'mixed') NOT NULL,
            confidence FLOAT DEFAULT NULL,
            bin_location VARCHAR(100) DEFAULT NULL,
            user_id INT DEFAULT NULL,
            sorting_history_id INT DEFAULT NULL,
            time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (sorting_history_id) REFERENCES sorting_history(id) ON DELETE CASCADE
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS maintenance_commands (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_identity VARCHAR(100) NOT NULL,
            command VARCHAR(50) NOT NULL,
            executed TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            executed_at TIMESTAMP NULL,
            FOREIGN KEY (device_identity) REFERENCES sorters(device_identity) ON DELETE CASCADE
        )
    ");

   $conn->query("
    CREATE TABLE IF NOT EXISTS sorters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_name VARCHAR(100) NOT NULL,
        location VARCHAR(255),
        status ENUM('online', 'offline') DEFAULT 'offline',
        registration_token VARCHAR(64) UNIQUE,
        device_identity VARCHAR(100) UNIQUE,
        maintenance_mode TINYINT(1) DEFAULT 0,
        last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");


    $conn->query("
        CREATE TABLE IF NOT EXISTS waiting_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_identity VARCHAR(100) UNIQUE,
            request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS sorting_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_identity VARCHAR(100) NOT NULL,
            trash_type ENUM('bio', 'nbio', 'hazardous', 'mixed') NOT NULL,
            is_maintenance TINYINT(1) DEFAULT 0,
            sorted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (device_identity) REFERENCES sorters(device_identity) ON DELETE CASCADE
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS maintenance_mode (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            active BOOLEAN DEFAULT TRUE,
            start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            end_time TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS sorter_mapping (
            device_identity VARCHAR(100) PRIMARY KEY,
            zdeg VARCHAR(10) NOT NULL,
            ndeg VARCHAR(10) NOT NULL,
            odeg VARCHAR(10) NOT NULL,
            tdeg VARCHAR(10) NOT NULL,
            FOREIGN KEY (device_identity) REFERENCES sorters(device_identity) ON DELETE CASCADE
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS assigned_sorters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            device_identity VARCHAR(100) NOT NULL,
            assigned_floor VARCHAR(50) DEFAULT NULL,
            assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_assignment (user_id, device_identity),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (device_identity) REFERENCES sorters(device_identity) ON DELETE CASCADE
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS bin_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message TEXT NOT NULL,
            type VARCHAR(50) NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            device_identity VARCHAR(100) DEFAULT NULL,
            priority VARCHAR(20) DEFAULT 'normal',
            bin_name VARCHAR(20) DEFAULT NULL,
            fullness_level INT DEFAULT NULL,
            FOREIGN KEY (device_identity) REFERENCES sorters(device_identity) ON DELETE CASCADE
        )
    ");
    // Enable event scheduler
    $conn->query("SET GLOBAL event_scheduler = ON");

    // Drop existing events if they exist
    $conn->query("DROP EVENT IF EXISTS check_inactive_sorters");

    // Create event to automatically set sorters as offline if inactive
    $conn->query("
        CREATE EVENT check_inactive_sorters
        ON SCHEDULE EVERY 30 SECOND
        DO
        UPDATE sorters 
        SET status = 'offline'
        WHERE status = 'online' 
        AND maintenance_mode = 0
        AND last_active < NOW() - INTERVAL 60 SECOND
    ");

    // Create event to automatically end maintenance mode after 1 minute
    $conn->query("DROP EVENT IF EXISTS end_maintenance_mode_after_1_minute");
    $conn->query("
        CREATE EVENT end_maintenance_mode_after_1_minute
        ON SCHEDULE EVERY 1 MINUTE
        DO
        UPDATE maintenance_mode
        SET active = FALSE, end_time = NOW()
        WHERE active = TRUE AND start_time < NOW() - INTERVAL 1 MINUTE
    ");

    $conn->close();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
