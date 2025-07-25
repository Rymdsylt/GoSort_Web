^<?php
// Prevent any output before our JSON response
ob_start();

// Disable error display but enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Function to send JSON response and exit
function send_json_response($data, $status = 200) {
    // Clear any output buffers
    while (ob_get_level()) ob_end_clean();
    
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

require_once 'connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

$deviceIdentity = $_POST['deviceIdentity'] ?? '';
$deviceName = $_POST['deviceName'] ?? '';
$location = $_POST['location'] ?? '';

if (!$deviceIdentity || !$deviceName || !$location) {
    send_json_response(['success' => false, 'message' => 'Missing required fields'], 400);
}

try {
    // Create pending_registrations table if it doesn't exist
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pending_registrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_identity VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_identity (device_identity),
            INDEX (created_at)
        )");
    } catch (PDOException $e) {
        error_log("Error creating pending_registrations table: " . $e->getMessage());
    }

    // Check if device already exists
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM sorters WHERE device_identity = ?");
    $checkStmt->execute([$deviceIdentity]);
    if ($checkStmt->fetchColumn() > 0) {
        send_json_response(['success' => false, 'message' => 'A device with this identity already exists.'], 400);
    }

    // Check if this identity is waiting for registration
    $waitingStmt = $pdo->prepare("SELECT * FROM pending_registrations WHERE device_identity = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $waitingStmt->execute([$deviceIdentity]);
    $pendingDevice = $waitingStmt->fetch();

    if (!$pendingDevice) {
        send_json_response([
            'success' => false, 
            'message' => 'No device with this identity is waiting for registration. Please ensure the device is running and trying to connect.'
        ], 400);
    }

    // Generate registration token
    $registrationToken = bin2hex(random_bytes(32));
    
    // Add device to database
    $stmt = $pdo->prepare("INSERT INTO sorters (device_name, location, device_identity, registration_token, status) VALUES (?, ?, ?, ?, 'offline')");
    $stmt->execute([$deviceName, $location, $deviceIdentity, $registrationToken]);
    
    // Verify the insert worked by checking if the row exists
    $checkStmt = $pdo->prepare("SELECT id FROM sorters WHERE device_identity = ?");
    $checkStmt->execute([$deviceIdentity]);
    if (!$checkStmt->fetch()) {
        throw new Exception("Device was not added to the database");
    }
    
    // Clean up the pending registration
    $pdo->prepare("DELETE FROM pending_registrations WHERE device_identity = ?")->execute([$deviceIdentity]);
    
    error_log("Device added successfully: $deviceIdentity");
    send_json_response([
        'success' => true, 
        'message' => 'Device registered successfully',
        'token' => $registrationToken,
        'registered' => true
    ]);

} catch(PDOException $e) {
    error_log("Database error in add_device.php: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => 'Database error occurred'], 500);
} catch(Exception $e) {
    error_log("Error in add_device.php: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
