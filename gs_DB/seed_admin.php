<?php
require_once __DIR__ . '/connection.php';

$check = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE email='root@gosort.com'");
$row = $check->fetch_assoc();

if ($row['cnt'] > 0) {
    die(json_encode(['status' => 'skipped', 'message' => 'Admin already exists']));
}

$hash = password_hash('pcsadmin', PASSWORD_DEFAULT);
$conn->query("INSERT INTO users (userName, lastName, email, password, role) VALUES ('root', 'Admin', 'root@gosort.com', '$hash', 'admin')");

if ($conn->affected_rows > 0) {
    echo json_encode(['status' => 'ok', 'message' => 'Admin seeded successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
}
