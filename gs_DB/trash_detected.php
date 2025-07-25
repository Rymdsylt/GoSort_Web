<?php
require_once('connection.php');


$trash_type = isset($_GET['type']) ? $_GET['type'] : '';

if (!empty($trash_type)) {

    $type_map = [
        'nbio' => 'non-biodegradable',
        'bio' => 'biodegradable',
        'recyc' => 'recyclable'
    ];


    $readable_type = isset($type_map[$trash_type]) ? $type_map[$trash_type] : $trash_type;


    if (!in_array($readable_type, ['biodegradable', 'non-biodegradable', 'recyclable'])) {
        http_response_code(400);
        echo "Error: Invalid trash type";
        exit;
    }

    try {
    
        $stmt = $pdo->prepare("INSERT INTO trash_sorted (sorted) VALUES (?)");
        $stmt->execute([$readable_type]);
        echo "Success: Trash detection recorded";
    } catch (PDOException $e) {
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
} else {
    http_response_code(400);
    echo "Error: No trash type provided";
}
?>
