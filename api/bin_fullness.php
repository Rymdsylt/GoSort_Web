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
    // Define sensor mapping (physical hardware pins)
    // trig/echo_1 = front left (zdeg)
    // trig/echo_2 = back left (ndeg)
    // trig/echo_3 = back right (odeg)
    // trig/echo_4 = front right (mdeg)
    $sensor_to_position = [
        'trig/echo_1' => 'zdeg',
        'trig/echo_2' => 'ndeg',
        'trig/echo_3' => 'odeg',
        'trig/echo_4' => 'mdeg'
    ];

    // Fetch sorter mapping for this device
    $mapping_query = "SELECT zdeg, ndeg, odeg, mdeg FROM sorter_mapping WHERE device_identity = ?";
    $mapping_stmt = $conn->prepare($mapping_query);
    $mapping_stmt->bind_param("s", $device_identity);
    $mapping_stmt->execute();
    $mapping_result = $mapping_stmt->get_result();
    
    // Use default mapping if not found
    $sorter_mapping = [
        'zdeg' => 'bio',
        'ndeg' => 'nbio',
        'odeg' => 'hazardous',
        'mdeg' => 'mixed'
    ];
    
    if ($mapping_row = $mapping_result->fetch_assoc()) {
        $sorter_mapping = $mapping_row;
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
        // Map the bin_name from database (which is stored as trash type) to the correct servo position
        $trash_type = strtolower($row['bin_name']);
        $mapped_position = null;
        
        // Find which servo position maps to this trash type
        foreach ($sorter_mapping as $position => $type) {
            if (strtolower($type) === $trash_type) {
                $mapped_position = $position;
                break;
            }
        }
        
        // Determine sensor number from mapped position
        $sensor_name = $trash_type;
        if ($mapped_position) {
            foreach ($sensor_to_position as $sensor => $position) {
                if ($position === $mapped_position) {
                    $sensor_name = $sensor;
                    break;
                }
            }
        }
        
        $bins[] = [
            'device_identity' => $row['device_identity'],
            'bin_name' => $row['bin_name'],
            'sensor' => $sensor_name,
            'servo_position' => $mapped_position,
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
