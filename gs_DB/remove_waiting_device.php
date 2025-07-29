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
    // Remove device from waiting_devices
    $stmt = $pdo->prepare("DELETE FROM waiting_devices WHERE device_identity = ?");
    $stmt->execute([$device_identity]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Device removed from waiting list']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Device not found in waiting list']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 