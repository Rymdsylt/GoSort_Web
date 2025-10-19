<?php
require_once 'connection.php';

function createBinNotificationsTable() {
    global $conn;
    
    $sql = "CREATE TABLE IF NOT EXISTS bin_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message TEXT NOT NULL,
        type VARCHAR(50) NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        device_identity VARCHAR(100),
        priority VARCHAR(20) DEFAULT 'normal',
        bin_name VARCHAR(20),
        fullness_level INT,
        FOREIGN KEY (device_identity) REFERENCES sorters(device_identity) ON DELETE CASCADE
    )";

    if ($conn->query($sql) !== TRUE) {
        error_log("Error creating bin_notifications table: " . $conn->error);
    }
}

function addBinNotification($message, $type, $deviceId = null, $priority = 'normal', $binName = null, $fullnessLevel = null) {
    global $conn;
    
    $sql = "INSERT INTO bin_notifications (message, type, device_identity, priority, bin_name, fullness_level) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $message, $type, $deviceId, $priority, $binName, $fullnessLevel);
    
    if (!$stmt->execute()) {
        error_log("Error adding bin notification: " . $stmt->error);
        return false;
    }
    return true;
}

function getBinNotifications($limit = 50) {
    global $conn;
    
    $sql = "SELECT * FROM bin_notifications 
            ORDER BY created_at DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function markBinNotificationAsRead($id) {
    global $conn;
    
    $sql = "UPDATE bin_notifications 
            SET is_read = TRUE 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    return $stmt->execute();
}

// Initialize the bin_notifications table
createBinNotificationsTable();
?>