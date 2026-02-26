<?php
require_once 'bin_notifications_DB.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark all as read
    if (isset($_POST['mark_all']) && $_POST['mark_all']) {
        if (markAllBinNotificationsAsRead()) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to mark all notifications as read']);
        }
    }
    // Mark single as read
    elseif (isset($_POST['notification_id'])) {
        $notificationId = $_POST['notification_id'];
        
        if (markBinNotificationAsRead($notificationId)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>