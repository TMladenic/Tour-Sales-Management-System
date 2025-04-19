<?php
require_once '../config/database.php';
require_once '../includes/header.php';

// Provjeri je li korisnik admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Provjeri je li POST zahtjev
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Dohvati podatke iz JSON-a
$data = json_decode(file_get_contents('php://input'), true);
$tour_id = $data['tour_id'] ?? null;

if (!$tour_id) {
    echo json_encode(['success' => false, 'message' => 'Tour ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Vrati prodaju iz arhive
    $stmt = $pdo->prepare("INSERT INTO sales 
        (tour_id, product_id, salesperson_id, quantity, price, customer_name, customer_address, 
        instagram_handle, delivery_method, package_email, package_number, package_location, 
        discount, discount_type, discount_reason, notes, created_at)
        SELECT tour_id, product_id, salesperson_id, quantity, price, customer_name, customer_address,
        instagram_handle, delivery_method, package_email, package_number, package_location,
        discount, discount_type, discount_reason, notes, created_at
        FROM archived_sales WHERE tour_id = ?");
    $stmt->execute([$tour_id]);

    // Vrati troškove iz arhive
    $stmt = $pdo->prepare("INSERT INTO expenses 
        (tour_id, category_id, description, amount, created_at)
        SELECT tour_id, category_id, description, amount, created_at
        FROM archived_expenses WHERE tour_id = ?");
    $stmt->execute([$tour_id]);

    // Vrati listu čekanja iz arhive
    $stmt = $pdo->prepare("INSERT INTO waiting_list 
        (tour_id, customer_name, phone, email, quantity, notes, created_at)
        SELECT tour_id, customer_name, phone, email, quantity, notes, created_at
        FROM archived_waiting_list WHERE tour_id = ?");
    $stmt->execute([$tour_id]);

    // Označi turu kao ne-arhiviranu
    $stmt = $pdo->prepare("UPDATE tours SET archived = FALSE WHERE id = ?");
    $stmt->execute([$tour_id]);

    // Obriši arhivirane podatke
    $stmt = $pdo->prepare("DELETE FROM archived_sales WHERE tour_id = ?");
    $stmt->execute([$tour_id]);

    $stmt = $pdo->prepare("DELETE FROM archived_expenses WHERE tour_id = ?");
    $stmt->execute([$tour_id]);

    $stmt = $pdo->prepare("DELETE FROM archived_waiting_list WHERE tour_id = ?");
    $stmt->execute([$tour_id]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 