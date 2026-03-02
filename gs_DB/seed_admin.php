<?php
require_once __DIR__ . '/mariadb_credentials.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

$check = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE userName = 'root'");
$row = $check->fetch_assoc();
if ($row['cnt'] > 0) {
    die(json_encode(['success' => false, 'message' => 'Admin user already exists']));
}

$hash = password_hash('pcsadmin', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (userName, lastName, email, password, role) VALUES (?, ?, ?, ?, ?)");
$userName = 'root';
$lastName = 'Admin';
$email = 'root@gosort.com';
$role = 'admin';
$stmt->bind_param("sssss", $userName, $lastName, $email, $hash, $role);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Admin user created. DELETE this file now.']);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
$conn->close();
