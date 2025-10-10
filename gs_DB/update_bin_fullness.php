<?php
header('Content-Type: application/json');

require_once 'connection.php';

// Get the POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['device_identity']) || !isset($data['bin_name']) || !isset($data['distance'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$device_identity = $data['device_identity'];
$bin_name = $data['bin_name'];
$distance = $data['distance'];

try {
    // First check if the device exists and is registered
    $stmt = $conn->prepare("SELECT id FROM sorters WHERE identity = ?");
    $stmt->bind_param("s", $device_identity);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Device not registered']);
        exit;
    }
    
    $device_row = $result->fetch_assoc();
    $device_id = $device_row['id'];

    // Update or insert bin fullness data
    $stmt = $conn->prepare("INSERT INTO bin_fullness (device_id, bin_name, distance, timestamp) 
                           VALUES (?, ?, ?, NOW())
                           ON DUPLICATE KEY UPDATE 
                           distance = VALUES(distance),
                           timestamp = VALUES(timestamp)");
    
    $stmt->bind_param("isi", $device_id, $bin_name, $distance);
    $stmt->execute();
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>