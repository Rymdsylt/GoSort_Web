<?php
require_once __DIR__ . '/mariadb_credentials.php';

// Connect to database
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

// Check if superadmin already exists
$check = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE userName = 'superadmin'");
$row = $check->fetch_assoc();
if ($row['cnt'] > 0) {
    die(json_encode(['success' => false, 'message' => 'Superadmin user already exists']));
}

// Hash the password
$hash = password_hash('pcsadmin', PASSWORD_DEFAULT);

// Insert superadmin
$stmt = $conn->prepare("INSERT INTO users (userName, lastName, email, password, role) VALUES (?, ?, ?, ?, ?)");
$userName = 'superadmin';       // customized username
$lastName = 'System';
$email    = 'superadmin@gosort.com'; // email for superadmin
$role     = 'superadmin';       // role is now 'superadmin'
$stmt->bind_param("sssss", $userName, $lastName, $email, $hash, $role);

// Execute and show result
if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Superadmin created. DELETE this file immediately after use.'
    ]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}

$conn->close();
