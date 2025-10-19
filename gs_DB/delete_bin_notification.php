<?php
require_once 'connection.php';
require_once 'bin_notifications_DB.php';

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
    $stmt = $pdo->prepare("DELETE FROM bin_notifications WHERE id = ?");
    $success = $stmt->execute([$notification_id]);

    echo json_encode(['success' => $success]);
} catch (PDOException $e) {
    error_log("Error deleting notification: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>