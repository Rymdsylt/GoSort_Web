<?php
require_once 'bin_notifications_DB.php';

$notifications = getBinNotifications();

if (empty($notifications)) {
    echo '<div class="alert alert-info">No bin notifications available.</div>';
} else {
    foreach ($notifications as $notification) {
        $readClass = $notification['is_read'] ? '' : 'unread';
        $priorityBadge = '';
        
        switch ($notification['priority']) {
            case 'high':
                $priorityBadge = '<span class="badge bg-danger">High Priority</span>';
                break;
            case 'medium':
                $priorityBadge = '<span class="badge bg-warning text-dark">Medium Priority</span>';
                break;
        }

        $time = new DateTime($notification['created_at']);
        $timeAgo = $time->format('Y-m-d H:i:s');
        
        $binInfo = $notification['bin_name'] ? sprintf(' | Bin: %s', htmlspecialchars($notification['bin_name'])) : '';
        $fullnessInfo = $notification['fullness_level'] ? sprintf(' | Fullness: %d%%', $notification['fullness_level']) : '';
        
        echo '<div class="notification-item ' . $readClass . '" data-id="' . $notification['id'] . '">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="notification-content">
                        <p class="mb-1">' . htmlspecialchars($notification['message']) . '</p>
                        <small class="notification-time">
                            <i class="bi bi-clock"></i> ' . $timeAgo . 
                            ($notification['device_identity'] ? ' | Device: ' . htmlspecialchars($notification['device_identity']) : '') .
                            $binInfo . $fullnessInfo . '
                        </small>
                    </div>
                    <div class="d-flex align-items-start gap-2">
                        ' . $priorityBadge . '
                        <i class="bi bi-trash delete-btn" title="Delete notification"></i>
                    </div>
                </div>
              </div>';
    }
}
?>