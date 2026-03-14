<?php
header('Content-Type: application/json');

require_once 'connection.php';

// Get the POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Check both JSON and POST data
if (isset($data['device_identity']) && isset($data['bin_name']) && isset($data['distance'])) {
    $device_identity = $data['device_identity'];
    $bin_name = $data['bin_name'];
    $distance = floatval($data['distance']);
} else if (isset($_POST['device_identity']) && isset($_POST['bin_name']) && isset($_POST['distance'])) {
    $device_identity = $_POST['device_identity'];
    $bin_name = $_POST['bin_name'];
    $distance = floatval($_POST['distance']);
} else {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

error_log("Processing bin fullness - Device: $device_identity, Bin: $bin_name, Distance: $distance");

// Calculate fullness for logging
$calculatedFullness = $distance < 0.5 ? -1 : ($distance <= 30 ? 100 : ($distance >= 90 ? 0 : round(100 - (($distance - 30) / (90 - 30) * 100))));
error_log("Calculated fullness level: $calculatedFullness% (Distance: {$distance}cm)");


try {
    // First check if the device exists and is registered
    $stmt = $conn->prepare("SELECT device_identity FROM sorters WHERE device_identity = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $device_identity);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Device not registered']);
        exit;
    }

    // Update or insert bin fullness data
    $stmt = $conn->prepare("INSERT INTO bin_fullness (device_identity, bin_name, distance, timestamp) 
                           VALUES (?, ?, ?, NOW())
                           ON DUPLICATE KEY UPDATE 
                           distance = VALUES(distance),
                           timestamp = VALUES(timestamp)");
    
    error_log("Inserting bin fullness record - Distance: $distance");
    $stmt->bind_param("ssd", $device_identity, $bin_name, $distance);
    if (!$stmt->execute()) {
        error_log("Failed to insert bin fullness: " . $stmt->error);
    } else {
        error_log("Successfully inserted bin fullness record");
    }
    $stmt->execute();
    

    // Prepare notification statement
    $notification_sql = "INSERT INTO bin_notifications (message, type, device_identity, priority, bin_name, fullness_level) 
                        VALUES (?, ?, ?, ?, ?, ?)";
    $notification_stmt = $conn->prepare($notification_sql);
    if (!$notification_stmt) {
        throw new Exception("Failed to prepare notification statement: " . $conn->error);
    }

    $type = "bin_fullness";
    $params = "sssssi";
    
    // Get the last 5 readings from the database
    $readings_sql = "SELECT distance FROM bin_fullness 
                    WHERE device_identity = ? AND bin_name = ? 
                    ORDER BY timestamp DESC LIMIT 5";
    $readings_stmt = $conn->prepare($readings_sql);
    $readings_stmt->bind_param("ss", $device_identity, $bin_name);
    $readings_stmt->execute();
    $readings_result = $readings_stmt->get_result();
    $readings = array();
    while ($row = $readings_result->fetch_assoc()) {
        $readings[] = floatval($row['distance']);
    }
    if (!in_array($distance, $readings)) {
        $readings[] = $distance;
    }
    
    // Calculate fullness percentage
    function calculateFullness($distance) {
        if ($distance < 0.5) return -1;   // Sensor failure
        if ($distance <= 30) return 100;  // Full (trash at bin rim)
        if ($distance >= 90) return 0;    // Empty
        return round(100 - (($distance - 30) / (90 - 30) * 100));
    }
    
    // First check for sensor failure
    if ($distance < 0.5) {
        error_log("Sensor failure detected - Distance: {$distance}cm");
        $message = "";
        if ($distance <= 0.4) {
            $message = sprintf("CRITICAL: Probable sensor failure for bin '%s'. Distance reading is %0.1fcm (0-0.4cm range), indicating definite malfunction.", 
                             $bin_name, $distance);
            error_log("Critical sensor failure message: $message");
        } else {
            $message = sprintf("WARNING: Sensor failure detected for bin '%s'. Distance reading is below 0.5cm, indicating possible malfunction.", 
                             $bin_name);
            error_log("Warning sensor failure message: $message");
        }

        $check_existing_sql = "SELECT id FROM bin_notifications 
                             WHERE device_identity = ? AND bin_name = ? 
                             AND type = 'bin_fullness' 
                             AND fullness_level = -1 
                             AND message LIKE '%sensor failure%'";
        $check_stmt = $conn->prepare($check_existing_sql);
        $check_stmt->bind_param("ss", $device_identity, $bin_name);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $update_sql = "UPDATE bin_notifications SET created_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $row['id']);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            $priority = $distance <= 0.4 ? "critical" : "high";
            $fullness_level = -1;
            error_log("Creating new sensor failure notification - Priority: $priority, Fullness: $fullness_level");
            $notification_stmt->bind_param($params, $message, $type, $device_identity, $priority, $bin_name, $fullness_level);
            if (!$notification_stmt->execute()) {
                error_log("Failed to insert notification: " . $notification_stmt->error);
            } else {
                error_log("Successfully created sensor failure notification");
            }
        }
        $check_stmt->close();
        
        $delete_old_sql = "DELETE FROM bin_notifications 
                          WHERE device_identity = ? AND bin_name = ? 
                          AND type = 'bin_fullness' 
                          AND fullness_level = -1 
                          AND id NOT IN (
                              SELECT id FROM (
                                  SELECT id FROM bin_notifications 
                                  WHERE device_identity = ? AND bin_name = ? 
                                  AND type = 'bin_fullness' 
                                  AND fullness_level = -1 
                                  ORDER BY created_at DESC 
                                  LIMIT 1
                              ) temp
                          )";
        $delete_stmt = $conn->prepare($delete_old_sql);
        $delete_stmt->bind_param("ssss", $device_identity, $bin_name, $device_identity, $bin_name);
        $delete_stmt->execute();
        $delete_stmt->close();
    }
    // Check if current distance is in valid range
    else if ($distance >= 0.5 && $distance <= 400.0) {
        $fullness = calculateFullness($distance);
        
        if (count($readings) >= 2) {
            $latest_reading = $readings[0];
            $previous_reading = $readings[1];
            
            // Full = distance <= 30cm (trash at or above bin rim)
            $is_full = ($latest_reading <= 30 && $previous_reading <= 30);
            
            if ($is_full) {
                error_log("Bin fullness check - Latest: {$latest_reading}cm, Previous: {$previous_reading}cm");
                
                $check_existing_sql = "SELECT id FROM bin_notifications 
                                     WHERE device_identity = ? AND bin_name = ? 
                                     AND type = 'bin_fullness' 
                                     AND fullness_level >= 0";
                $check_stmt = $conn->prepare($check_existing_sql);
                $check_stmt->bind_param("ss", $device_identity, $bin_name);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                
                $message = sprintf("Bin '%s' is FULL! Current distance: %0.1fcm, Previous: %0.1fcm. Please empty immediately.", 
                                 $bin_name, $latest_reading, $previous_reading);
                $priority = "high";
                $fullness_level = $fullness;

                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $update_sql = "UPDATE bin_notifications 
                                 SET created_at = NOW(),
                                     fullness_level = ?,
                                     message = ?
                                 WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("isi", $fullness_level, $message, $row['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else {
                    $notification_stmt->bind_param($params, $message, $type, $device_identity, $priority, $bin_name, $fullness_level);
                    $notification_stmt->execute();
                }
                $check_stmt->close();
            }
        }
    }
    
    if (isset($notification_stmt)) {
        $notification_stmt->close();
    }

    $cleanup_sql = "DELETE FROM bin_fullness 
                   WHERE device_identity = ? 
                   AND bin_name = ?
                   AND id NOT IN (
                       SELECT id FROM (
                           SELECT id 
                           FROM bin_fullness 
                           WHERE device_identity = ? 
                           AND bin_name = ?
                           ORDER BY timestamp DESC 
                           LIMIT 20
                       ) temp
                   )";
                   
    $cleanup_stmt = $conn->prepare($cleanup_sql);
    if ($cleanup_stmt) {
        $cleanup_stmt->bind_param("ssss", $device_identity, $bin_name, $device_identity, $bin_name);
        $cleanup_stmt->execute();
        $cleanup_stmt->close();
    }

    if (isset($stmt)) {
        $stmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Record updated successfully']);

} catch (Exception $e) {
    error_log("Error in update_bin_fullness.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>