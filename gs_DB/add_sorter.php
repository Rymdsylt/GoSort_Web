<?php
require_once 'connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (empty($_POST['deviceName']) || empty($_POST['location']) || empty($_POST['deviceIdentity'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Generate a unique registration token
    $registrationToken = bin2hex(random_bytes(32));
    
    $stmt = $pdo->prepare("INSERT INTO sorters (device_name, location, device_identity, registration_token) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_POST['deviceName'],
        $_POST['location'],
        $_POST['deviceIdentity'],
        $registrationToken
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Device added successfully']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
