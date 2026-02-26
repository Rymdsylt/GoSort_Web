<?php
require_once 'connection.php';

header('Content-Type: application/json');

// Accept both GET and POST requests
$data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_input = json_decode(file_get_contents('php://input'), true);
    if (is_array($json_input)) {
        $data = $json_input;
    } else {
        $data = $_POST;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = $_GET;
}

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
