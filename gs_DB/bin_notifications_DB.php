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

function getBinNotifications($limit = 10, $page = 1, $readFilter = null) {
    global $conn;
    
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT bn.*, s.device_name, s.location 
            FROM bin_notifications bn
            LEFT JOIN sorters s ON bn.device_identity = s.device_identity";
    
    if ($readFilter === 0) {
        $sql .= " WHERE bn.is_read = 0";
    } elseif ($readFilter === 1) {
        $sql .= " WHERE bn.is_read = 1";
    }
    
    $sql .= " ORDER BY bn.created_at DESC LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function countBinNotifications($readFilter = null) {
    global $conn;
    
    $sql = "SELECT COUNT(*) as total FROM bin_notifications";
    if ($readFilter === 0) {
        $sql .= " WHERE is_read = 0";
    } elseif ($readFilter === 1) {
        $sql .= " WHERE is_read = 1";
    }
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return intval($row['total']);
}

function countUnreadBinNotifications() {
    global $conn;
    
    $sql = "SELECT COUNT(*) as total FROM bin_notifications WHERE is_read = 0";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return intval($row['total']);
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

function markAllBinNotificationsAsRead() {
    global $conn;
    
    $sql = "UPDATE bin_notifications SET is_read = TRUE WHERE is_read = FALSE";
    return $conn->query($sql);
}

// Initialize the bin_notifications table
createBinNotificationsTable();
?>