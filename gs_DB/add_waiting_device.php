<?php
require_once 'connection.php';

header('Content-Type: application/json');

// Accept both GET and POST requests
$data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Try to get JSON data first
    $json_input = json_decode(file_get_contents('php://input'), true);
    if (is_array($json_input)) {
        $data = $json_input;
    } else {
        // Fallback to POST data
        $data = $_POST;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = $_GET;
}

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
        echo json_encode(['success' => true, 'message' => 'Device already in waiting list']);
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
