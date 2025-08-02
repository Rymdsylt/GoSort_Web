<?php
require_once 'connection.php';

// Set maintenance mode flag in a file that GoSort.py will check
$maintenanceFile = "../python_maintenance_mode.txt";
$response = array();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $mode = isset($_POST['mode']) ? $_POST['mode'] : '';
        $device_id = isset($_POST['device_id']) ? $_POST['device_id'] : null;
        $device_identity = isset($_POST['device_identity']) ? $_POST['device_identity'] : null;
        
        if ($mode === 'enable') {
            file_put_contents($maintenanceFile, "1");
            
            // If device information is provided, update database maintenance mode
            if ($device_id && $device_identity) {
                $stmt = $pdo->prepare("UPDATE sorters SET maintenance_mode = 1 WHERE id = ? AND device_identity = ? AND status = 'online'");
                $result = $stmt->execute([$device_id, $device_identity]);
                
                if (!$result) {
                    throw new Exception('Failed to update device maintenance mode in database');
                }
            }
            
            $response['status'] = 'success';
            $response['message'] = 'Maintenance mode enabled';
        } elseif ($mode === 'disable') {
            if (file_exists($maintenanceFile)) {
                unlink($maintenanceFile);
            }
            
            // If device information is provided, update database maintenance mode
            if ($device_id && $device_identity) {
                $stmt = $pdo->prepare("UPDATE sorters SET maintenance_mode = 0 WHERE id = ? AND device_identity = ?");
                $result = $stmt->execute([$device_id, $device_identity]);
                
                if (!$result) {
                    throw new Exception('Failed to update device maintenance mode in database');
                }
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
?>
