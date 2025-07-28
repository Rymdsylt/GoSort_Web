<?php
require_once 'connection.php';

// Get the command from POST data
$data = json_decode(file_get_contents('php://input'), true);
$device_identity = $data['device_identity'] ?? null;
$command = $data['command'] ?? null;

if (!$device_identity || !$command) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    // Only verify the device is online (not maintenance mode)
    $stmt = $pdo->prepare("SELECT status FROM sorters WHERE device_identity = ? AND status = 'online'");
    $stmt->execute([$device_identity]);
    $device = $stmt->fetch();

    if (!$device) {
        echo json_encode(['success' => false, 'message' => 'Device not online or not found']);
        exit();
    }

    // Insert the command
    $stmt = $pdo->prepare("
        INSERT INTO maintenance_commands (device_identity, command) 
        VALUES (?, ?)
    ");
    
    if ($stmt->execute([$device_identity, $command])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save command']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
