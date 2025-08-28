<?php
session_start();
require_once 'connection.php';


if (!isset($_SESSION['user_id']) || !isset($_COOKIE['user_logged_in'])) {
    http_response_code(401);
    exit('Unauthorized');
}


$stmt = $pdo->query("SELECT 
    CASE 
        WHEN sorted = 'biodegradable' THEN 'Bio'
        WHEN sorted = 'non-biodegradable' THEN 'Non-Bio'
        WHEN sorted = 'hazardous' THEN 'Hazardous'
    END as category,
    COUNT(*) as total_count 
    FROM trash_sorted 
    GROUP BY sorted");
$wasteData = $stmt->fetchAll(PDO::FETCH_ASSOC);


header('Content-Type: application/json');
echo json_encode($wasteData);
?>
