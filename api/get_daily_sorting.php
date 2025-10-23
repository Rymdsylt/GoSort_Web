<?php
header('Content-Type: application/json');
require_once '../gs_DB/connection.php';

try {
    // Get today's sorting data grouped by type
    $query = "
        SELECT 
            trash_type,
            COUNT(*) as count,
            AVG(confidence) as avg_confidence,
            device_identity,
            MAX(image_data) as latest_image,
            GROUP_CONCAT(DISTINCT trash_class) as detected_items
        FROM sorting_history 
        WHERE DATE(sorted_at) = CURDATE()
        GROUP BY trash_type, device_identity
        ORDER BY sorted_at DESC
    ";
    
    $stmt = $pdo->query($query);
    $sortingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for today
    $totalQuery = "
        SELECT COUNT(*) as total
        FROM sorting_history 
        WHERE DATE(sorted_at) = CURDATE()
    ";
    $totalStmt = $pdo->query($totalQuery);
    $totalData = $totalStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $sortingData,
        'total' => $totalData['total']
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>