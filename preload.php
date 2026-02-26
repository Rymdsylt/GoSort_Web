<?php
/**
 * OPcache Preload Script
 * Compiles all critical PHP files at server startup so the first request
 * doesn't pay file I/O + parse cost. Only runs once when PHP starts.
 */

$basePath = __DIR__;

// Preload database layer
$dbFiles = [
    '/gs_DB/mariadb_credentials.php',
    '/gs_DB/connection.php',
    '/gs_DB/activity_logs.php',
];

// Preload API files
$apiFiles = glob($basePath . '/api/*.php');

// Preload router
$routerFiles = [
    '/router.php',
    '/test_no_db.php',
];

// Compile DB files
foreach ($dbFiles as $file) {
    $fullPath = $basePath . $file;
    if (file_exists($fullPath)) {
        opcache_compile_file($fullPath);
    }
}

// Compile API files
foreach ($apiFiles as $file) {
    if (file_exists($file)) {
        opcache_compile_file($file);
    }
}

// Compile router files
foreach ($routerFiles as $file) {
    $fullPath = $basePath . $file;
    if (file_exists($fullPath)) {
        opcache_compile_file($fullPath);
    }
}
