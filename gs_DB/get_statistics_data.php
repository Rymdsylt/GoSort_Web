<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id']) || !isset($_COOKIE['user_logged_in'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Get device info if device ID is provided
$device_id = $_GET['device'] ?? null;
$device_identity = $_GET['identity'] ?? null;

// Prepare the base SQL query
$base_query = "
    SELECT 
        trash_type,
        COUNT(*) as count,
        is_maintenance,
        DATE(sorted_at) as date
    FROM sorting_history";

if ($device_id && $device_identity) {
    // Get sorting history for specific device
    $stmt = $pdo->prepare($base_query . " 
        WHERE device_identity = ?
        GROUP BY trash_type, is_maintenance, DATE(sorted_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$device_identity]);
} else {
    // Get overall sorting history
    $stmt = $pdo->prepare($base_query . " 
        GROUP BY trash_type, is_maintenance, DATE(sorted_at)
        ORDER BY date ASC
    ");
    $stmt->execute();
}

// Fetch and process data
$sorting_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$dates = array();
$bio_counts = array();
$nbio_counts = array();
$hazardous_counts = array();
$mixed_counts = array();
$maintenance_counts = array();

foreach ($sorting_data as $record) {
    $date = $record['date'];
    if (!in_array($date, $dates)) {
        $dates[] = $date;
    }
    
    $count = intval($record['count']);
    $type = $record['trash_type'];
    $is_maintenance = $record['is_maintenance'];

    if ($is_maintenance) {
        $maintenance_counts[$date][$type] = $count;
    } else {
        switch ($type) {
            case 'bio':
                $bio_counts[$date] = ($bio_counts[$date] ?? 0) + $count;
                break;
            case 'nbio':
                $nbio_counts[$date] = ($nbio_counts[$date] ?? 0) + $count;
                break;
            case 'hazardous':
                $hazardous_counts[$date] = ($hazardous_counts[$date] ?? 0) + $count;
                break;
            case 'mixed':
                $mixed_counts[$date] = ($mixed_counts[$date] ?? 0) + $count;
                break;
        }
    }
}

// Calculate totals
$total_bio = array_sum($bio_counts);
$total_nbio = array_sum($nbio_counts);
$total_hazardous = array_sum($hazardous_counts);
$total_mixed = array_sum($mixed_counts);

// Calculate maintenance totals
$maintenance_bio = 0;
$maintenance_nbio = 0;
$maintenance_hazardous = 0;
$maintenance_mixed = 0;

foreach ($maintenance_counts as $date_counts) {
    $maintenance_bio += isset($date_counts['bio']) ? $date_counts['bio'] : 0;
    $maintenance_nbio += isset($date_counts['nbio']) ? $date_counts['nbio'] : 0;
    $maintenance_hazardous += isset($date_counts['hazardous']) ? $date_counts['hazardous'] : 0;
    $maintenance_mixed += isset($date_counts['mixed']) ? $date_counts['mixed'] : 0;
}

// Prepare response data
$response = [
    'dates' => $dates,
    'totals' => [
        'bio' => $total_bio,
        'nbio' => $total_nbio,
        'hazardous' => $total_hazardous,
        'mixed' => $total_mixed
    ],
    'trends' => [
        'bio' => array_map(function($date) use ($bio_counts) { return $bio_counts[$date] ?? 0; }, $dates),
        'nbio' => array_map(function($date) use ($nbio_counts) { return $nbio_counts[$date] ?? 0; }, $dates),
        'hazardous' => array_map(function($date) use ($hazardous_counts) { return $hazardous_counts[$date] ?? 0; }, $dates),
        'mixed' => array_map(function($date) use ($mixed_counts) { return $mixed_counts[$date] ?? 0; }, $dates)
    ],
    'maintenance' => [
        'normal' => [
            'bio' => $total_bio - $maintenance_bio,
            'nbio' => $total_nbio - $maintenance_nbio,
            'hazardous' => $total_hazardous - $maintenance_hazardous,
            'mixed' => $total_mixed - $maintenance_mixed
        ],
        'maintenance' => [
            'bio' => $maintenance_bio,
            'nbio' => $maintenance_nbio,
            'hazardous' => $maintenance_hazardous,
            'mixed' => $maintenance_mixed
        ]
    ]
];

header('Content-Type: application/json');
echo json_encode($response);
