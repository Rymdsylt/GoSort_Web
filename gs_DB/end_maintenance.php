<?php
session_start();
require_once 'connection.php';
require_once 'activity_logs.php';

try {
    // Get devices that are in maintenance mode before ending
    $stmt = $pdo->prepare("SELECT device_identity FROM sorters WHERE maintenance_mode = 1");
    $stmt->execute();
    $devices = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->prepare("UPDATE sorters SET maintenance_mode = 0 WHERE maintenance_mode = 1");
    $result = $stmt->execute();
    
    if ($result) {
        // Log maintenance ended for each device
        $user_id = $_SESSION['user_id'] ?? null;
        foreach ($devices as $device_identity) {
            log_maintenance_ended($user_id, $device_identity);
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update maintenance mode']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
