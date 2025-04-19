<?php
session_start();
require_once 'config/database.php';

// Provjeri je li POST zahtjev
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: sales.php');
    exit;
}

// Provjeri postojanje ID-a
if (!isset($_POST['id'])) {
    $_SESSION['error'] = 'Nedostaje ID prodaje';
    header('Location: sales.php');
    exit;
}

$id = $_POST['id'];

try {
    // Prvo dohvati podatke o prodaji da možemo obrisati i trošak
    $stmt = $pdo->prepare("
        SELECT ps.*, p.coefficient 
        FROM promoter_sales ps 
        JOIN promoters p ON ps.promoter_id = p.id 
        WHERE ps.id = ?
    ");
    $stmt->execute([$id]);
    $sale = $stmt->fetch();

    if (!$sale) {
        $_SESSION['error'] = 'Sale not found';
        header('Location: sales.php');
        exit;
    }

    // Započni transakciju
    $pdo->beginTransaction();

    // Obriši povezani trošak
    $stmt = $pdo->prepare("
        DELETE FROM expenses 
        WHERE tour_id = ? 
        AND category_id = (SELECT id FROM expense_categories WHERE name = 'Promoter')
        AND description = ?
    ");
    $stmt->execute([
        $sale['tour_id'],
        'Zarada promotera: ' . $sale['promoter_id']
    ]);

    // Obriši prodaju promotera
    $stmt = $pdo->prepare("DELETE FROM promoter_sales WHERE id = ?");
    $stmt->execute([$id]);

    // Potvrdi transakciju
    $pdo->commit();

    $_SESSION['success'] = 'Promoter sale has been successfully deleted';
} catch (Exception $e) {
    // Poništi transakciju u slučaju greške
    $pdo->rollBack();
    error_log("Error in delete_promoter_sale.php: " . $e->getMessage());
    $_SESSION['error'] = 'An error occurred while deleting the sale';
}

header('Location: sales.php');
exit;
?> 