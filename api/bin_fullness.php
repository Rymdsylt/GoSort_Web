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
    // Query to get the latest record for each bin_name for the given device
    $query = "
        SELECT bf.*,
               CASE 
                   WHEN distance < 0.5 THEN -1 -- Sensor failure indicator
                   WHEN distance <= 10 THEN 100 -- Full bin (capped at 100%)
                   WHEN distance >= 60.96 THEN 0 -- Empty (2 feet or more)
                   ELSE ROUND(((60.96 - distance) / 50.96) * 100) -- Formula: (60.96 - D_measured) / 50.96 × 100
               END as fullness_percentage
        FROM bin_fullness bf
        INNER JOIN (
            SELECT device_identity, bin_name, MAX(timestamp) as max_timestamp
            FROM bin_fullness
            WHERE device_identity = ?
            GROUP BY device_identity, bin_name
        ) latest 
        ON bf.device_identity = latest.device_identity 
        AND bf.bin_name = latest.bin_name 
        AND bf.timestamp = latest.max_timestamp
        ORDER BY bf.bin_name";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $device_identity);
    $stmt->execute();
    $result = $stmt->get_result();

    $bins = [];
    while ($row = $result->fetch_assoc()) {
        $bins[] = [
            'device_identity' => $row['device_identity'],
            'bin_name' => $row['bin_name'],
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
