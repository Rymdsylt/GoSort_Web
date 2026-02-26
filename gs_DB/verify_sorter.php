<?php
require_once 'connection.php';

header('Content-Type: application/json');

// Accept both GET and POST requests
$input = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Try to get JSON data first
    $json_input = json_decode(file_get_contents('php://input'), true);
    if (is_array($json_input)) {
        $input = $json_input;
    } else {
        // Fallback to POST data
        $input = $_POST;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $input = $_GET;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($input['identity'])) {
    echo json_encode(['success' => false, 'message' => 'Missing sorter identity']);
    exit;
}

$identity = $input['identity'];

try {
    // Check if this device is registered
    $stmt = $pdo->prepare("SELECT id, registration_token FROM sorters WHERE device_identity = ?");
    $stmt->execute([$identity]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($device) {
        // Device exists, update its status to online
        $updateStmt = $pdo->prepare("UPDATE sorters SET status = 'online', last_active = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->execute([$device['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Device verified',
            'registered' => true,
            'token' => $device['registration_token']
        ]);
    } else {
        // Device not registered
        echo json_encode([
            'success' => true,
            'message' => 'Device not registered',
            'registered' => false
        ]);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
