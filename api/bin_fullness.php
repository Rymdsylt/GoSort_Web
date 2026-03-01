<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

include_once '../gs_DB/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get device identity from query parameter
$device_identity = isset($_GET['device_identity']) ? $_GET['device_identity'] : null;

if (!$device_identity) {
    http_response_code(400);
    echo json_encode(['error' => 'Device identity is required']);
    exit();
}

try {
    // Fetch the sorter mapping for this device
    // Sensor mapping: TRIG_PIN_1/ECHO_PIN_1 = zdeg, TRIG_PIN_2/ECHO_PIN_2 = ndeg, TRIG_PIN_3/ECHO_PIN_3 = odeg, TRIG_PIN_4/ECHO_PIN_4 = mdeg
    $mappingQuery = "SELECT zdeg, ndeg, odeg, mdeg FROM sorter_mapping WHERE device_identity = ?";
    $mappingStmt = $conn->prepare($mappingQuery);
    $mappingStmt->bind_param("s", $device_identity);
    $mappingStmt->execute();
    $mappingResult = $mappingStmt->get_result();
    
    $mapping = ['zdeg' => 'bio', 'ndeg' => 'nbio', 'odeg' => 'hazardous', 'mdeg' => 'mixed'];
    
    if ($mappingResult->num_rows > 0) {
        $mapping = $mappingResult->fetch_assoc();
    }
    
    // Create a reverse map from stored bin_name to the correct mapped trash type
    // bin_fullness.bin_name comes from Arduino in order: "Non-Bio" (sensor 1/zdeg), "Bio" (sensor 2/ndeg), "Hazardous" (sensor 3/odeg), "Mixed" (sensor 4/mdeg)
    // We need to map these to the actual trash types from sorter_mapping
    $sensorToBinType = [
        'Non-Bio' => $mapping['zdeg'],      // Sensor 1 (TRIG_PIN_1/ECHO_PIN_1) = zdeg position
        'Bio' => $mapping['ndeg'],          // Sensor 2 (TRIG_PIN_2/ECHO_PIN_2) = ndeg position
        'Hazardous' => $mapping['odeg'],    // Sensor 3 (TRIG_PIN_3/ECHO_PIN_3) = odeg position
        'Mixed Waste' => $mapping['mdeg']   // Sensor 4 (TRIG_PIN_4/ECHO_PIN_4) = mdeg position
    ];
    
    // Query to get the last 20 entries for the given device
    $query = "
        SELECT bf.*,
               CASE 
                   WHEN distance < 0.5 THEN -1 -- Sensor failure indicator
                   WHEN distance >= 60.96 THEN 0 -- Empty (2 feet or more)
                   ELSE ROUND(100 - ((distance - 0.5) / (60.96 - 0.5) * 100)) -- New calculation matching update_bin_fullness
               END as fullness_percentage
        FROM bin_fullness bf
        WHERE device_identity = ?
        ORDER BY timestamp DESC
        LIMIT 20";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $device_identity);
    $stmt->execute();
    $result = $stmt->get_result();

    $bins = [];
    while ($row = $result->fetch_assoc()) {
        // Map the Arduino sensor bin_name to the dynamically mapped trash type
        $mappedBinName = $sensorToBinType[$row['bin_name']] ?? $row['bin_name'];
        
        $bins[] = [
            'device_identity' => $row['device_identity'],
            'bin_name' => $mappedBinName,
            'distance' => (int)$row['distance'],
            'fullness_percentage' => (int)$row['fullness_percentage'],
            'timestamp' => $row['timestamp']
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $bins]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
