<?php
require_once 'connection.php';

// Create maintenance_mode table if it doesn't exist
$pdo->query("
    CREATE TABLE IF NOT EXISTS maintenance_mode (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        active BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

// Function to get current active maintenance session
function getActiveMaintenanceSession() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT m.*, u.userName 
        FROM maintenance_mode m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.active = TRUE 
        ORDER BY m.start_time DESC 
        LIMIT 1
    ");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to start maintenance mode
function startMaintenanceMode($userId) {
    global $pdo;
    
    // First check if there's already an active session
    $activeSession = getActiveMaintenanceSession();
    if ($activeSession) {
        return [
            'status' => 'error',
            'message' => 'Maintenance mode is already active',
            'user' => $activeSession['userName']
        ];
    }

    // Start new maintenance session
    $stmt = $pdo->prepare("INSERT INTO maintenance_mode (user_id) VALUES (?)");
    $stmt->execute([$userId]);
    
    return ['status' => 'success'];
}

// Function to end maintenance mode
function endMaintenanceMode($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE maintenance_mode 
        SET active = FALSE 
        WHERE user_id = ? AND active = TRUE
    ");
    $stmt->execute([$userId]);
    
    return ['status' => 'success'];
}

// Handle POST requests for ending maintenance mode
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    if (isset($_POST['action']) && $_POST['action'] === 'end_maintenance' && isset($_SESSION['user_id'])) {
        endMaintenanceMode($_SESSION['user_id']);
        echo json_encode(['status' => 'success']);
        exit();
    }
}
?>
