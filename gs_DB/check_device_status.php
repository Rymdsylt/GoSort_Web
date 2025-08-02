<?php
require_once 'main_DB.php';
require_once 'connection.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$device_id = $data['device_id'] ?? null;
$device_identity = $data['device_identity'] ?? null;

if (!$device_id || !$device_identity) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameters',
        'message' => 'Device ID and identity are required'
    ]);
    exit();
}

try {
    // Check if device exists and get its current status
    $stmt = $pdo->prepare("SELECT * FROM sorters WHERE id = ? AND device_identity = ?");
    $stmt->execute([$device_id, $device_identity]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        echo json_encode([
            'success' => false,
            'error' => 'device_not_found',
            'message' => 'Device not found or mismatch'
        ]);
        exit();
    }

    // Check if device is online
    if ($device['status'] !== 'online') {
        echo json_encode([
            'success' => false,
            'error' => 'device_offline',
            'message' => "Device '{$device['device_name']}' is offline"
        ]);
        exit();
    }

    // Check if already in maintenance mode
    if ($device['maintenance_mode'] == 1) {
        echo json_encode([
            'success' => false,
            'error' => 'already_in_maintenance',
            'message' => "Device '{$device['device_name']}' is already in maintenance mode"
        ]);
        exit();
    }

    // Check if any other device is in maintenance mode
    $stmt = $pdo->prepare("SELECT device_name FROM sorters WHERE maintenance_mode = 1 AND id != ? LIMIT 1");
    $stmt->execute([$device_id]);
    $maintenance_device = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($maintenance_device) {
        echo json_encode([
            'success' => false,
            'error' => 'maintenance_active',
            'message' => "Another device '{$maintenance_device['device_name']}' is currently in maintenance mode"
        ]);
        exit();
    }

    // All checks passed
    echo json_encode([
        'success' => true,
        'message' => 'Device is ready for maintenance',
        'device' => [
            'id' => $device['id'],
            'name' => $device['device_name'],
            'identity' => $device['device_identity'],
            'status' => $device['status'],
            'maintenance_mode' => $device['maintenance_mode']
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'database_error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
