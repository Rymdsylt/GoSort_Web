<?php
require_once 'connection.php';

// Disable error display but enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function send_json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$deviceId = $data['deviceId'] ?? null;

if (!$deviceId) {
    send_json_response(['success' => false, 'message' => 'Device ID is required'], 400);
}

try {
    // First check if device exists and get its status
    $stmt = $pdo->prepare("SELECT status FROM sorters WHERE id = ?");
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();

    if (!$device) {
        send_json_response(['success' => false, 'message' => 'Device not found'], 404);
    }

    if ($device['status'] === 'online') {
        send_json_response([
            'success' => false, 
            'message' => 'Cannot delete device while it is online. Please ensure the device is offline first.'
        ], 400);
    }

    // Delete the device
    $stmt = $pdo->prepare("DELETE FROM sorters WHERE id = ?");
    $stmt->execute([$deviceId]);

    send_json_response([
        'success' => true,
        'message' => 'Device deleted successfully'
    ]);

} catch (PDOException $e) {
    error_log("Error deleting device: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => 'Database error occurred while deleting device'
    ], 500);
}
