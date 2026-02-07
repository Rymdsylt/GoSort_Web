<?php
/**
 * Activity Logs Helper Functions
 * Handles logging and retrieving activity logs for the GoSort system
 */

require_once __DIR__ . '/connection.php';

/**
 * Log an activity to the database
 * 
 * @param string $category - Main category: 'devices', 'analytics', 'maintenance', 'general'
 * @param string $action - The specific action performed
 * @param string $details - Additional details about the action
 * @param int|null $user_id - The user who performed the action (null for system actions)
 * @param string|null $device_identity - Related device identity if applicable
 * @return bool - Success or failure
 */
function log_activity($category, $action, $details = '', $user_id = null, $device_identity = null) {
    global $pdo;
    
    try {
        // Get username if user_id is provided
        $username = null;
        if ($user_id) {
            $stmt = $pdo->prepare("SELECT userName, lastName FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $username = trim($user['userName'] . ' ' . $user['lastName']);
            }
        }
        
        // Get device name if device_identity is provided
        $device_name = null;
        if ($device_identity) {
            $stmt = $pdo->prepare("SELECT device_name FROM sorters WHERE device_identity = ?");
            $stmt->execute([$device_identity]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($device) {
                $device_name = $device['device_name'];
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (category, action, details, user_id, username, device_identity, device_name, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        
        return $stmt->execute([
            $category,
            $action,
            $details,
            $user_id,
            $username,
            $device_identity,
            $device_name,
            $ip_address
        ]);
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get activity logs with optional filtering
 * 
 * @param string|null $category - Filter by category
 * @param string|null $action_filter - Filter by action type
 * @param int $limit - Number of records to return
 * @param int $offset - Offset for pagination
 * @return array - Array of activity logs
 */
function get_activity_logs($category = null, $action_filter = null, $limit = 50, $offset = 0) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM activity_logs WHERE 1=1";
        $params = [];
        
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        if ($action_filter) {
            $sql .= " AND action LIKE ?";
            $params[] = "%$action_filter%";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching activity logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Get activity logs by subtopic
 * 
 * @param string $category - Main category
 * @param string $subtopic - Subtopic to filter by
 * @param int $limit - Number of records to return
 * @return array - Array of activity logs
 */
function get_logs_by_subtopic($category, $subtopic, $limit = 100) {
    global $pdo;
    
    try {
        $action_mapping = [
            // Devices
            'Added Devices' => ['Device Added', 'Device Registered'],
            'Deleted Devices' => ['Device Deleted', 'Device Removed'],
            'Updated Devices' => ['Device Updated', 'Device Modified'],
            
            // Analytics
            'Sorting History' => ['Waste Sorted', 'Sorting Recorded'],
            'Online Activity' => ['Device Online', 'Device Offline', 'Connection Status'],
            
            // Maintenance
            'Device Mapping' => ['Mapping Updated', 'Mapping Changed'],
            'Testing' => ['Test Started', 'Test Completed', 'Bin Test'],
            'Controls' => ['Maintenance Started', 'Maintenance Ended', 'Maintenance Mode'],
            
            // General
            'Login History' => ['Login', 'Logout', 'Login Failed'],
            'Notifications' => ['Notification Deleted', 'Notification Created', 'Notification Read'],
            'User Activity' => ['User Added', 'User Deleted', 'User Updated', 'Profile Updated']
        ];
        
        $actions = $action_mapping[$subtopic] ?? [];
        
        if (empty($actions)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($actions), '?'));
        $sql = "SELECT * FROM activity_logs WHERE category = ? AND action IN ($placeholders) ORDER BY created_at DESC LIMIT ?";
        
        $params = array_merge([$category], $actions, [$limit]);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching logs by subtopic: " . $e->getMessage());
        return [];
    }
}

// Specific logging functions for convenience

function log_login($user_id, $username = null) {
    return log_activity('general', 'Login', "User logged in successfully", $user_id);
}

function log_logout($user_id) {
    return log_activity('general', 'Logout', "User logged out", $user_id);
}

function log_login_failed($username) {
    global $pdo;
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (category, action, details, username, ip_address)
            VALUES ('general', 'Login Failed', ?, ?, ?)
        ");
        return $stmt->execute(["Failed login attempt for username: $username", $username, $ip_address]);
    } catch (PDOException $e) {
        error_log("Error logging failed login: " . $e->getMessage());
        return false;
    }
}

function log_maintenance_started($user_id, $device_identity) {
    return log_activity('maintenance', 'Maintenance Started', "Maintenance mode enabled", $user_id, $device_identity);
}

function log_maintenance_ended($user_id, $device_identity) {
    return log_activity('maintenance', 'Maintenance Ended', "Maintenance mode disabled", $user_id, $device_identity);
}

function log_device_added($user_id, $device_identity, $device_name) {
    return log_activity('devices', 'Device Added', "Added device: $device_name", $user_id, $device_identity);
}

function log_device_deleted($user_id, $device_identity, $device_name) {
    return log_activity('devices', 'Device Deleted', "Deleted device: $device_name", $user_id, $device_identity);
}

function log_notification_deleted($user_id, $notification_type, $message = '') {
    return log_activity('general', 'Notification Deleted', "Deleted notification: $notification_type - $message", $user_id);
}

function log_user_added($admin_user_id, $new_username) {
    return log_activity('general', 'User Added', "Added new user: $new_username", $admin_user_id);
}

function log_mapping_updated($user_id, $device_identity) {
    return log_activity('maintenance', 'Mapping Updated', "Updated sorter mapping", $user_id, $device_identity);
}

function log_bin_test($user_id, $device_identity, $bin_name) {
    return log_activity('maintenance', 'Bin Test', "Tested bin: $bin_name", $user_id, $device_identity);
}
?>
