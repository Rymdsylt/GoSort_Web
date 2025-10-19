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

<<<<<<< Updated upstream
$device_identity = $data['device_identity'];
$bin_name = $data['bin_name'];
$distance = $data['distance'];
=======
$device_identity = $_POST['device_identity'];
$bin_name = $_POST['bin_name'];
$distance = floatval($_POST['distance']);

error_log("Processing bin fullness - Device: $device_identity, Bin: $bin_name, Distance: $distance");
>>>>>>> Stashed changes

try {
    // First check if the device exists and is registered
    $stmt = $conn->prepare("SELECT id FROM sorters WHERE identity = ?");
    $stmt->bind_param("s", $device_identity);
    $stmt->execute();
    $result = $stmt->get_result();
    
<<<<<<< Updated upstream
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Device not registered']);
        exit;
=======
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    error_log("Executing query with params: $device_identity, $bin_name, $distance");
    
    $stmt->bind_param("ssd", $device_identity, $bin_name, $distance);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
>>>>>>> Stashed changes
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
    
<<<<<<< Updated upstream
    echo json_encode(['success' => true]);
=======
    $type = "bin_fullness";
    $params = "sssssi"; // Parameter types for bind_param

    // First check for sensor failure (distance less than 0.5cm)
    if ($distance < 0.5) {
        // Check if there's already a sensor failure notification for this bin
        $check_existing_sql = "SELECT id FROM bin_notifications 
                             WHERE device_id = ? AND bin_name = ? 
                             AND type = 'bin_fullness' 
                             AND fullness_level = -1 
                             AND message LIKE '%Sensor failure detected%'";
        $check_stmt = $conn->prepare($check_existing_sql);
        $check_stmt->bind_param("ss", $device_identity, $bin_name);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update the timestamp of the existing notification
            $row = $result->fetch_assoc();
            $update_sql = "UPDATE bin_notifications SET timestamp = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $row['id']);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            // Create a new notification if one doesn't exist
            $message = sprintf("WARNING: Sensor failure detected for bin '%s'. Distance reading is below 0.5cm, indicating possible malfunction.", 
                             $bin_name);
            $priority = "high";
            $fullness_level = -1; // Indicating sensor failure
            $notification_stmt->bind_param($params, $message, $type, $device_identity, $priority, $bin_name, $fullness_level);
            $notification_stmt->execute();
        }
        $check_stmt->close();
        
        // Delete any older sensor failure notifications for this bin
        $delete_old_sql = "DELETE FROM bin_notifications 
                          WHERE device_id = ? AND bin_name = ? 
                          AND type = 'bin_fullness' 
                          AND fullness_level = -1 
                          AND id NOT IN (
                              SELECT id FROM (
                                  SELECT id FROM bin_notifications 
                                  WHERE device_id = ? AND bin_name = ? 
                                  AND type = 'bin_fullness' 
                                  AND fullness_level = -1 
                                  ORDER BY timestamp DESC 
                                  LIMIT 1
                              ) temp
                          )";
        $delete_stmt = $conn->prepare($delete_old_sql);
        $delete_stmt->bind_param("ssss", $device_identity, $bin_name, $device_identity, $bin_name);
        $delete_stmt->execute();
        $delete_stmt->close();
    }
    // Check if current distance is between 0.5-10cm (indicating bin is full)
    else if ($distance >= 0.5 && $distance <= 10.0) {
        // Check if we have 5 readings and they're all between 0.5-10cm
        if (count($readings) >= 5) {
            $all_readings_full = true;
            foreach ($readings as $reading) {
                if ($reading < 0.5 || $reading > 10.0) {
                    $all_readings_full = false;
                    break;
                }
            }
            
            if ($all_readings_full) {
                $message = sprintf("Bin '%s' is FULL! Distance reading has been consistently between 0.1-10cm for the last 5 readings. Please empty immediately.", 
                                 $bin_name);
                $priority = "high";
                $fullness_level = 100;
                $notification_stmt->bind_param($params, $message, $type, $device_identity, $priority, $bin_name, $fullness_level);
                $notification_stmt->execute();
            }
        }
    }
    
    $notification_stmt->close();
    echo "OK - Record inserted";

    // Keep only the 10 most recent entries for each device and bin combination
    $cleanup_sql = "DELETE bf1 FROM bin_fullness bf1
                   LEFT JOIN (
                       SELECT id 
                       FROM bin_fullness 
                       WHERE device_identity = ? AND bin_name = ?
                       ORDER BY timestamp DESC 
                       LIMIT 10
                   ) bf2 ON bf1.id = bf2.id
                   WHERE bf1.device_identity = ? AND bf1.bin_name = ?
                   AND bf2.id IS NULL";
                   
    $cleanup_stmt = $conn->prepare($cleanup_sql);
    $cleanup_stmt->bind_param("ssss", $device_identity, $bin_name, $device_identity, $bin_name);
    $cleanup_stmt->execute();
    $cleanup_stmt->close();

    $stmt->close();
>>>>>>> Stashed changes

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>