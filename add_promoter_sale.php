<?php
session_start();

// Provjeri je li POST zahtjev
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: sales.php');
    exit;
}

require_once 'config/database.php';

// Provjeri postojanje potrebnih podataka
if (!isset($_POST['tour_id']) || !isset($_POST['promoter_id']) || !isset($_POST['promoter_quantity'])) {
    $_SESSION['error'] = 'Sva polja moraju biti popunjena';
    header('Location: sales.php');
    exit;
}

$tour_id = $_POST['tour_id'];
$promoter_id = $_POST['promoter_id'];
$quantity = $_POST['promoter_quantity'];

// Provjeri jesu li vrijednosti ispravne
if (!is_numeric($quantity) || $quantity <= 0) {
    $_SESSION['error'] = 'Quantity must be a positive number';
    header('Location: sales.php');
    exit;
}

try {
    // Dohvati koeficijent promotera
    $stmt = $pdo->prepare("SELECT coefficient FROM promoters WHERE id = ?");
    $stmt->execute([$promoter_id]);
    $promoter = $stmt->fetch();

    if (!$promoter) {
        $_SESSION['error'] = 'Promoter not found';
        header('Location: sales.php');
        exit;
    }

    // Unesi prodaju promotera
    $stmt = $pdo->prepare("INSERT INTO promoter_sales (tour_id, promoter_id, quantity) VALUES (?, ?, ?)");
    $stmt->execute([$tour_id, $promoter_id, $quantity]);

    // Izračunaj zaradu promotera
    $earnings = $quantity * $promoter['coefficient'];

    // Dodaj zaradu u troškove
    $stmt = $pdo->prepare("
        INSERT INTO expenses (tour_id, category_id, description, amount) 
        VALUES (?, (SELECT id FROM expense_categories WHERE name = 'Promoter'), ?, ?)
    ");
    $stmt->execute([
        $tour_id,
        'Zarada promotera: ' . $promoter_id,
        $earnings
    ]);

    $_SESSION['success'] = 'Promoter sale has been successfully recorded';
} catch (Exception $e) {
    error_log("Error in add_promoter_sale.php: " . $e->getMessage());
    $_SESSION['error'] = 'An error occurred while saving the sale';
}

header('Location: sales.php');
exit;
?> 