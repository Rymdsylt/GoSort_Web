<?php
header('Content-Type: application/json');

require_once('connection.php');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['identity'])) {
    echo json_encode(['success' => false, 'message' => 'No identity provided']);
    exit;
}

$identity = $data['identity'];

try {
    // Check if identity exists in sorters table first (registered devices)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sorters WHERE device_identity = ?");
    $stmt->execute([$identity]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row['count'] > 0) {
        echo json_encode([
            'success' => true,
            'status' => 'registered'
        ]);
        exit;
    }
    
    // Check if identity exists in waiting_devices table
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM waiting_devices WHERE device_identity = ?");
    $stmt->execute([$identity]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $is_duplicate = $row['count'] > 0;
    
    echo json_encode([
        'success' => true,
        'status' => $is_duplicate ? 'waiting' : 'available'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
