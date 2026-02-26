<?php
require_once 'bin_notifications_DB.php';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
// filter: 'unread' = only unread (default for main page), 'read' = only read (for modal)
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'unread';
$readFilter = ($filter === 'read') ? 1 : 0;
$paginationClass = ($filter === 'read') ? 'read-pagination' : 'unread-pagination';

$notifications = getBinNotifications($limit, $page, $readFilter);
$totalCount = countBinNotifications($readFilter);
$unreadCount = countUnreadBinNotifications();
$readCount = countBinNotifications(1);
$totalPages = max(1, ceil($totalCount / $limit));

if (empty($notifications)) {
    $emptyMsg = ($filter === 'read') ? 'No read notifications.' : 'No new notifications.';
    $emptyIcon = ($filter === 'read') ? 'bi-check2-circle' : 'bi-bell-slash';
    echo '<div class="text-center py-5">
            <i class="bi ' . $emptyIcon . '" style="font-size: 3rem; color: #c0c0c0;"></i>
            <p class="mt-3 text-muted">' . $emptyMsg . '</p>
          </div>';
} else {
    foreach ($notifications as $notification) {
        $readClass = $notification['is_read'] ? 'read' : 'unread';
        $priorityBadge = '';
        
        switch ($notification['priority']) {
            case 'critical':
                $priorityBadge = '<span class="badge bg-danger">Critical</span>';
                break;
            case 'high':
                $priorityBadge = '<span class="badge bg-danger">High Priority</span>';
                break;
            case 'medium':
                $priorityBadge = '<span class="badge bg-warning text-dark">Medium</span>';
                break;
        }

        $time = new DateTime($notification['created_at']);
        $now = new DateTime();
        $diff = $now->diff($time);
        
        if ($diff->days == 0) {
            if ($diff->h == 0) {
                if ($diff->i == 0) {
                    $timeAgo = 'Just now';
                } else {
                    $timeAgo = $diff->i . 'm ago';
                }
            } else {
                $timeAgo = $diff->h . 'h ago';
            }
        } elseif ($diff->days == 1) {
            $timeAgo = 'Yesterday, ' . $time->format('g:i A');
        } elseif ($diff->days < 7) {
            $timeAgo = $diff->days . 'd ago';
        } else {
            $timeAgo = $time->format('M j, Y');
        }

        // Build device/location info with better naming
        $deviceName = !empty($notification['device_name']) ? htmlspecialchars($notification['device_name']) : null;
        $location = !empty($notification['location']) ? htmlspecialchars($notification['location']) : null;
        $deviceIdentity = !empty($notification['device_identity']) ? htmlspecialchars($notification['device_identity']) : null;
        $binName = !empty($notification['bin_name']) ? htmlspecialchars($notification['bin_name']) : null;
        $fullness = $notification['fullness_level'];

        $metaTags = '';
        if ($location) {
            $metaTags .= '<span class="notif-meta-tag"><i class="bi bi-geo-alt"></i> ' . $location . '</span>';
        }
        if ($deviceName) {
            $metaTags .= '<span class="notif-meta-tag"><i class="bi bi-cpu"></i> ' . $deviceName . '</span>';
        } elseif ($deviceIdentity) {
            $metaTags .= '<span class="notif-meta-tag"><i class="bi bi-cpu"></i> ' . $deviceIdentity . '</span>';
        }
        if ($binName) {
            $metaTags .= '<span class="notif-meta-tag"><i class="bi bi-trash3"></i> ' . $binName . '</span>';
        }
        if ($fullness !== null && $fullness >= 0) {
            $fullnessClass = $fullness >= 90 ? 'text-danger' : ($fullness >= 70 ? 'text-warning' : 'text-success');
            $metaTags .= '<span class="notif-meta-tag ' . $fullnessClass . '"><i class="bi bi-bar-chart-fill"></i> ' . $fullness . '% Full</span>';
        } elseif ($fullness !== null && $fullness == -1) {
            $metaTags .= '<span class="notif-meta-tag text-danger"><i class="bi bi-exclamation-triangle"></i> Sensor Failure</span>';
        }

        $readBtn = '';
        if (!$notification['is_read']) {
            $readBtn = '<button class="btn btn-sm btn-outline-success mark-read-btn" title="Mark as read"><i class="bi bi-check2"></i></button>';
        }
        
        echo '<div class="notification-item ' . $readClass . '" data-id="' . $notification['id'] . '">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <div class="notif-meta">' . $metaTags . '</div>
                    <div class="d-flex align-items-center gap-2">
                        ' . $priorityBadge . '
                        ' . $readBtn . '
                        <i class="bi bi-trash delete-btn" title="Delete notification"></i>
                    </div>
                </div>
                <p class="mb-1 notif-message">' . htmlspecialchars($notification['message']) . '</p>
                <small class="notification-time"><i class="bi bi-clock"></i> ' . $timeAgo . '</small>
              </div>';
    }

    // Pagination controls
    if ($totalPages > 1) {
        echo '<nav class="mt-4"><ul class="pagination justify-content-center ' . $paginationClass . '">';
        
        // Previous button
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        echo '<li class="page-item ' . $prevDisabled . '"><a class="page-link" href="#" data-page="' . ($page - 1) . '"><i class="bi bi-chevron-left"></i> Prev</a></li>';
        
        // Page numbers
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        
        if ($startPage > 1) {
            echo '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
            if ($startPage > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        
        for ($i = $startPage; $i <= $endPage; $i++) {
            $active = ($i === $page) ? 'active' : '';
            echo '<li class="page-item ' . $active . '"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
        }
        
        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            echo '<li class="page-item"><a class="page-link" href="#" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
        }
        
        // Next button
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<li class="page-item ' . $nextDisabled . '"><a class="page-link" href="#" data-page="' . ($page + 1) . '">Next <i class="bi bi-chevron-right"></i></a></li>';
        
        echo '</ul></nav>';
    }

    // Hidden element for JS to read pagination info
    echo '<div id="pagination-info" data-page="' . $page . '" data-total="' . $totalPages . '" data-unread="' . $unreadCount . '" data-read-count="' . $readCount . '" data-filter="' . $filter . '" style="display:none;"></div>';
}
?>