<?php
session_start();
require_once 'connection.php';
require_once 'bin_notifications_DB.php';
require_once 'activity_logs.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    exit;
}

$notification_id = intval($_POST['notification_id']);

try {
    // Get notification details before deletion for logging
    $stmt = $pdo->prepare("SELECT type, message FROM bin_notifications WHERE id = ?");
    $stmt->execute([$notification_id]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("DELETE FROM bin_notifications WHERE id = ?");
    $success = $stmt->execute([$notification_id]);

    if ($success && $notification) {
        // Log notification deletion
        $user_id = $_SESSION['user_id'] ?? null;
        log_notification_deleted($user_id, $notification['type'], $notification['message']);
    }

    echo json_encode(['success' => $success]);
} catch (PDOException $e) {
    error_log("Error deleting notification: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>