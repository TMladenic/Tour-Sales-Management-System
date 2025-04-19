<?php
// First prevent any output
ob_start();

// Postavke error reportinga - ali bez slanja u browser
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'vendor/tcpdf/tcpdf.php';

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    die('Unauthorized access');
}

if (!isset($_GET['tour_id'])) {
    ob_end_clean();
    die('Tour ID not specified');
}

$tour_id = (int)$_GET['tour_id'];

try {
    // Dohvati informacije o turneji
    $stmt = $pdo->prepare("SELECT t.*, s.name as supplier_name 
                           FROM tours t 
                           LEFT JOIN suppliers s ON t.supplier_id = s.id 
                           WHERE t.id = ?");
    $stmt->execute([$tour_id]);
    $tour = $stmt->fetch();
    
    if (!$tour) {
        ob_end_clean();
        die('Tour not found');
    }

    // Dohvati statistiku po prodavačima
    $stmt = $pdo->prepare("
        SELECT 
            sp.name as salesperson_name,
            COUNT(*) as total_sales,
            SUM(s.quantity) as total_quantity,
            SUM(s.price * s.quantity * (1 - s.discount/100)) as total_revenue
        FROM sales s
        JOIN salespeople sp ON s.salesperson_id = sp.id
        WHERE s.tour_id = ?
        GROUP BY sp.id, sp.name
        ORDER BY total_revenue DESC
    ");
    $stmt->execute([$tour_id]);
    $salesperson_stats = $stmt->fetchAll();

    // Dohvati statistiku po proizvodima
    $stmt = $pdo->prepare("
        SELECT 
            p.name as product_name,
            COUNT(*) as total_sales,
            SUM(s.quantity) as total_quantity,
            SUM(s.price * s.quantity * (1 - s.discount/100)) as total_revenue
        FROM sales s
        JOIN products p ON s.product_id = p.id
        WHERE s.tour_id = ?
        GROUP BY p.id, p.name
        ORDER BY total_revenue DESC
    ");
    $stmt->execute([$tour_id]);
    $product_stats = $stmt->fetchAll();

    // Dohvati ukupnu statistiku
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(quantity), 0) as total_quantity,
        COALESCE(SUM(price * quantity), 0) as total_revenue,
        COALESCE(SUM(discount), 0) as total_discounts
    FROM sales WHERE tour_id = ?");
    $stmt->execute([$tour_id]);
    $total_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Dohvati ukupne troškove
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_expenses FROM expenses WHERE tour_id = ?");
    $stmt->execute([$tour_id]);
    $total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'];

    // Očisti output buffer prije kreiranja PDF-a
    ob_end_clean();
    
    // Kreiraj novi PDF dokument za printer
    $pdf = new TCPDF('P', 'mm', array(55, 200), true, 'UTF-8', false);
    
    // Postavi informacije o dokumentu
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Sales Tracking System');
    $pdf->SetTitle('Sales Statistics');
    
    // Postavi margine
    $pdf->SetMargins(2, 2, 2);
    $pdf->SetAutoPageBreak(TRUE, 2);
    
    // Isključi automatsko dodavanje donje crte
    $pdf->setPrintFooter(false);
    
    // Dodaj stranicu
    $pdf->AddPage();
    
    // Dodaj datum i vrijeme izdavanja
    $pdf->SetFont('freeserif', '', 5);
    $pdf->Cell(0, 2, 'Issued: ' . date('d.m.Y. H:i'), 0, 1, 'R');
    $pdf->Ln(1);
    
    // Dodaj naslov
    $pdf->SetFont('freeserif', 'B', 7);
    $pdf->Cell(0, 3, 'SALES STATISTICS', 0, 1, 'C');
    $pdf->Ln(1);
    
    // Dodaj informacije o turi
    $pdf->SetFont('freeserif', 'B', 6);
    $pdf->Cell(0, 3, 'Tour: ' . $tour['name'], 0, 1);
    $pdf->SetFont('freeserif', '', 6);
    $pdf->Cell(0, 3, 'Supplier: ' . $tour['supplier_name'], 0, 1);
    $pdf->Cell(0, 3, 'Period: ' . date('d.m.Y.', strtotime($tour['start_date'])) . ' - ' . date('d.m.Y.', strtotime($tour['end_date'])), 0, 1);
    $pdf->Ln(2);
    
    // Dodaj ukupne podatke
    $pdf->SetFont('freeserif', 'B', 7);
    $pdf->Cell(0, 4, 'TOTAL:', 0, 1);
    
    // Tablica za ukupne podatke
    $pdf->SetFont('freeserif', '', 6);
    $header = array('', 'Total');
    $data = array(
        array('Sales', number_format($total_stats['total_sales'], 0) . ' pcs'),
        array('Quantity', number_format($total_stats['total_quantity'], 0) . ' pcs'),
        array('Revenue', number_format($total_stats['total_revenue'], 2) . ' €'),
        array('Discounts', number_format($total_stats['total_discounts'], 2) . ' €'),
        array('Expenses', number_format($total_expenses, 2) . ' €')
    );
    
    // Postavi širine stupaca
    $w = array(25, 25);
    
    // Iscrtaj tablicu
    $pdf->SetFont('freeserif', 'B', 6);
    foreach($header as $i => $col) {
        $pdf->Cell($w[$i], 3, $col, 1, 0, 'C');
    }
    $pdf->Ln();
    
    $pdf->SetFont('freeserif', '', 6);
    foreach($data as $row) {
        foreach($row as $i => $col) {
            $pdf->Cell($w[$i], 3, $col, 1, 0, 'C');
        }
        $pdf->Ln();
    }
    $pdf->Ln(3);
    
    // Dodaj statistiku po prodavačima
    $pdf->SetFont('freeserif', 'B', 7);
    $pdf->Cell(0, 4, 'BY SALESPERSON:', 0, 1);
    
    // Tablica za prodavače
    $header = array('Salesperson', 'Revenue');
    $w = array(35, 15);
    
    $pdf->SetFont('freeserif', 'B', 6);
    foreach($header as $i => $col) {
        $pdf->Cell($w[$i], 3, $col, 1, 0, 'C');
    }
    $pdf->Ln();
    
    $pdf->SetFont('freeserif', '', 6);
    foreach($salesperson_stats as $stat) {
        $pdf->Cell($w[0], 3, $stat['salesperson_name'], 1);
        $pdf->Cell($w[1], 3, number_format($stat['total_revenue'], 2) . ' €', 1, 0, 'R');
        $pdf->Ln();
    }
    $pdf->Ln(3);
    
    // Dodaj statistiku po proizvodima
    $pdf->SetFont('freeserif', 'B', 7);
    $pdf->Cell(0, 4, 'BY PRODUCTS:', 0, 1);
    
    // Tablica za proizvode
    $header = array('Product', 'Revenue');
    $w = array(35, 15);
    
    $pdf->SetFont('freeserif', 'B', 6);
    foreach($header as $i => $col) {
        $pdf->Cell($w[$i], 3, $col, 1, 0, 'C');
    }
    $pdf->Ln();
    
    $pdf->SetFont('freeserif', '', 6);
    foreach($product_stats as $stat) {
        $pdf->Cell($w[0], 3, $stat['product_name'], 1);
        $pdf->Cell($w[1], 3, number_format($stat['total_revenue'], 2) . ' €', 1, 0, 'R');
        $pdf->Ln();
    }
    
    // Dodaj donju crtu nakon što je sav sadržaj ispisan
    $pdf->Ln(2);
    $pdf->Line(2, $pdf->GetY(), 53, $pdf->GetY());
    
    // Ispiši PDF
    $pdf->Output('sales_statistics_printer.pdf', 'D');
    
} catch (PDOException $e) {
    ob_end_clean();
    die('Error retrieving data: ' . $e->getMessage());
}
?> 