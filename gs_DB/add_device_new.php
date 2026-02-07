<?php
session_start();
require_once 'connection.php';
require_once 'activity_logs.php';

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate input data
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data received. Make sure you are sending JSON data.']);
    exit;
}

$missing = [];
if (!isset($data['deviceName']) || trim($data['deviceName']) === '') {
    $missing[] = 'Device Name';
}
if (!isset($data['location']) || trim($data['location']) === '') {
    $missing[] = 'Location';
}
if (!isset($data['deviceIdentity']) || trim($data['deviceIdentity']) === '') {
    $missing[] = 'Device Identity';
}

if (!empty($missing)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required fields: ' . implode(', ', $missing),
        'data' => $data
    ]);
    exit;
}

try {
    // First check if this identity exists in waiting_devices
    $stmt = $pdo->prepare("SELECT id FROM waiting_devices WHERE device_identity = ?");
    $stmt->execute([$data['deviceIdentity']]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Device not found in waiting list. Please ensure the device is running and trying to connect.'
        ]);
        exit;
    }
    
    // Generate a unique registration token
    $registration_token = bin2hex(random_bytes(32));
    
    // Add the device to sorters table
    $stmt = $pdo->prepare("
        INSERT INTO sorters (device_name, location, device_identity, registration_token, status) 
        VALUES (?, ?, ?, ?, 'offline')
    ");
    
    if ($stmt->execute([$data['deviceName'], $data['location'], $data['deviceIdentity'], $registration_token])) {
        // Remove from waiting_devices
        $stmt = $pdo->prepare("DELETE FROM waiting_devices WHERE device_identity = ?");
        $stmt->execute([$data['deviceIdentity']]);
        
        // Set default mapping for the new device
        $stmt = $pdo->prepare("
            INSERT INTO sorter_mapping (device_identity, zdeg, ndeg, odeg, mdeg) 
            VALUES (?, 'bio', 'nbio', 'hazardous', 'mixed')
        ");
        $stmt->execute([$data['deviceIdentity']]);
        
        // Log device addition
        $user_id = $_SESSION['user_id'] ?? null;
        log_device_added($user_id, $data['deviceIdentity'], $data['deviceName']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Device added successfully with default mapping!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error adding device to database'
        ]);
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Duplicate entry
        echo json_encode([
            'success' => false,
            'message' => 'A device with this identity already exists.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}
?>
