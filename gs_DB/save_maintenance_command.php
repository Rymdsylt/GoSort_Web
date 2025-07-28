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
    // Allow shutdown command regardless of maintenance mode
    if ($command === 'shutdown') {
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_commands (device_identity, command) 
            VALUES (?, ?)
        ");
        if ($stmt->execute([$device_identity, $command])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save shutdown command']);
        }
        exit();
    }
    // First verify the device is in maintenance mode for other commands
    $stmt = $pdo->prepare("SELECT maintenance_mode FROM sorters WHERE device_identity = ? AND status = 'online'");
    $stmt->execute([$device_identity]);
    $device = $stmt->fetch();

    if (!$device || $device['maintenance_mode'] != 1) {
        echo json_encode(['success' => false, 'message' => 'Device not in maintenance mode']);
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
