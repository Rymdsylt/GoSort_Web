<?php
require_once 'connection.php';

header('Content-Type: application/json');

// Accept both GET and POST requests
$data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_input = json_decode(file_get_contents('php://input'), true);
    if (is_array($json_input)) {
        $data = $json_input;
    } else {
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