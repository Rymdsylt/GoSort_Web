<?php
/**
 * API endpoint for fetching activity logs
 */

require_once '../gs_DB/connection.php';
require_once '../gs_DB/activity_logs.php';

header('Content-Type: application/json');

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$category = $_GET['category'] ?? null;
$subtopic = $_GET['subtopic'] ?? null;
$all = isset($_GET['all']) ? true : false;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

try {
    if ($all) {
        // Fetch all logs without filtering
        $logs = get_activity_logs(null, null, $limit);
    } else if ($subtopic) {
        $logs = get_logs_by_subtopic($category, $subtopic, $limit);
    } else if ($category) {
        $logs = get_activity_logs($category, null, $limit);
    } else {
        $logs = get_activity_logs(null, null, $limit);
    }
    
    // Format the logs for display
    $formatted_logs = array_map(function($log) {
        return [
            'id' => $log['id'],
            'date' => date('Y-m-d h:i A', strtotime($log['created_at'])),
            'user' => $log['username'] ?? 'System',
            'device' => $log['device_name'] ?? $log['device_identity'] ?? '-',
            'action' => $log['action'],
            'details' => $log['details'],
            'category' => $log['category'],
            'ip_address' => $log['ip_address'] ?? '-'
        ];
    }, $logs);
    
    echo json_encode([
        'success' => true,
        'logs' => $formatted_logs,
        'count' => count($formatted_logs)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching logs: ' . $e->getMessage()
    ]);
}
?>
