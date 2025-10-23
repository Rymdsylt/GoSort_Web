<?php
header('Content-Type: application/json');
require_once '../gs_DB/connection.php';

// Get the device identity from the query parameters
$device_identity = $_GET['identity'] ?? null;

if (!$device_identity) {
    echo json_encode(['success' => false, 'message' => 'Device identity is required']);
    exit;
}

try {
    // Get the most recent detection for this device
    $query = "
        SELECT 
            id,
            trash_type,
            trash_class,
            confidence,
            image_data,
            sorted_at
        FROM sorting_history 
        WHERE device_identity = ? 
        ORDER BY sorted_at DESC 
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$device_identity]);
    $detection = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($detection) {
        echo json_encode([
            'success' => true,
            'data' => $detection
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => null
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>