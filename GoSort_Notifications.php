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
        .notification-item {
            border-left: 4px solid #7AF146;
            margin-bottom: 15px;
            padding: 15px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .notification-item.unread {
            background-color: #f8fff5;
        }
        .notification-time {
            color: #6c757d;
            font-size: 0.875rem;
        }
        .delete-btn {
            float: right;
            color: #dc3545;
            cursor: pointer;
            padding: 5px;
            transition: color 0.2s;
        }
        .delete-btn:hover {
            color: #bd2130;
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showError(message) {
            $('.notification-container').html(`
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    ${message}
                </div>
            `);
        }

        function loadNotifications() {
            $.ajax({
                url: 'gs_DB/get_bin_notifications.php',
                method: 'GET',
                success: function(response) {
                    try {
                        $('.notification-container').html(response);
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

        // Handle marking notifications as read
        $(document).on('click', '.notification-item.unread', function(e) {
            if ($(e.target).hasClass('delete-btn') || $(e.target).closest('.delete-btn').length) {
                return; // Don't mark as read if clicking delete button
            }
            const notificationId = $(this).data('id');
            $.ajax({
                url: 'gs_DB/mark_bin_notification_read.php',
                method: 'POST',
                data: { notification_id: notificationId },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            loadNotifications(); // Refresh the notifications
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
            e.stopPropagation(); // Prevent triggering the mark as read event
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
                        loadNotifications(); // Refresh the notifications
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
            loadNotifications();
            // Refresh notifications every 30 seconds
            setInterval(loadNotifications, 30000);
        });
    </script>
</body>
</html>