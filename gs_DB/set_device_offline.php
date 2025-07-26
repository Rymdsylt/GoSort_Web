<?php
require_once 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    // Verify token from Python app
    $provided_token = $data['token'] ?? '';
    $device_identity = $data['identity'] ?? '';
    $stored_token = trim(file_get_contents('../python_auth_token.txt'));
    
    if ($provided_token === $stored_token) {
        try {
            // Set device status to offline
            $stmt = $pdo->prepare("UPDATE sorters SET status = 'offline' WHERE device_name = ?");
            $stmt->execute(["GoSort-" . $device_identity]);
            
            echo json_encode(['success' => true, 'message' => 'Device status updated']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            error_log("Error updating device status: " . $e->getMessage());
        }
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
    }
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
?>
