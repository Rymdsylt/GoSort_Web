<?php
require_once 'connection.php';

header('Content-Type: application/json');

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['identity'])) {
    echo json_encode(['success' => false, 'message' => 'Device identity not provided']);
    exit;
}

$device_identity = $data['identity'];

try {
    // Check if device is already in waiting_devices
    $stmt = $pdo->prepare("SELECT id FROM waiting_devices WHERE device_identity = ?");
    $stmt->execute([$device_identity]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'error' => 'duplicate', 'message' => 'Device identity already exists in waiting list']);
        exit;
    }
    
    // Add device to waiting_devices
    $stmt = $pdo->prepare("INSERT INTO waiting_devices (device_identity) VALUES (?)");
    $stmt->execute([$device_identity]);
    
    echo json_encode(['success' => true, 'message' => 'Device added to waiting list']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
