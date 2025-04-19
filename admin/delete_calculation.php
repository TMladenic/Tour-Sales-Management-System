<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Calculation ID not specified']);
    exit;
}

$calculationId = (int)$_GET['id'];

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // First delete investors (due to foreign key constraints)
    $stmt = $pdo->prepare("DELETE FROM investment_investors WHERE calculation_id = ?");
    $stmt->execute([$calculationId]);
    
    // Then delete the main calculation
    $stmt = $pdo->prepare("DELETE FROM investment_calculations WHERE id = ?");
    $stmt->execute([$calculationId]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    // Rollback transaction in case of error
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error deleting calculation']);
}
?> 