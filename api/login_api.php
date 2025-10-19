<?php
require_once '../gs_DB/connection.php';

// Set headers for API response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Get raw input and log it for debugging
$raw_input = file_get_contents('php://input');
error_log("Raw input received: " . $raw_input);

// Try to decode JSON
$data = json_decode($raw_input, true);

// Log the parsed data
error_log("Parsed data: " . print_r($data, true));

// Check if JSON parsing failed
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON parsing error: " . json_last_error_msg());
    // Try POST data as fallback
    $data = $_POST;
}

// Validate required fields
if (empty($data['username']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username and password are required',
        'debug' => ['received_data' => $data]
    ]);
    exit();
}

$username = trim($data['username']);
$password = $data['password'];

try {
    // Attempt to find user
    $stmt = $pdo->prepare("SELECT id, userName as username, lastName, role, password, assigned_floor FROM users WHERE userName = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Generate a session token
        $token = bin2hex(random_bytes(32));
        
        // Store token in session
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['token'] = $token;

        // Set cookie for web client
        setcookie('user_logged_in', 'true', time() + (86400 * 30), "/", "", true, true);

        // Get assigned sorter
        $stmt = $pdo->prepare("
            SELECT 
                s.device_name,
                s.device_identity,
                s.location,
                s.status,
                s.maintenance_mode,
                a.assigned_floor
            FROM assigned_sorters a
            JOIN sorters s ON s.device_identity = a.device_identity
            WHERE a.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user['id']]);
        $sorter = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return success with user data, token and sorter
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'userId' => $user['id'],
                'username' => $user['username'],
                'lastName' => $user['lastName'],
                'isAdmin' => $user['role'] === 'admin',
                'role' => $user['role'],
                'assignedFloor' => $user['assigned_floor'],
                'token' => $token,
                'sorter' => $sorter ? [
                    'device_name' => $sorter['device_name'],
                    'device_identity' => $sorter['device_identity'],
                    'location' => $sorter['location'],
                    'status' => $sorter['status'],
                    'maintenance_mode' => (bool)$sorter['maintenance_mode'],
                    'assigned_floor' => $sorter['assigned_floor']
                ] : null
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username or password'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'debug' => $e->getMessage()
    ]);
}
?>