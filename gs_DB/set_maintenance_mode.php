<?php
require_once 'connection.php';

// Set maintenance mode flag in a file that GoSort.py will check
$maintenanceFile = "../python_maintenance_mode.txt";
$response = array();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $mode = isset($_POST['mode']) ? $_POST['mode'] : '';
        
        if ($mode === 'enable') {
            file_put_contents($maintenanceFile, "1");
            $response['status'] = 'success';
            $response['message'] = 'Maintenance mode enabled';
        } elseif ($mode === 'disable') {
            if (file_exists($maintenanceFile)) {
                unlink($maintenanceFile);
            }
            $response['status'] = 'success';
            $response['message'] = 'Maintenance mode disabled';
        } else {
            throw new Exception('Invalid mode specified');
        }
    } else {
        throw new Exception('Invalid request method');
    }
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
