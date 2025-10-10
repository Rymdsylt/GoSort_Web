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
    echo "OK - Record inserted";

    $stmt->close();

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}

$conn->close();
?>
