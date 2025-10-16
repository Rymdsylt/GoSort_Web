<?php
session_start();
require_once 'main_DB.php';
require_once 'connection.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Verify admin status
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin privileges required']);
    exit();
}

// Get POST data
$userName = $_POST['userName'] ?? '';
$lastName = $_POST['lastName'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';
$assigned_floor = $_POST['assigned_floor'] ?? '';
$assigned_sorters = $_POST['assigned_sorters'] ?? [];

// Validate required fields
if (!$userName || !$lastName || !$email || !$password || !$role) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (userName, lastName, email, password, role, assigned_floor) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $userName, $lastName, $email, $hashedPassword, $role, $assigned_floor);
    $stmt->execute();
    
    $user_id = $conn->insert_id;

    // Insert assigned sorters if any
    if (!empty($assigned_sorters)) {
        $stmt = $conn->prepare("INSERT INTO assigned_sorters (user_id, device_identity) VALUES (?, ?)");
        foreach ($assigned_sorters as $sorter) {
            $stmt->bind_param("is", $user_id, $sorter);
            $stmt->execute();
        }
    }

    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'User added successfully',
        'user_id' => $user_id
    ]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error adding user: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
