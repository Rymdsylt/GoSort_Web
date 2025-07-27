<?php
require_once 'connection.php';

try {
    $stmt = $pdo->prepare("UPDATE sorters SET maintenance_mode = 0 WHERE maintenance_mode = 1");
    $result = $stmt->execute();
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update maintenance mode']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
