<?php
require_once '../gs_DB/bin_notifications_DB.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $message = isset($data['message']) ? $data['message'] : '';
    $type = isset($data['type']) ? $data['type'] : 'bin_full';
    $device_identity = isset($data['device_identity']) ? $data['device_identity'] : null;
    $priority = isset($data['priority']) ? $data['priority'] : 'normal';
    $bin_name = isset($data['bin_name']) ? $data['bin_name'] : null;
    $fullness_level = isset($data['fullness_level']) ? (int)$data['fullness_level'] : null;
    
    if (!$message) {
        echo json_encode(['success' => false, 'message' => 'Message is required']);
        exit;
    }
    
    if (addBinNotification($message, $type, $device_identity, $priority, $bin_name, $fullness_level)) {
        echo json_encode(['success' => true, 'message' => 'Notification added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add notification']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
