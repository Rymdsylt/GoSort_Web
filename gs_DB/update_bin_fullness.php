<?php
require_once 'connection.php';

// Log all received data
error_log("Received POST data: " . print_r($_POST, true));
error_log("Raw input: " . file_get_contents('php://input'));

// Get POST data directly
if (!isset($_POST['device_identity']) || !isset($_POST['bin_name']) || !isset($_POST['distance'])) {
    $error = "Missing parameters. Required: device_identity, bin_name, distance. Received: " . 
             implode(", ", array_keys($_POST));
    error_log($error);
    exit($error);
}

$device_identity = $_POST['device_identity'];
$bin_name = $_POST['bin_name'];
$distance = (int)$_POST['distance'];

error_log("Processing bin fullness - Device: $device_identity, Bin: $bin_name, Distance: $distance");

try {
    // First verify connection
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Try direct insert
    $sql = "INSERT INTO bin_fullness (device_identity, bin_name, distance) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    error_log("Executing query with params: $device_identity, $bin_name, $distance");
    
    $stmt->bind_param("ssi", $device_identity, $bin_name, $distance);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    error_log("Successfully inserted new record");
    
    // Get last 5 readings for this bin
    $history_sql = "SELECT distance FROM bin_fullness 
                   WHERE device_identity = ? AND bin_name = ? 
                   ORDER BY timestamp DESC LIMIT 5";
    $history_stmt = $conn->prepare($history_sql);
    $history_stmt->bind_param("ss", $device_identity, $bin_name);
    $history_stmt->execute();
    $result = $history_stmt->get_result();
    $readings = [];
    while ($row = $result->fetch_assoc()) {
        $readings[] = $row['distance'];
    }
    $history_stmt->close();

    // Create notifications based on fullness levels
    $notification_sql = "INSERT INTO bin_notifications (message, type, device_id, priority, bin_name, fullness_level) VALUES (?, ?, ?, ?, ?, ?)";
    $notification_stmt = $conn->prepare($notification_sql);
    
    $type = "bin_fullness";
    $params = "sssssi"; // Parameter types for bind_param

    // Check if current distance is between 0.1-10cm (indicating bin is full)
    if ($distance > 0.0 && $distance <= 10.0) {
        // Check if we have 5 readings and they're all between 0.1-10cm
        if (count($readings) >= 5) {
            $all_readings_full = true;
            foreach ($readings as $reading) {
                if ($reading <= 0.0 || $reading > 10.0) {
                    $all_readings_full = false;
                    break;
                }
            }
            
            if ($all_readings_full) {
                $message = sprintf("Bin '%s' is probably full. Distance reading has been consistently between 0.1-10cm for the last 5 readings. Please empty when convenient.", 
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

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}

$conn->close();
?>
