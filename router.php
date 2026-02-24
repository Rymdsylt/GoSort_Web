<?php
// Simple router for PHP built-in server
ob_start();
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requested_file = __DIR__ . $request_uri;

// If file exists, serve it (return false lets PHP serve it)
if (file_exists($requested_file)) {
    // If it's a directory, try index.php
    if (is_dir($requested_file)) {
        $index = $requested_file . '/index.php';
        if (file_exists($index)) {
            require $index;
            return true;
        }
    }
    // File exists, let PHP serve it
    return false;
}

// File doesn't exist
http_response_code(404);
echo "404 Not Found";
return true;
