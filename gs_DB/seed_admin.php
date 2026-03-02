<?php
require_once __DIR__ . '/connection.php';

$check = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE email = 'root@gosort.com'");
$row = $check->fetch_assoc();

if ($row['cnt'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Admin user already exists']);
    exit();
}

$hash = password_hash('pcsadmin', PASSWORD_DEFAULT);
$result = $conn->query("INSERT INTO users (userName, lastName, email, password, role) VALUES ('root', 'Admin', 'root@gosort.com', '$hash', 'admin')");

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Admin user created']);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
