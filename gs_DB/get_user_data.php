<?php
require_once 'connection.php';

// Set headers for API response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Get authorization header
$headers = getallheaders();
$auth_header = $headers['Authorization'] ?? '';

if (empty($auth_header)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No authorization token provided'
    ]);
    exit();
}

// Extract token from Bearer format
$token = str_replace('Bearer ', '', $auth_header);

// Start session to check token
session_start();

// Verify token matches session
if (!isset($_SESSION['token']) || $_SESSION['token'] !== $token) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or expired token'
    ]);
    exit();
}

try {
    // Get user ID from session
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        throw new Exception('User ID not found in session');
    }

    // Query user data
    $stmt = $pdo->prepare("
        SELECT 
            id,
            userName,
            lastName,
            email,
            isAdmin,
            registered_at
        FROM users 
        WHERE id = ?
    ");
    
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    // Return user data (excluding password)
    echo json_encode([
        'success' => true,
        'data' => [
            'userId' => $user['id'],
            'username' => $user['userName'],
            'lastName' => $user['lastName'],
            'email' => $user['email'],
            'isAdmin' => (bool)$user['isAdmin'],
            'registeredAt' => $user['registered_at']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'debug' => $e->getMessage()
    ]);
}
?>