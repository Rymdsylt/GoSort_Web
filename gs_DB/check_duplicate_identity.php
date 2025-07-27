<?php
header('Content-Type: application/json');

require_once('connection.php');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['identity'])) {
    echo json_encode(['success' => false, 'message' => 'No identity provided']);
    exit;
}

$identity = $data['identity'];
$current_identity = isset($data['current_identity']) ? $data['current_identity'] : null;

try {
    // Check both waiting_devices and sorters tables, excluding current identity
    $stmt = $conn->prepare("
        SELECT 
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM waiting_devices 
                    WHERE device_identity = ? 
                    AND device_identity != ?
                )
                    THEN 'waiting'
                WHEN EXISTS (
                    SELECT 1 FROM sorters 
                    WHERE device_identity = ?
                    AND device_identity != ?
                )
                    THEN 'registered'
                ELSE 'available'
            END as status
    ");
    
    $stmt->bind_param("ssss", $identity, $current_identity, $identity, $current_identity);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'status' => $row['status']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
