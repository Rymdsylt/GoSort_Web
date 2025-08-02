<?php
require_once 'main_DB.php';
require_once 'connection.php';

header('Content-Type: application/json');

try {
    // Get all devices from sorters table
    $stmt = $pdo->query("
        SELECT 
            id,
            device_name,
            status,
            last_active
        FROM sorters
        ORDER BY last_active DESC
    ");
    
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update status to offline for devices that haven't been active recently
    $stmt = $pdo->prepare("
        UPDATE sorters 
        SET status = 'offline' 
        WHERE status = 'online' 
        AND last_active < NOW() - INTERVAL 15 SECOND
    ");

    $stmt->execute();
    
    // Get fresh status after update
    $stmt = $pdo->query("
        SELECT 
            id,
            device_name,
            status,
            last_active
        FROM sorters
        ORDER BY last_active DESC
    ");
    
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'devices' => $devices
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
