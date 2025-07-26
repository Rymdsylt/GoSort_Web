<?php
require_once 'connection.php';

header('Content-Type: application/json');

// Get the raw POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['identity'])) {
    echo json_encode(['success' => false, 'message' => 'No identity provided']);
    exit;
}

$device_identity = $input['identity'];

try {
    // Check if a device with this identity exists
    $stmt = $pdo->prepare("SELECT * FROM sorters WHERE device_identity = ?");
    $stmt->execute([$device_identity]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($device) {
        // Return existing registration info
        echo json_encode([
            'success' => true,
            'message' => 'Device found',
            'registered' => true,
            'token' => $device['registration_token']
        ]);
    } else {
        // Create pending_registrations table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS pending_registrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_identity VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_identity (device_identity),
            INDEX (created_at)
        )");
        
        // Clean up old pending registrations
        $pdo->exec("DELETE FROM pending_registrations WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        
        // Add or update pending registration
        $stmt = $pdo->prepare("INSERT INTO pending_registrations (device_identity) VALUES (?)
                              ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP");
        $stmt->execute([$device_identity]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Device pending registration',
            'registered' => false
        ]);
    }
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
