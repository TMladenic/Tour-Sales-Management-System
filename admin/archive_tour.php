<?php
require_once '../config/database.php';
require_once '../includes/header.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get data from JSON
$data = json_decode(file_get_contents('php://input'), true);
$tour_id = $data['tour_id'] ?? null;

if (!$tour_id) {
    echo json_encode(['success' => false, 'message' => 'Tour ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Archive sales
    $stmt = $pdo->prepare("INSERT INTO archived_sales 
        (tour_id, product_id, salesperson_id, quantity, price, customer_name, customer_address, 
        instagram_handle, delivery_method, package_email, package_number, package_location, 
        discount, discount_type, discount_reason, notes, created_at, archived_at)
        SELECT tour_id, product_id, salesperson_id, quantity, price, customer_name, customer_address,
        instagram_handle, delivery_method, package_email, package_number, package_location,
        discount, discount_type, discount_reason, notes, created_at, NOW()
        FROM sales WHERE tour_id = ?");
    $stmt->execute([$tour_id]);

    // Archive expenses
    $stmt = $pdo->prepare("INSERT INTO archived_expenses 
        (tour_id, category_id, description, amount, created_at, archived_at)
        SELECT tour_id, category_id, description, amount, created_at, NOW()
        FROM expenses WHERE tour_id = ?");
    $stmt->execute([$tour_id]);

    // Archive waiting list
    $stmt = $pdo->prepare("INSERT INTO archived_waiting_list 
        (tour_id, customer_name, phone, email, quantity, notes, created_at, archived_at)
        SELECT tour_id, customer_name, phone, email, quantity, notes, created_at, NOW()
        FROM waiting_list WHERE tour_id = ?");
    $stmt->execute([$tour_id]);

    // Mark tour as archived
    $stmt = $pdo->prepare("UPDATE tours SET archived = TRUE WHERE id = ?");
    $stmt->execute([$tour_id]);

    // Delete original data
    $stmt = $pdo->prepare("DELETE FROM sales WHERE tour_id = ?");
    $stmt->execute([$tour_id]);

    $stmt = $pdo->prepare("DELETE FROM expenses WHERE tour_id = ?");
    $stmt->execute([$tour_id]);

    $stmt = $pdo->prepare("DELETE FROM waiting_list WHERE tour_id = ?");
    $stmt->execute([$tour_id]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 