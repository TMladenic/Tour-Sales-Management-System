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
    die('Nedozvoljen pristup');
}

if (!isset($_GET['tour_id'])) {
    ob_end_clean();
    die('ID turneje nije naveden');
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

    // Dohvati dodatne statistike
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as waiting_list_count 
        FROM waiting_list 
        WHERE tour_id = ?
    ");
    $stmt->execute([$tour_id]);
    $waiting_list_count = $stmt->fetch(PDO::FETCH_ASSOC)['waiting_list_count'];

    // Izračunaj brzinu prodaje i preostale dane
    $startDate = new DateTime($tour['start_date']);
    $endDate = new DateTime($tour['end_date']);
    $today = new DateTime();
    
    $daysPassed = $startDate->diff($today)->days;
    $daysRemaining = $endDate->diff($today)->days;
    $salesSpeed = $daysPassed > 0 ? ($total_stats['total_quantity'] ?? 0) / $daysPassed : 0;
    $estimatedRemainingSales = $salesSpeed * $daysRemaining;

    // Dohvati statistiku po danima
    $stmt = $pdo->prepare("
        SELECT 
            DATE(s.created_at) as date,
            COUNT(*) as total_sales,
            SUM(s.quantity) as total_quantity,
            SUM(s.price * s.quantity * (1 - s.discount/100)) as total_revenue
        FROM sales s
        WHERE s.tour_id = ?
        GROUP BY DATE(s.created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$tour_id]);
    $daily_stats = $stmt->fetchAll();

    // Očisti output buffer prije kreiranja PDF-a
    ob_end_clean();
    
    // Kreiraj novi PDF dokument
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Postavi informacije o dokumentu
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Sales Tracking System');
    $pdf->SetTitle('Sales Statistics');
    
    // Postavi margine
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Dodaj stranicu
    $pdf->AddPage();
    
    // Dodaj datum i vrijeme izdavanja u header
    $pdf->SetFont('freeserif', '', 8);
    $pdf->Cell(0, 5, 'Issued: ' . date('d.m.Y. H:i'), 0, 1, 'R');
    $pdf->Ln(2);
    
    // Dodaj naslov
    $pdf->SetFont('freeserif', 'B', 14);
    $pdf->Cell(0, 8, 'Sales Statistics', 0, 1, 'C');
    $pdf->Ln(2);
    
    // Dodaj informacije o turi
    $pdf->SetFont('freeserif', 'B', 10);
    $pdf->Cell(0, 6, 'Tour: ' . $tour['name'], 0, 1);
    $pdf->SetFont('freeserif', '', 10);
    $pdf->Cell(0, 6, 'Supplier: ' . $tour['supplier_name'], 0, 1);
    $pdf->Cell(0, 6, 'Period: ' . date('d.m.Y.', strtotime($tour['start_date'])) . ' - ' . date('d.m.Y.', strtotime($tour['end_date'])), 0, 1);
    $pdf->Ln(3);
    
    // Dodaj detaljni sažetak
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, 'Detailed summary', 0, 1);
    $pdf->Ln(2);
    
    $pdf->SetFont('freeserif', '', 10);
    $pageWidth = $pdf->getPageWidth() - 30;
    $columnWidth = $pageWidth / 2;
    
    // Prvi red sažetka
    $pdf->Cell($columnWidth, 6, 'Total sales: ' . number_format($total_stats['total_sales'], 0), 0, 0);
    $pdf->Cell($columnWidth, 6, 'Total quantity: ' . number_format($total_stats['total_quantity'], 0), 0, 1);
    
    // Drugi red sažetka
    $pdf->Cell($columnWidth, 6, 'Total revenue: ' . number_format($total_stats['total_revenue'], 2) . ' €', 0, 0);
    $pdf->Cell($columnWidth, 6, 'Total discounts: ' . number_format($total_stats['total_discounts'], 2) . ' €', 0, 1);
    
    // Treći red sažetka
    $pdf->Cell($columnWidth, 6, 'Total expenses: ' . number_format($total_expenses, 2) . ' €', 0, 0);
    $pdf->Cell($columnWidth, 6, 'Net revenue: ' . number_format($total_stats['total_revenue'] - $total_expenses - $total_stats['total_discounts'], 2) . ' €', 0, 1);
    
    // Četvrti red sažetka
    $pdf->Cell($columnWidth, 6, 'Waiting list: ' . number_format($waiting_list_count, 0), 0, 0);
    $pdf->Cell($columnWidth, 6, 'Sales speed: ' . number_format($salesSpeed, 2) . ' kom/dan', 0, 1);
    
    // Peti red sažetka
    $pdf->Cell($columnWidth, 6, 'Remaining days: ' . $daysRemaining, 0, 0);
    $pdf->Cell($columnWidth, 6, 'Estimated remaining sales: ' . number_format($estimatedRemainingSales, 2) . ' kom', 0, 1);
    
    $pdf->Ln(3);
    
    // Dodaj statistiku po prodavačima
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, 'Statistics by salesperson', 0, 1);
    $pdf->Ln(2);
    
    // Postavi širine stupaca
    $w = array(60, 30, 30, 40);
    
    // Dodaj zaglavlje tablice
    $pdf->SetFont('freeserif', 'B', 8);
    $header = array('Salesperson', 'Number of sales', 'Quantity', 'Revenue');
    foreach($header as $i => $col) {
        $pdf->Cell($w[$i], 6, $col, 1, 0, 'C');
    }
    $pdf->Ln();
    
    // Dodaj podatke o prodavačima
    $pdf->SetFont('freeserif', '', 8);
    foreach($salesperson_stats as $stat) {
        $pdf->Cell($w[0], 6, $stat['salesperson_name'], 1);
        $pdf->Cell($w[1], 6, number_format($stat['total_sales'], 0), 1, 0, 'C');
        $pdf->Cell($w[2], 6, number_format($stat['total_quantity'], 0), 1, 0, 'C');
        $pdf->Cell($w[3], 6, number_format($stat['total_revenue'], 2) . ' €', 1, 0, 'C');
        $pdf->Ln();
    }
    
    $pdf->Ln(3);
    
    // Dodaj statistiku po proizvodima
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, 'Statistics by product', 0, 1);
    $pdf->Ln(2);
    
    // Dodaj zaglavlje tablice
    $pdf->SetFont('freeserif', 'B', 8);
    $header = array('Product', 'Number of sales', 'Quantity', 'Revenue');
    foreach($header as $i => $col) {
        $pdf->Cell($w[$i], 6, $col, 1, 0, 'C');
    }
    $pdf->Ln();
    
    // Dodaj podatke o proizvodima
    $pdf->SetFont('freeserif', '', 8);
    foreach($product_stats as $stat) {
        $pdf->Cell($w[0], 6, $stat['product_name'], 1);
        $pdf->Cell($w[1], 6, number_format($stat['total_sales'], 0), 1, 0, 'C');
        $pdf->Cell($w[2], 6, number_format($stat['total_quantity'], 0), 1, 0, 'C');
        $pdf->Cell($w[3], 6, number_format($stat['total_revenue'], 2) . ' €', 1, 0, 'C');
        $pdf->Ln();
    }
    
    // Dodaj statistiku po danima
    $pdf->AddPage();
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, 'Statistics by day', 0, 1);
    $pdf->Ln(2);
    
    // Postavi širine stupaca za dnevnu statistiku
    $w_daily = array(40, 30, 30, 40);
    
    // Dodaj zaglavlje tablice
    $pdf->SetFont('freeserif', 'B', 8);
    $header = array('Date', 'Number of sales', 'Quantity', 'Revenue');
    foreach($header as $i => $col) {
        $pdf->Cell($w_daily[$i], 6, $col, 1, 0, 'C');
    }
    $pdf->Ln();
    
    // Dodaj podatke o dnevnoj statistici
    $pdf->SetFont('freeserif', '', 8);
    foreach($daily_stats as $stat) {
        $pdf->Cell($w_daily[0], 6, date('d.m.Y.', strtotime($stat['date'])), 1);
        $pdf->Cell($w_daily[1], 6, number_format($stat['total_sales'], 0), 1, 0, 'C');
        $pdf->Cell($w_daily[2], 6, number_format($stat['total_quantity'], 0), 1, 0, 'C');
        $pdf->Cell($w_daily[3], 6, number_format($stat['total_revenue'], 2) . ' €', 1, 0, 'C');
        $pdf->Ln();
    }
    
    // Ispiši PDF
    $pdf->Output('sales_statistics.pdf', 'D');
    
} catch (PDOException $e) {
    ob_end_clean();
    die('Error retrieving data: ' . $e->getMessage());
}
?> 