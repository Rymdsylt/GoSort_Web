<?php
require_once 'notifications_DB.php';

$notifications = getNotifications();

if (empty($notifications)) {
    echo '<div class="alert alert-info">No notifications available.</div>';
} else {
    foreach ($notifications as $notification) {
        $readClass = $notification['is_read'] ? '' : 'unread';
        $priorityBadge = '';
        
        switch ($notification['priority']) {
            case 'high':
                $priorityBadge = '<span class="badge bg-danger">High Priority</span>';
                break;
            case 'medium':
                $priorityBadge = '<span class="badge bg-warning">Medium Priority</span>';
                break;
        }

        $time = new DateTime($notification['created_at']);
        $timeAgo = $time->format('Y-m-d H:i:s');
        
        echo '<div class="notification-item ' . $readClass . '" data-id="' . $notification['id'] . '">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="notification-content">
                        <p class="mb-1">' . htmlspecialchars($notification['message']) . '</p>
                        <small class="notification-time">
                            <i class="bi bi-clock"></i> ' . $timeAgo . '
                            ' . ($notification['device_id'] ? ' | Device: ' . htmlspecialchars($notification['device_id']) : '') . '
                        </small>
                    </div>
                    <div>' . $priorityBadge . '</div>
                </div>
              </div>';
    }
}
?>