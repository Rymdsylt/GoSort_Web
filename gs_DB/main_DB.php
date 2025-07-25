<?php
$host = "localhost";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$conn->query("CREATE DATABASE IF NOT EXISTS GoSort");
$conn->select_db("GoSort");


$conn->query("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        isAdmin BOOLEAN DEFAULT FALSE,
        userName VARCHAR(50) NOT NULL,
        lastName VARCHAR(50) NOT NULL,
        email VARCHAR(100) UNIQUE,
        password VARCHAR(255),
        registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS trash_sorted (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sorted ENUM('biodegradable', 'non-biodegradable', 'recyclable') NOT NULL,
        confidence FLOAT DEFAULT NULL,
        bin_location VARCHAR(100) DEFAULT NULL,
        user_id INT DEFAULT NULL,
        time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )
");

echo "Database and tables created successfully.";

$conn->close();
?>
