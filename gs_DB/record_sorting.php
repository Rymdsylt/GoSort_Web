<?php
header('Content-Type: application/json');

require_once 'connection.php';

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['device_identity']) || !isset($data['trash_type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$device_identity = $data['device_identity'];
$trash_type = $data['trash_type'];
$is_maintenance = isset($data['is_maintenance']) ? (bool)$data['is_maintenance'] : false;

try {
    // First verify the device exists
    $stmt = $pdo->prepare("SELECT id FROM sorters WHERE device_identity = ?");
    $stmt->execute([$device_identity]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Device not found']);
        exit;
    }
    
    // Record the sorting operation
    $stmt = $pdo->prepare("
        INSERT INTO sorting_history 
        (device_identity, trash_type, is_maintenance) 
        VALUES (?, ?, ?)
    ");
    
    $success = $stmt->execute([$device_identity, $trash_type, $is_maintenance]);
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Sorting recorded successfully' : 'Failed to record sorting'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
