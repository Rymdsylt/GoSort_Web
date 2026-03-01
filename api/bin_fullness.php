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
    // Get the sorter mapping for this device to translate bin names dynamically
    $mappingQuery = "SELECT zdeg, ndeg, odeg, mdeg FROM sorter_mapping WHERE device_identity = ?";
    $mappingStmt = $conn->prepare($mappingQuery);
    $mappingStmt->bind_param("s", $device_identity);
    $mappingStmt->execute();
    $mappingResult = $mappingStmt->get_result();
    
    // Default mapping if not found (default Arduino layout)
    $sensorMap = [
        1 => 'nbio',      // case 1 sends "Non-Bio" → maps to ndeg
        2 => 'bio',       // case 2 sends "Bio" → maps to zdeg
        3 => 'hazardous', // case 3 sends "Hazardous" → maps to odeg
        4 => 'mixed'      // case 4 sends "Mixed" → maps to mdeg
    ];
    
    if ($mappingResult && $mappingRow = $mappingResult->fetch_assoc()) {
        // Map Arduino sensor cases to their configured trash types from sorter_mapping
        $sensorMap = [
            1 => $mappingRow['ndeg'],  // Arduino case 1 (Non-Bio) → configured ndeg
            2 => $mappingRow['zdeg'],  // Arduino case 2 (Bio) → configured zdeg
            3 => $mappingRow['odeg'],  // Arduino case 3 (Hazardous) → configured odeg
            4 => $mappingRow['mdeg']   // Arduino case 4 (Mixed) → configured mdeg
        ];
    }
    
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
        // Map Arduino's hardcoded bin names to sensor cases
        $sensorCase = 0;
        switch($row['bin_name']) {
            case 'Non-Bio':
                $sensorCase = 1;
                break;
            case 'Bio':
                $sensorCase = 2;
                break;
            case 'Hazardous':
                $sensorCase = 3;
                break;
            case 'Mixed':
                $sensorCase = 4;
                break;
        }
        
        // Get the configured trash type for this sensor case from sorter_mapping
        $configuredType = $sensorMap[$sensorCase] ?? 'mixed';
        
        $bins[] = [
            'device_identity' => $row['device_identity'],
            'bin_name' => $configuredType,
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
