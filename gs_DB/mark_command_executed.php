<?php
require_once 'connection.php';

$data = json_decode(file_get_contents('php://input'), true);
$device_identity = $data['device_identity'] ?? null;
$command = $data['command'] ?? null;

if (!$device_identity || !$command) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE maintenance_commands 
        SET executed = 1, 
            executed_at = CURRENT_TIMESTAMP 
        WHERE device_identity = ? 
        AND command = ? 
        AND executed = 0 
        ORDER BY created_at ASC 
        LIMIT 1
    ");
    
    if ($stmt->execute([$device_identity, $command])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update command status']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
