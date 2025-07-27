<?php
require_once 'connection.php';

$data = json_decode(file_get_contents('php://input'), true);
$device_identity = $data['identity'] ?? null;

if (!$device_identity) {
    echo json_encode(['success' => false, 'message' => 'Device identity not provided']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT maintenance_mode FROM sorters WHERE device_identity = ?");
    $stmt->execute([$device_identity]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode([
            'success' => true,
            'maintenance_mode' => (int)$result['maintenance_mode']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Device not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
