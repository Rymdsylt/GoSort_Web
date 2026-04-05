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
    <link href="css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #274a17;
            --light-green:   #7AF146;
            --mid-green:     #368137;
            --dark-gray:     #1f2937;
            --medium-gray:   #6b7280;
            --border-color:  #e5e7eb;
            --card-shadow:   0 1px 3px rgba(0,0,0,0.07);
        }

        body {
            background-color: #e8f1e6;
            font-family: 'Poppins', sans-serif !important;
            color: var(--dark-gray);
        }

        #main-content-wrapper {
            margin-left: 240px;
            padding: 100px 0 40px 0;
            min-height: 100vh;
            overflow-y: auto;
        }

        /* ── shared containers ── */
        .section-container {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #eeeeee;
        }

        .section-block {
            background: linear-gradient(135deg, rgb(236,251,234) 0%, #d5f5dc 100%);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            min-height: 400px;
        }
        .section-block:last-child { margin-bottom: 0; }

        .section-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #000000b1;
            margin-bottom: 1rem;
        }

        /* ── page header strip ── */
        .page-header-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .ctx-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #e8f5e1;
            border: 1px solid #c8e6c9;
            border-radius: 20px;
            padding: 0.3rem 0.8rem;
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--primary-green);
        }

        /* ── action buttons ── */
        .btn-green {
            background: linear-gradient(135deg, var(--mid-green) 0%, var(--primary-green) 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.35rem 0.8rem;
            font-size: 0.76rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(39,74,23,0.15);
        }
        .btn-green:hover { transform: translateY(-1px); color: #fff; }

        .btn-outline-gray {
            background: #fff;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            padding: 0.35rem 0.8rem;
            font-size: 0.76rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            color: var(--dark-gray);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: border-color 0.2s;
        }
        .btn-outline-gray:hover { border-color: var(--mid-green); color: var(--primary-green); }

        /* ── notification card ── */
        .notif-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem 1.1rem;
            margin-bottom: 0.75rem;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: flex-start;
            gap: 0.9rem;
            position: relative;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }
        .notif-card:last-child { margin-bottom: 0; }
        .notif-card:hover { box-shadow: 0 3px 10px rgba(0,0,0,0.09); }

        .notif-card::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
        }
        .notif-card.unread::before { background: linear-gradient(to bottom, var(--light-green), var(--primary-green)); }
        .notif-card.read::before   { background: #e5e7eb; }

        .notif-icon-wrap {
            width: 38px; height: 38px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
            margin-left: 0.25rem;
        }
        .notif-icon-wrap.unread { background: #e8f5e1; color: var(--primary-green); }
        .notif-icon-wrap.read   { background: #f3f4f6; color: #9ca3af; }

        .notif-body { flex: 1; min-width: 0; }

        .notif-message {
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 0.3rem;
            line-height: 1.45;
        }
        .notif-card.read .notif-message { font-weight: 500; color: var(--medium-gray); }

        .notif-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 0.35rem;
        }

        .notif-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: #f3f4f6;
            border-radius: 6px;
            padding: 0.15rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--medium-gray);
        }
        .notif-tag i { font-size: 0.65rem; }

        .notif-time {
            font-size: 0.72rem;
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .notif-actions {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            flex-shrink: 0;
        }

        .mark-read-btn {
            background: #fff;
            border: 1.5px solid #c8e6c9;
            border-radius: 7px;
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            color: var(--mid-green);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            transition: all 0.2s;
        }
        .mark-read-btn:hover { background: #e8f5e1; }

        .delete-btn {
            background: transparent;
            border: none;
            color: #d1d5db;
            font-size: 0.95rem;
            cursor: pointer;
            padding: 0.2rem 0.3rem;
            border-radius: 6px;
            transition: color 0.2s;
            display: flex; align-items: center;
        }
        .delete-btn:hover { color: #dc2626; background: #fee2e2; }

        /* ── unread dot ── */
        .unread-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--light-green);
            flex-shrink: 0;
            margin-top: 0.35rem;
            box-shadow: 0 0 4px rgba(122,241,70,0.5);
        }

        /* ── empty state ── */
        .empty-notif {
            text-align: center;
            padding: 3rem 0;
            color: var(--medium-gray);
        }
        .empty-notif i { font-size: 2.5rem; display: block; margin-bottom: 0.6rem; color: #d1d5db; }
        .empty-notif p { font-size: 0.84rem; margin: 0; }

        /* ── pagination ── */
        .pagination .page-link {
            font-family: 'Poppins', sans-serif;
            font-size: 0.78rem;
            color: var(--mid-green);
            border-color: var(--border-color);
            border-radius: 7px;
            margin: 0 2px;
        }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--mid-green), var(--primary-green));
            border-color: var(--primary-green);
            color: #fff;
        }
        .pagination .page-link:hover { background: #e8f5e1; color: var(--primary-green); }
        .pagination .page-item.disabled .page-link { color: #d1d5db; }

        /* ── modal ── */
        .modal-content {
            border-radius: 16px;
            border: none;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 8px 32px rgba(0,0,0,0.14);
        }
        .modal-header { border-bottom: 1px solid #f3f4f6; padding: 1.1rem 1.25rem; }
        .modal-title  { font-size: 0.92rem; font-weight: 700; }
        .modal-body   { padding: 1.25rem; }
        .modal-footer { border-top: 1px solid #f3f4f6; padding: 0.9rem 1.25rem; }

        #deleteConfirmModal { z-index: 1065; }
        #deleteConfirmModal + .modal-backdrop,
        .modal-backdrop ~ .modal-backdrop { z-index: 1060; }

        @media (max-width: 992px) {
            #main-content-wrapper { margin-left: 0; padding: 12px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container-fluid">

            <?php include 'topbar.php'; ?>

            <div class="section-container">

                <!-- Header strip -->
                <div class="page-header-strip">
                    <div class="d-flex align-items-center gap-2">
                        <span class="ctx-badge" id="unreadBadge" style="display:none;">
                            <i class="bi bi-bell-fill"></i>
                            <span id="unreadBadgeText">0 unread</span>
                        </span>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn-outline-gray" id="viewReadBtn" style="display:none;">
                            <i class="bi bi-envelope-open"></i>
                            Read <span id="readCountBadge"
                                style="background:#e5e7eb;color:var(--medium-gray);border-radius:20px;padding:0.1rem 0.45rem;font-size:0.68rem;font-weight:700;margin-left:0.15rem;">0</span>
                        </button>
                        <button class="btn-green" id="markAllReadBtn" style="display:none;">
                            <i class="bi bi-check2-all"></i> Mark All as Read
                        </button>
                    </div>
                </div>

                <!-- Unread section -->
                <div class="section-block">
                    <div class="section-label">
                        <i class="bi bi-bell me-1"></i> Unread Notifications
                    </div>
                    <div class="notification-container">
                        <div class="empty-notif">
                            <i class="bi bi-bell-slash"></i>
                            <p>Loading notifications…</p>
                        </div>
                    </div>
                </div>

            </div><!-- /section-container -->
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:360px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-trash3 me-2 text-danger"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="font-size:0.84rem;">
                    Are you sure this notification is resolved and can be deleted?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"
                        style="font-family:'Poppins',sans-serif;font-size:0.82rem;border-radius:8px;">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn"
                        style="font-family:'Poppins',sans-serif;font-size:0.82rem;border-radius:8px;">
                        <i class="bi bi-trash3 me-1"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Read Notifications Modal -->
    <div class="modal fade" id="readNotificationsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-envelope-open me-2" style="color:var(--primary-green);"></i>Read Notifications
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height:65vh;overflow-y:auto;">
                    <div class="read-notification-container">
                        <div class="text-center py-4">
                            <div class="spinner-border" role="status"
                                style="color:var(--mid-green);width:1.5rem;height:1.5rem;border-width:2px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPage = 1;
        let readModalPage = 1;

        function showError(message) {
            $('.notification-container').html(`
                <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:1rem 1.25rem;color:#dc2626;font-size:0.82rem;display:flex;align-items:center;gap:0.5rem;">
                    <i class="bi bi-exclamation-triangle-fill"></i> ${message}
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
                                $('#unreadBadgeText').text(unread + ' unread');
                                $('#unreadBadge').show();
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
                    }
                },
                error: function() {
                    showError('Unable to load notifications. Please try again later.');
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
                    $('.read-notification-container').html(
                        '<div style="color:#dc2626;font-size:0.82rem;padding:1rem;">Failed to load read notifications.</div>'
                    );
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
            new bootstrap.Modal(document.getElementById('readNotificationsModal')).show();
        });

        // Mark single as read
        $(document).on('click', '.mark-read-btn', function(e) {
            e.stopPropagation();
            const notificationId = $(this).closest('.notification-item').data('id');
            $.ajax({
                url: 'gs_DB/mark_bin_notification_read.php',
                method: 'POST',
                data: { notification_id: notificationId },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) loadNotifications(currentPage);
                    } catch (e) {}
                }
            });
        });

        // Mark all as read
        $('#markAllReadBtn').on('click', function() {
            $.ajax({
                url: 'gs_DB/mark_bin_notification_read.php',
                method: 'POST',
                data: { mark_all: true },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) loadNotifications(1);
                    } catch (e) {}
                }
            });
        });

        // Delete flow
        let currentNotificationId = null;
        let deleteFromReadModal = false;
        const deleteModalEl = document.getElementById('deleteConfirmModal');
        const deleteModal = new bootstrap.Modal(deleteModalEl);

        $(document).on('click', '.delete-btn', function(e) {
            e.stopPropagation();
            currentNotificationId = $(this).closest('.notification-item').data('id');
            deleteFromReadModal = $('#readNotificationsModal').hasClass('show');
            deleteModal.show();
        });

        deleteModalEl.addEventListener('shown.bs.modal', function() {
            var backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length > 1) backdrops[backdrops.length - 1].style.zIndex = '1060';
        });

        deleteModalEl.addEventListener('hidden.bs.modal', function() {
            if (deleteFromReadModal && $('#readNotificationsModal').hasClass('show')) {
                document.body.classList.add('modal-open');
                document.body.style.overflow = 'hidden';
            }
        });

        $('#confirmDeleteBtn').on('click', function() {
            if (!currentNotificationId) return;
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: { action: 'delete', notification_id: currentNotificationId },
                success: function(response) {
                    if (response.success) {
                        deleteModal.hide();
                        loadNotifications(currentPage);
                        if ($('#readNotificationsModal').hasClass('show')) loadReadNotifications(readModalPage);
                    } else {
                        showError(response.message || 'Failed to delete notification.');
                    }
                },
                error: function() { showError('Error deleting notification. Please try again.'); }
            });
        });

        $(document).ready(function() {
            loadNotifications(1);
            setInterval(function() { loadNotifications(currentPage); }, 30000);
        });
    </script>
</body>
</html>