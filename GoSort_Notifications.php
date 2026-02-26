<?php
session_start();
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';
require_once 'gs_DB/bin_notifications_DB.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'delete' && isset($_POST['notification_id'])) {
        try {
            $notification_id = intval($_POST['notification_id']);
            $stmt = $pdo->prepare("DELETE FROM bin_notifications WHERE id = ?");
            $success = $stmt->execute([$notification_id]);
            echo json_encode(['success' => $success]);
        } catch (PDOException $e) {
            error_log("Error deleting notification: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        }
        exit;
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: GoSort_Login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: GoSort_Login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode-global.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="js/theme-manager.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif !important;
            background-color: #F3F3EF !important;
            color: var(--dark-gray);
        }
        #main-content-wrapper {
            margin-left: 260px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        #main-content-wrapper.collapsed {
            margin-left: 70px;
        }
        .page-header {
            padding: 1rem 0 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 0;
            margin-left: 6px;
        }

        .unread-badge {
            background-color: #7AF146;
            color: #1a3a0a;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
        }

        .notification-item {
            border-left: 4px solid #d0d0d0;
            margin-bottom: 12px;
            padding: 16px 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            transition: all 0.2s ease;
        }
        .notification-item:hover {
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
        }
        .notification-item.unread {
            border-left-color: #7AF146;
            background-color: #f8fff5;
        }
        .notification-item.read {
            opacity: 0.75;
        }

        .notif-message {
            font-size: 0.93rem;
            font-weight: 500;
            color: #333;
            line-height: 1.5;
        }

        .notif-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
        }

        .notif-meta-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.78rem;
            color: #555;
            background: #f0f0f0;
            padding: 0.2rem 0.55rem;
            border-radius: 5px;
            font-weight: 500;
        }

        .notification-time {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .mark-read-btn {
            border-radius: 6px;
            font-size: 0.78rem;
            padding: 0.2rem 0.5rem;
            border-color: #368137;
            color: #368137;
        }
        .mark-read-btn:hover {
            background-color: #368137;
            color: #fff;
        }

        .mark-all-btn {
            font-size: 0.85rem;
            font-weight: 500;
            color: #368137;
            border: 1px solid #368137;
            border-radius: 8px;
            padding: 0.35rem 0.9rem;
            transition: all 0.2s ease;
        }
        .mark-all-btn:hover {
            background-color: #368137;
            color: #fff;
        }

        .view-read-btn {
            font-size: 0.85rem;
            font-weight: 500;
            color: #6c757d;
            border: 1px solid #ced4da;
            border-radius: 8px;
            padding: 0.35rem 0.9rem;
            transition: all 0.2s ease;
        }
        .view-read-btn:hover {
            background-color: #6c757d;
            color: #fff;
        }

        #readNotificationsModal .notification-item {
            border-left-color: #d0d0d0;
            opacity: 0.85;
            margin-bottom: 10px;
        }

        #readNotificationsModal .modal-header {
            font-family: 'Inter', sans-serif;
        }

        .delete-btn {
            color: #dc3545;
            cursor: pointer;
            padding: 5px;
            transition: color 0.2s;
            font-size: 1rem;
        }
        .delete-btn:hover {
            color: #bd2130;
        }

        .pagination .page-link {
            color: #368137;
            border-color: #ddd;
            font-size: 0.88rem;
        }
        .pagination .page-item.active .page-link {
            background-color: #368137;
            border-color: #368137;
            color: #fff;
        }
        .pagination .page-link:hover {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .pagination .page-item.disabled .page-link {
            color: #aaa;
        }

        @media (max-width: 992px) {
            #main-content-wrapper {
                margin-left: 0;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container-fluid">
            <div class="page-header">
                <div class="header-left">
                    <h1 class="page-title">Notifications</h1>
                    <span class="unread-badge" id="unreadBadge" style="display:none;">0 unread</span>
                </div>
                <div>
                    <button class="btn view-read-btn me-2" id="viewReadBtn" style="display:none;">
                        <i class="bi bi-envelope-open me-1"></i>Read <span id="readCountBadge" class="badge bg-secondary ms-1">0</span>
                    </button>
                    <button class="btn mark-all-btn" id="markAllReadBtn" style="display:none;">
                        <i class="bi bi-check2-all me-1"></i>Mark All as Read
                    </button>
                </div>
            </div>

            <hr style="height: 1.5px; background-color: #000; opacity: 1; margin-left:6.5px;" class="mb-3">

            
            <div class="notification-container">
                <!-- Notifications will be loaded here dynamically -->
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure this is resolved?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Read Notifications Modal -->
    <div class="modal fade" id="readNotificationsModal" tabindex="-1" aria-labelledby="readNotificationsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: #f8f9fa; border-bottom: 2px solid #e0e0e0;">
                    <h5 class="modal-title" id="readNotificationsModalLabel">
                        <i class="bi bi-envelope-open me-2" style="color: #368137;"></i>Read Notifications
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 65vh; overflow-y: auto; padding: 1.25rem;">
                    <div class="read-notification-container">
                        <div class="text-center py-4">
                            <div class="spinner-border text-success" role="status"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPage = 1;
        let readModalPage = 1;

        function showError(message) {
            $('.notification-container').html(`
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    ${message}
                </div>
            `);
        }

        function loadNotifications(page) {
            page = page || currentPage;
            $.ajax({
                url: 'gs_DB/get_bin_notifications.php',
                method: 'GET',
                data: { page: page, filter: 'unread' },
                success: function(response) {
                    try {
                        $('.notification-container').html(response);
                        var info = $('.notification-container #pagination-info');
                        if (info.length) {
                            currentPage = parseInt(info.attr('data-page'));
                            var unread = parseInt(info.attr('data-unread'));
                            var readCount = parseInt(info.attr('data-read-count'));
                            if (unread > 0) {
                                $('#unreadBadge').text(unread + ' unread').show();
                                $('#markAllReadBtn').show();
                            } else {
                                $('#unreadBadge').hide();
                                $('#markAllReadBtn').hide();
                            }
                            if (readCount > 0) {
                                $('#readCountBadge').text(readCount);
                                $('#viewReadBtn').show();
                            } else {
                                $('#viewReadBtn').hide();
                            }
                        }
                    } catch (e) {
                        showError('Error displaying notifications. Please try refreshing the page.');
                        console.error('Error parsing notifications:', e);
                    }
                },
                error: function(xhr, status, error) {
                    showError('Unable to load notifications. Please try again later.');
                    console.error('Error loading notifications:', error);
                }
            });
        }

        function loadReadNotifications(page) {
            page = page || 1;
            $.ajax({
                url: 'gs_DB/get_bin_notifications.php',
                method: 'GET',
                data: { page: page, filter: 'read' },
                success: function(response) {
                    $('.read-notification-container').html(response);
                    var info = $('.read-notification-container #pagination-info');
                    if (info.length) {
                        readModalPage = parseInt(info.attr('data-page'));
                    }
                },
                error: function() {
                    $('.read-notification-container').html('<div class="alert alert-danger">Failed to load read notifications.</div>');
                }
            });
        }

        // Main page pagination
        $(document).on('click', '.unread-pagination .page-link', function(e) {
            e.preventDefault();
            var page = parseInt($(this).attr('data-page'));
            if (page && !$(this).closest('.page-item').hasClass('disabled') && !$(this).closest('.page-item').hasClass('active')) {
                loadNotifications(page);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });

        // Modal pagination
        $(document).on('click', '.read-pagination .page-link', function(e) {
            e.preventDefault();
            var page = parseInt($(this).attr('data-page'));
            if (page && !$(this).closest('.page-item').hasClass('disabled') && !$(this).closest('.page-item').hasClass('active')) {
                loadReadNotifications(page);
            }
        });

        // Open read notifications modal
        $('#viewReadBtn').on('click', function() {
            readModalPage = 1;
            loadReadNotifications(1);
            var modal = new bootstrap.Modal(document.getElementById('readNotificationsModal'));
            modal.show();
        });

        // Handle mark-as-read button click
        $(document).on('click', '.mark-read-btn', function(e) {
            e.stopPropagation();
            const btn = $(this);
            const notificationId = btn.closest('.notification-item').data('id');
            $.ajax({
                url: 'gs_DB/mark_bin_notification_read.php',
                method: 'POST',
                data: { notification_id: notificationId },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            loadNotifications(currentPage);
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                    }
                }
            });
        });

        // Handle mark all as read
        $('#markAllReadBtn').on('click', function() {
            $.ajax({
                url: 'gs_DB/mark_bin_notification_read.php',
                method: 'POST',
                data: { mark_all: true },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            loadNotifications(1);
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                    }
                }
            });
        });

        let currentNotificationId = null;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));

        // Handle deleting notifications
        $(document).on('click', '.delete-btn', function(e) {
            e.stopPropagation();
            currentNotificationId = $(this).closest('.notification-item').data('id');
            deleteModal.show();
        });

        // Handle delete confirmation
        $('#confirmDeleteBtn').on('click', function() {
            if (!currentNotificationId) return;
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: { 
                    action: 'delete',
                    notification_id: currentNotificationId 
                },
                success: function(response) {
                    if (response.success) {
                        deleteModal.hide();
                        loadNotifications(currentPage);
                        // Also refresh the read modal if it's open
                        if ($('#readNotificationsModal').hasClass('show')) {
                            loadReadNotifications(readModalPage);
                        }
                    } else {
                        showError(response.message || 'Failed to delete notification. Please try again.');
                    }
                },
                error: function() {
                    showError('Error deleting notification. Please try again.');
                }
            });
        });

        // Load notifications when page loads
        $(document).ready(function() {
            loadNotifications(1);
            setInterval(function() { loadNotifications(currentPage); }, 30000);
        });
    </script>
</body>
</html>