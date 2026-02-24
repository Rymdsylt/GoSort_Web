<?php
// Simple router for PHP built-in server
$requested_file = __DIR__ . $_SERVER['REQUEST_URI'];

// Remove query string
$requested_file = parse_url($requested_file, PHP_URL_PATH);
$requested_file = __DIR__ . $requested_file;

// If the file exists (file or directory), serve it
if (file_exists($requested_file)) {
    // If it's a directory, look for index.php
    if (is_dir($requested_file)) {
        $index = $requested_file . '/index.php';
        if (file_exists($index)) {
            require $index;
            return true;
        }
    }
    return false;
}

// File doesn't exist, return 404
http_response_code(404);
echo "404 Not Found";
return true;
