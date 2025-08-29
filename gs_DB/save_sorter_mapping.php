<?php
require_once 'connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $device_identity = $_GET['device_identity'] ?? null;
    if (!$device_identity) {
        echo json_encode(['success' => false, 'message' => 'Missing device_identity']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT zdeg, ndeg, odeg, tdeg FROM sorter_mapping WHERE device_identity = ?");
    $stmt->execute([$device_identity]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode(['success' => true, 'mapping' => $row]);
    } else {
        echo json_encode(['success' => true, 'mapping' => [
            'zdeg' => 'bio',
            'ndeg' => 'nbio',
            'odeg' => 'hazardous',
            'tdeg' => 'mixed'
        ]]);
    }
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$device_identity = $data['device_identity'] ?? null;
$zdeg = $data['zdeg'] ?? null;
$ndeg = $data['ndeg'] ?? null;
$odeg = $data['odeg'] ?? null;
$tdeg = $data['tdeg'] ?? null;

if (!$device_identity || !$zdeg || !$ndeg || !$odeg || !$tdeg) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS sorter_mapping (
        device_identity VARCHAR(100) PRIMARY KEY,
        zdeg VARCHAR(10) NOT NULL,
        ndeg VARCHAR(10) NOT NULL,
        odeg VARCHAR(10) NOT NULL,
        tdeg VARCHAR(10) NOT NULL,
        FOREIGN KEY (device_identity) REFERENCES sorters(device_identity) ON DELETE CASCADE
    )");

    // Upsert mapping
    $stmt = $pdo->prepare("INSERT INTO sorter_mapping (device_identity, zdeg, ndeg, odeg, tdeg) VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE zdeg=VALUES(zdeg), ndeg=VALUES(ndeg), odeg=VALUES(odeg), tdeg=VALUES(tdeg)");
    $stmt->execute([$device_identity, $zdeg, $ndeg, $odeg, $tdeg]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 