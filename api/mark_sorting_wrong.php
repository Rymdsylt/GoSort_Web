<?php
session_start();
header('Content-Type: application/json');

require_once '../gs_DB/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

$sortingId = $input['sorting_id'] ?? null;
$deviceIdentity = $input['device_identity'] ?? null;

if (!$sortingId || !$deviceIdentity) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    // Check if a review already exists for this sorting entry
    $checkStmt = $pdo->prepare("SELECT id FROM sorting_reviews WHERE sorting_history_id = ?");
    $checkStmt->execute([$sortingId]);
    $existingReview = $checkStmt->fetch();

    if ($existingReview) {
        // Update existing review
        $updateStmt = $pdo->prepare("
            UPDATE sorting_reviews 
            SET is_correct = 0, 
                reviewed_by = ?, 
                reviewed_at = NOW() 
            WHERE sorting_history_id = ?
        ");
        $updateStmt->execute([$_SESSION['user_id'], $sortingId]);
    } else {
        // Insert new review
        $insertStmt = $pdo->prepare("
            INSERT INTO sorting_reviews (sorting_history_id, device_identity, is_correct, reviewed_by, reviewed_at) 
            VALUES (?, ?, 0, ?, NOW())
        ");
        $insertStmt->execute([$sortingId, $deviceIdentity, $_SESSION['user_id']]);
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Detection marked as wrong',
        'sorting_id' => $sortingId,
        'is_correct' => false
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
