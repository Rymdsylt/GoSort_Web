<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Unauthorized access";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed";
    exit();
}

$action = $_POST['action'] ?? '';
$validActions = ['bio', 'nbio', 'recyc', 'maintenance_start', 'maintenance_end', 'maintenance_keep', 'sweep1', 'sweep2', 'unclog'];

if (!in_array($action, $validActions)) {
    http_response_code(400);
    echo "Invalid action: " . htmlspecialchars($action);
    exit();
}

// Store the maintenance command in a file that GoSort.py will read
$commandFile = '../maintenance_command.txt';
file_put_contents($commandFile, $action);

echo "Success: Command sent to move servo to " . ucfirst($action);
?>
