<?php
require_once 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    // Generate a unique token if it doesn't exist
    if (!file_exists('../python_auth_token.txt')) {
        $token = bin2hex(random_bytes(32));
        file_put_contents('../python_auth_token.txt', $token);
    }
    
    // Verify token from Python app
    $provided_token = $data['token'] ?? '';
    $device_identity = $data['identity'] ?? '';
    $stored_token = trim(file_get_contents('../python_auth_token.txt'));
    
    if ($provided_token === $stored_token) {
        $current_time = time();
        file_put_contents('../python_heartbeat.txt', $current_time);
        
        // Update device status in database
        if ($device_identity) {
            try {
                // First check if device is in maintenance mode
                $stmt = $pdo->prepare("SELECT status FROM sorters WHERE device_name = ?");
                $stmt->execute(["GoSort-" . $device_identity]);
                $current_status = $stmt->fetchColumn();
                
                // Only update to 'online' if not in maintenance
                if ($current_status !== 'maintenance') {
                    $stmt = $pdo->prepare("UPDATE sorters SET status = 'online', last_active = NOW() WHERE device_name = ?");
                    $stmt->execute(["GoSort-" . $device_identity]);
                } else {
                    // Just update last_active if in maintenance
                    $stmt = $pdo->prepare("UPDATE sorters SET last_active = NOW() WHERE device_name = ?");
                    $stmt->execute(["GoSort-" . $device_identity]);
                }
            } catch (PDOException $e) {
                // Log error but don't expose it
                error_log("Error updating device status: " . $e->getMessage());
            }
        }
        
        echo json_encode(['success' => true, 'message' => "Heartbeat recorded"]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => "Invalid token"]);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Update status of all devices that haven't sent a heartbeat in 5 seconds
    try {
        $stmt = $pdo->prepare("
            UPDATE sorters 
            SET status = 'offline' 
            WHERE status = 'online' 
            AND (
                TIMESTAMPDIFF(SECOND, last_active, NOW()) > 5 
                OR last_active IS NULL
            )
        ");
        $stmt->execute();
        
        // Return current device statuses
        $stmt = $pdo->query("SELECT device_name, status, last_active FROM sorters");
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'devices' => $devices
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error'
        ]);
        error_log("Error updating device statuses: " . $e->getMessage());
    }
    exit();
}
?>
