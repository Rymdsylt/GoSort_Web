<?php
require_once 'connection.php';

function createNotificationsTable() {
    global $conn;
    
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message TEXT NOT NULL,
        type VARCHAR(50) NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        device_id VARCHAR(100),
        priority VARCHAR(20) DEFAULT 'normal'
    )";

    if ($conn->query($sql) !== TRUE) {
        error_log("Error creating notifications table: " . $conn->error);
    }
}

function addNotification($message, $type, $deviceId = null, $priority = 'normal') {
    global $conn;
    
    $sql = "INSERT INTO notifications (message, type, device_id, priority) 
            VALUES (?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $message, $type, $deviceId, $priority);
    
    if (!$stmt->execute()) {
        error_log("Error adding notification: " . $stmt->error);
        return false;
    }
    return true;
}

function getNotifications($limit = 50) {
    global $conn;
    
    $sql = "SELECT * FROM notifications 
            ORDER BY created_at DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function markNotificationAsRead($id) {
    global $conn;
    
    $sql = "UPDATE notifications 
            SET is_read = TRUE 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    return $stmt->execute();
}

// Initialize the notifications table
createNotificationsTable();
?>