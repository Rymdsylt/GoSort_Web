<?php
header('Content-Type: application/json');
require_once('../gs_DB/connection.php');

// Check if username is provided
$username = isset($_GET['username']) ? $_GET['username'] : null;
$response = array();

try {
    if (!$username) {
        throw new Exception('Username is required');
    }

    // Get user basic information
    $query = "SELECT 
                u.id,
                u.role,
                u.userName,
                u.lastName,
                u.email,
                u.assigned_floor,
                u.registered_at
              FROM users u
              WHERE u.userName = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('User not found');
    }

    $userData = $result->fetch_assoc();
    $userId = $userData['id'];

    // Get assigned sorters for the user
    $query = "SELECT 
                s.device_name,
                s.device_identity,
                s.location,
                s.status,
                s.maintenance_mode,
                a.assigned_floor,
                a.assigned_at
              FROM assigned_sorters a
              JOIN sorters s ON s.device_identity = a.device_identity
              WHERE a.user_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $assignedSorters = array();
    while ($row = $result->fetch_assoc()) {
        $assignedSorters[] = array(
            'device_name' => $row['device_name'],
            'device_identity' => $row['device_identity'],
            'location' => $row['location'],
            'status' => $row['status'],
            'maintenance_mode' => (bool)$row['maintenance_mode'],
            'assigned_floor' => $row['assigned_floor'],
            'assigned_at' => $row['assigned_at']
        );
    }

    // Get maintenance history (if user is utility personnel)
    $maintenanceHistory = array();
    if ($userData['role'] === 'utility') {
        $query = "SELECT 
                    start_time,
                    end_time,
                    active
                  FROM maintenance_mode
                  WHERE user_id = ?
                  ORDER BY start_time DESC
                  LIMIT 10";  // Get last 10 maintenance sessions

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $maintenanceHistory[] = array(
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'active' => (bool)$row['active']
            );
        }
    }

    // Compile the response
    $response['success'] = true;
    $response['data'] = array(
        'user_info' => $userData,
        'assigned_sorters' => $assignedSorters,
        'maintenance_history' => $maintenanceHistory
    );

} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>