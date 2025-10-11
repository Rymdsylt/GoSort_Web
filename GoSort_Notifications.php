<?php
session_start();
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';
require_once 'gs_DB/bin_notifications_DB.php';

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Inter", sans-serif;
            background-color: #f0f2f5;
        }
        #main-content-wrapper {
            margin-left: 260px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        #main-content-wrapper.collapsed {
            margin-left: 70px;
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
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container-fluid">
            <h2 class="mb-4">Notifications</h2>
            
            <div class="notification-container">
                <!-- Notifications will be loaded here dynamically -->
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
        $(document).on('click', '.notification-item.unread', function() {
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

        // Load notifications when page loads
        $(document).ready(function() {
            loadNotifications();
            // Refresh notifications every 30 seconds
            setInterval(loadNotifications, 30000);
        });
    </script>
</body>
</html>