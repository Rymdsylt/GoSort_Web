<?php
session_start();
require_once 'connection.php';
require_once 'activity_logs.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $device_identity = $_GET['device_identity'] ?? null;
    if (!$device_identity) {
        echo json_encode(['success' => false, 'message' => 'Missing device_identity']);
        exit;
    }
    // Read mapping using the canonical 'mdeg' column. If not present, return defaults.
    $stmt = $pdo->prepare("SELECT zdeg, ndeg, odeg, mdeg FROM sorter_mapping WHERE device_identity = ?");
    try {
        $stmt->execute([$device_identity]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo json_encode(['success' => true, 'mapping' => $row]);
            exit;
        }
    } catch (PDOException $e) {
        // Table or column may not exist yet; fall through to defaults
    }

    // If nothing found or table/column doesn't exist, return defaults
    echo json_encode(['success' => true, 'mapping' => [
        'zdeg' => 'bio',
        'ndeg' => 'nbio',
        'odeg' => 'hazardous',
        'mdeg' => 'mixed'
    ]]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$device_identity = $data['device_identity'] ?? null;
$zdeg = $data['zdeg'] ?? null;
$ndeg = $data['ndeg'] ?? null;
$odeg = $data['odeg'] ?? null;
$mdeg = $data['mdeg'] ?? null;

if (!$device_identity || !$zdeg || !$ndeg || !$odeg || !$mdeg) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Ensure table exists with 'mdeg' column
    $pdo->exec("CREATE TABLE IF NOT EXISTS sorter_mapping (
            device_identity VARCHAR(100) PRIMARY KEY,
            zdeg VARCHAR(10) NOT NULL,
            ndeg VARCHAR(10) NOT NULL,
            odeg VARCHAR(10) NOT NULL,
            mdeg VARCHAR(10) NOT NULL,
            FOREIGN KEY (device_identity) REFERENCES sorters(device_identity) ON DELETE CASCADE
        )");

    // Upsert mapping using mdeg column
    $stmt = $pdo->prepare("INSERT INTO sorter_mapping (device_identity, zdeg, ndeg, odeg, mdeg) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE zdeg=VALUES(zdeg), ndeg=VALUES(ndeg), odeg=VALUES(odeg), mdeg=VALUES(mdeg)");
    $stmt->execute([$device_identity, $zdeg, $ndeg, $odeg, $mdeg]);
    
    // Log mapping update
    $user_id = $_SESSION['user_id'] ?? null;
    log_mapping_updated($user_id, $device_identity);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 