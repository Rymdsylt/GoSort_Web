<?php
header('Content-Type: application/json');
require_once('../gs_DB/connection.php');

// Get data based on requested statistic type
$type = isset($_GET['type']) ? $_GET['type'] : '';
$response = array();

try {
    switch($type) {
        case 'trash_types':
            // Get count of different trash types
            $query = "SELECT sorted, COUNT(*) as count 
                     FROM trash_sorted 
                     GROUP BY sorted";
            $result = $conn->query($query);
            $data = array();
            while($row = $result->fetch_assoc()) {
                $data[$row['sorted']] = (int)$row['count'];
            }
            $response['success'] = true;
            $response['data'] = $data;
            break;

        case 'daily_sorting':
            // Get daily sorting counts for the last 7 days
            $query = "SELECT DATE(time) as date, COUNT(*) as count 
                     FROM trash_sorted 
                     WHERE time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                     GROUP BY DATE(time)
                     ORDER BY date";
            $result = $conn->query($query);
            $data = array();
            while($row = $result->fetch_assoc()) {
                $data[$row['date']] = (int)$row['count'];
            }
            $response['success'] = true;
            $response['data'] = $data;
            break;

        case 'bin_status':
            // Get latest bin fullness levels for each device
            $query = "SELECT device_identity, bin_name, distance 
                     FROM bin_fullness bf1 
                     WHERE timestamp = (
                         SELECT MAX(timestamp) 
                         FROM bin_fullness bf2 
                         WHERE bf1.device_identity = bf2.device_identity 
                         AND bf1.bin_name = bf2.bin_name
                     )";
            $result = $conn->query($query);
            $data = array();
            while($row = $result->fetch_assoc()) {
                if (!isset($data[$row['device_identity']])) {
                    $data[$row['device_identity']] = array();
                }
                $data[$row['device_identity']][$row['bin_name']] = (int)$row['distance'];
            }
            $response['success'] = true;
            $response['data'] = $data;
            break;

        case 'sorter_activity':
            // Get sorting activity per sorter
            $query = "SELECT device_identity, 
                            COUNT(*) as total_sorts,
                            SUM(CASE WHEN trash_type = 'bio' THEN 1 ELSE 0 END) as bio_count,
                            SUM(CASE WHEN trash_type = 'nbio' THEN 1 ELSE 0 END) as nbio_count,
                            SUM(CASE WHEN trash_type = 'hazardous' THEN 1 ELSE 0 END) as hazardous_count,
                            SUM(CASE WHEN trash_type = 'mixed' THEN 1 ELSE 0 END) as mixed_count
                     FROM sorting_history
                     GROUP BY device_identity";
            $result = $conn->query($query);
            $data = array();
            while($row = $result->fetch_assoc()) {
                $data[$row['device_identity']] = array(
                    'total_sorts' => (int)$row['total_sorts'],
                    'bio' => (int)$row['bio_count'],
                    'nbio' => (int)$row['nbio_count'],
                    'hazardous' => (int)$row['hazardous_count'],
                    'mixed' => (int)$row['mixed_count']
                );
            }
            $response['success'] = true;
            $response['data'] = $data;
            break;

        default:
            throw new Exception('Invalid statistics type requested');
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>