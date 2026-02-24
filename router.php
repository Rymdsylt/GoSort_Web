<?php
// Router script for PHP built-in server
$requested_file = __DIR__ . $_SERVER['REQUEST_URI'];

// If it's a real file or directory, serve it
if (file_exists($requested_file)) {
    return false;
}

// Otherwise, let the requesting file handle it
// (for any routing logic you may have)
return false;
