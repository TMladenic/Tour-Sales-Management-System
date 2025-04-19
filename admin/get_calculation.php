<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Provjeri je li korisnik admin
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Nedozvoljen pristup']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Calculation ID not specified']);
    exit;
}

$calculationId = (int)$_GET['id'];

try {
    // Dohvati glavne podatke o izračunu
    $stmt = $pdo->prepare("
        SELECT * FROM investment_calculations 
        WHERE id = ?
    ");
    $stmt->execute([$calculationId]);
    $calculation = $stmt->fetch();
    
    if (!$calculation) {
        http_response_code(404);
        echo json_encode(['error' => 'Calculation not found']);
        exit;
    }
    
    // Dohvati podatke o investitorima
    $stmt = $pdo->prepare("
        SELECT ii.*, 
               CASE 
                   WHEN ii.investor_type = 'salesperson' THEN s.name
                   WHEN ii.investor_type = 'promoter' THEN p.name
               END as name,
               ii.discounts_share
        FROM investment_investors ii
        LEFT JOIN salespeople s ON ii.investor_type = 'salesperson' AND ii.investor_id = s.id
        LEFT JOIN promoters p ON ii.investor_type = 'promoter' AND ii.investor_id = p.id
        WHERE ii.calculation_id = ?
    ");
    $stmt->execute([$calculationId]);
    $investors = $stmt->fetchAll();
    
    // Pripremi podatke za vraćanje
    $response = [
        'total_investment' => number_format($calculation['total_investment'], 2),
        'gross_profit' => number_format($calculation['gross_profit'], 2),
        'total_expenses' => number_format($calculation['total_expenses'], 2),
        'total_discounts' => number_format($calculation['total_discounts'], 2),
        'net_profit' => number_format($calculation['net_profit'], 2),
        'investors' => array_map(function($investor) {
            return [
                'name' => $investor['name'],
                'percentage' => number_format($investor['percentage'], 2),
                'investment' => number_format($investor['investment'], 2),
                'profit_share' => number_format($investor['profit_share'], 2),
                'expenses_share' => number_format($investor['expenses_share'], 2),
                'discounts_share' => number_format($investor['discounts_share'], 2),
                'payout' => number_format($investor['payout'], 2),
                'future_investment_share' => number_format($investor['future_investment_share'], 2),
                'final_payout' => number_format($investor['final_payout'], 2),
                'notes' => $investor['notes']
            ];
        }, $investors)
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error fetching data']);
}
?> 