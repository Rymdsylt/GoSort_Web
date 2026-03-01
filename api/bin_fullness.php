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
    // Get the sorter mapping for this device
    // Sensor mapping (from Arduino):
    // TRIG_PIN_1 / ECHO_PIN_1 = Front - Left (zdeg)
    // TRIG_PIN_2 / ECHO_PIN_2 = Back - Left (ndeg)
    // TRIG_PIN_3 / ECHO_PIN_3 = Back - Right (odeg)
    // TRIG_PIN_4 / ECHO_PIN_4 = Front - Right (mdeg)
    
    $mapping = [];
    $mapStmt = $conn->prepare("SELECT zdeg, ndeg, odeg, mdeg FROM sorter_mapping WHERE device_identity = ?");
    $mapStmt->bind_param("s", $device_identity);
    $mapStmt->execute();
    $mapResult = $mapStmt->get_result();
    
    if ($mapResult && $mapResult->num_rows > 0) {
        $mapRow = $mapResult->fetch_assoc();
        // Map sensor number to trash type based on servo position
        $mapping = [
            1 => $mapRow['zdeg'],    // Sensor 1 -> zdeg (Front - Left)
            2 => $mapRow['ndeg'],    // Sensor 2 -> ndeg (Back - Left)
            3 => $mapRow['odeg'],    // Sensor 3 -> odeg (Back - Right)
            4 => $mapRow['mdeg']     // Sensor 4 -> mdeg (Front - Right)
        ];
    } else {
        // Default mapping if not found
        $mapping = [
            1 => 'bio',
            2 => 'nbio',
            3 => 'hazardous',
            4 => 'mixed'
        ];
    }
    
    // Map internal values to display names
    $trashTypeLabels = [
        'bio' => 'Bio',
        'nbio' => 'Non-Bio',
        'hazardous' => 'Hazardous',
        'mixed' => 'Mixed'
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
        // Determine which sensor this bin_name came from and get the dynamic bin type
        $dynamicBinName = $row['bin_name'];
        
        // Try to match the bin name to determine its sensor position
        foreach ($mapping as $sensorNum => $trashType) {
            // The bin_name in the database should match our dynamically mapped type
            // If bin_name doesn't match current mapping, use as-is for backward compatibility
            if (isset($trashTypeLabels[$trashType])) {
                // Store the dynamically mapped name
                $dynamicBinName = $trashTypeLabels[$trashType];
            }
        }
        
        $bins[] = [
            'device_identity' => $row['device_identity'],
            'bin_name' => $dynamicBinName,
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
