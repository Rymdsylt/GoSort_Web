<?php
// Centralized database credentials
// Supports both Railway (MARIADB_* env vars) and local XAMPP development (defaults)

$db_host = getenv('MARIADB_HOST') ?: '127.0.0.1';
$db_user = getenv('MARIADB_USER') ?: 'root';
$db_pass = getenv('MARIADB_PASSWORD') ?: '';
$db_name = getenv('MARIADB_DATABASE') ?: 'gosort_db';
$db_port = (int)(getenv('MARIADB_PORT') ?: 3306);
?>
