<?php
require_once 'connection.php';

try {
    // Check if old table exists
    $result = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($result->num_rows > 0) {
        // Rename the table
        $conn->query("RENAME TABLE notifications TO bin_notifications");
        echo "Successfully renamed notifications table to bin_notifications\n";
    }
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
}
?>