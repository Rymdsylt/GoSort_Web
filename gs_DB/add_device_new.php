<?php
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

try {
    require_once 'connection.php';

    // Validate input
    $deviceIdentity = $_POST['deviceIdentity'] ?? '';
    $deviceName = $_POST['deviceName'] ?? '';
    $location = $_POST['location'] ?? '';

    if (!$deviceIdentity || !$deviceName || !$location) {
        send_json_response([
            'success' => false,
            'message' => 'Missing required fields'
        ], 400);
    }

    // Ensure tables exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS pending_registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_identity VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_identity (device_identity),
        INDEX (created_at)
    )");

    // Check if device already exists
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM sorters WHERE device_identity = ?");
    $checkStmt->execute([$deviceIdentity]);
    if ($checkStmt->fetchColumn() > 0) {
        send_json_response([
            'success' => false,
            'message' => 'A device with this identity already exists.'
        ]);
    }

    // Check if device is waiting for registration
    $waitingStmt = $pdo->prepare("SELECT * FROM pending_registrations WHERE device_identity = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $waitingStmt->execute([$deviceIdentity]);
    $pendingDevice = $waitingStmt->fetch();

    if (!$pendingDevice) {
        send_json_response([
            'success' => false,
            'message' => 'No device with this identity is waiting for registration. Please ensure the device is running and trying to connect.'
        ]);
    }

    // Generate registration token
    $registrationToken = bin2hex(random_bytes(32));

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Add device to database
        $stmt = $pdo->prepare("INSERT INTO sorters (device_name, location, device_identity, registration_token, status) VALUES (?, ?, ?, ?, 'offline')");
        $stmt->execute([$deviceName, $location, $deviceIdentity, $registrationToken]);

        // Clean up pending registration
        $pdo->prepare("DELETE FROM pending_registrations WHERE device_identity = ?")->execute([$deviceIdentity]);

        // Commit transaction
        $pdo->commit();

        // Return success response
        send_json_response([
            'success' => true,
            'message' => 'Device registered successfully',
            'token' => $registrationToken,
            'registered' => true
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Database error in add_device.php: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => 'Database error occurred. Please try again or contact support.'
    ], 500);

} catch (Exception $e) {
    error_log("Error in add_device.php: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => 'An error occurred. Please try again or contact support.'
    ], 500);
}
?>
