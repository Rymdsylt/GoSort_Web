<?php
require_once 'connection.php';

// Function to add a new sorter
function addSorter($device_name, $location, $device_identity) {
    global $pdo;
    try {
        $registration_token = bin2hex(random_bytes(32));
        
        $stmt = $pdo->prepare("
            INSERT INTO sorters (device_name, location, device_identity, registration_token)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([$device_name, $location, $device_identity, $registration_token]);
        return [
            'success' => true,
            'message' => 'Device registered successfully',
            'token' => $registration_token
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Error registering device: ' . $e->getMessage()
        ];
    }
}

// Function to update sorter status
function updateSorterStatus($device_identity, $status) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE sorters 
            SET status = ?, last_active = CURRENT_TIMESTAMP 
            WHERE device_identity = ?
        ");
        return $stmt->execute([$status, $device_identity]);
    } catch (PDOException $e) {
        return false;
    }
}

// Function to get sorter by identity
function getSorterByIdentity($device_identity) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM sorters WHERE device_identity = ?");
        $stmt->execute([$device_identity]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

// Function to delete a sorter
function deleteSorter($id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM sorters WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        return false;
    }
}

// Function to verify sorter token
function verifySorterToken($device_identity, $token) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM sorters 
            WHERE device_identity = ? AND registration_token = ?
        ");
        $stmt->execute([$device_identity, $token]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to get all sorters
function getAllSorters() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM sorters ORDER BY last_active DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
