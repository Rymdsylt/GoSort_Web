<?php
require_once 'connection.php';

$data = json_decode(file_get_contents('php://input'), true);
$device_identity = $data['device_identity'] ?? null;

if (!$device_identity) {
    echo json_encode(['success' => false, 'message' => 'Device identity not provided']);
    exit();
}

try {
    // Get the oldest unexecuted command for this device
    $stmt = $pdo->prepare("
        SELECT command 
        FROM maintenance_commands 
        WHERE device_identity = ? 
        AND executed = 0 
        ORDER BY created_at ASC 
        LIMIT 1
    ");
    $stmt->execute([$device_identity]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode(['success' => true, 'command' => $result['command']]);
    } else {
        echo json_encode(['success' => true, 'command' => null]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
