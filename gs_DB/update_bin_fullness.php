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
$calculatedFullness = $distance < 0.5 ? -1 : ($distance > 60.96 ? 0 : round(100 - (($distance - 0.5) / (60.96 - 0.5) * 100)));
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
    $params = "sssssi"; // Parameter types for bind_param
    
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
    // Add current reading if not already in array
    if (!in_array($distance, $readings)) {
        $readings[] = $distance;
    }
    
    // Calculate fullness percentage for valid readings
    function calculateFullness($distance) {
        if ($distance > 60.96) return 0; // If distance > 60.96cm (24 inches), bin is empty
        if ($distance < 0.5) return -1;  // Sensor failure
        // Map 0.5-60.96cm to 100-0% (inverse relationship)
        return round(100 - (($distance - 0.5) / (60.96 - 0.5) * 100));
    }
    
    // First check for sensor failure (distance less than 0.5cm)
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

        // Check if there's already a sensor failure notification for this bin
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
            // Update the timestamp of the existing notification
            $row = $result->fetch_assoc();
            $update_sql = "UPDATE bin_notifications SET created_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $row['id']);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            // Create a new notification if one doesn't exist
            $priority = $distance <= 0.4 ? "critical" : "high";
            $fullness_level = -1; // Indicating sensor failure
            error_log("Creating new sensor failure notification - Priority: $priority, Fullness: $fullness_level");
            $notification_stmt->bind_param($params, $message, $type, $device_identity, $priority, $bin_name, $fullness_level);
            if (!$notification_stmt->execute()) {
                error_log("Failed to insert notification: " . $notification_stmt->error);
            } else {
                error_log("Successfully created sensor failure notification");
            }
        }
        $check_stmt->close();
        
        // Delete any older sensor failure notifications for this bin
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
    // Check if current distance is in valid range and calculate fullness
    else if ($distance >= 0.5 && $distance <= 400.0) {
        $fullness = calculateFullness($distance);
        
        // Check if we have at least 5 readings and they indicate high fullness (consistently between 0.5-10cm)
        if (count($readings) >= 5) {
            $all_readings_critical = true;
            $readings_in_range = array_filter($readings, function($reading) {
                return $reading >= 0.5 && $reading <= 10.0;
            });
            
            // Only notify if last 5 consecutive readings are in the full range
            if (count($readings_in_range) >= 5) {
                error_log("Bin fullness check - Last 5 readings: " . implode(", ", array_slice($readings, 0, 5)));
                
                // Check if there's already a fullness notification for this bin
                $check_existing_sql = "SELECT id FROM bin_notifications 
                                     WHERE device_identity = ? AND bin_name = ? 
                                     AND type = 'bin_fullness' 
                                     AND fullness_level >= 0";
                $check_stmt = $conn->prepare($check_existing_sql);
                $check_stmt->bind_param("ss", $device_identity, $bin_name);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                
                $message = sprintf("Bin '%s' is FULL! Distance readings have been consistently between 0.5-10cm for the last 5 readings. Trash height: %0.1fcm. Please empty immediately.", 
                                 $bin_name, $distance);
                $priority = "high";
                $fullness_level = $fullness;

                if ($result->num_rows > 0) {
                    // Update the timestamp of the existing notification
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
                    // Create a new notification
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

    // Keep only the 20 most recent entries for each device and bin combination
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