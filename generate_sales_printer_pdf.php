<?php
// Prvo spriječimo bilo kakav output
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
        die('Turneja nije pronađena');
    }

    // Dohvati sve prodaje za turu
    $stmt = $pdo->prepare("
        SELECT s.*, p.name as product_name, sp.name as salesperson_name
        FROM sales s
        JOIN products p ON s.product_id = p.id
        JOIN salespeople sp ON s.salesperson_id = sp.id
        WHERE s.tour_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$tour_id]);
    $sales = $stmt->fetchAll();

    // Očisti output buffer prije kreiranja PDF-a
    ob_end_clean();
    
    // Kreiraj novi PDF dokument za printer
    $pdf = new TCPDF('P', 'mm', array(55, 200), true, 'UTF-8', false);
    
    // Postavi informacije o dokumentu
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Sustav praćenja prodaje');
    $pdf->SetTitle('Prodaja');
    
    // Postavi margine
    $pdf->SetMargins(2, 2, 2);
    $pdf->SetAutoPageBreak(TRUE, 2);
    
    // Isključi automatsko dodavanje donje crte
    $pdf->setPrintFooter(false);
    
    // Dodaj stranicu
    $pdf->AddPage();
    
    // Dodaj datum i vrijeme izdavanja
    $pdf->SetFont('freeserif', '', 6);
    $pdf->Cell(0, 2, 'Izdano: ' . date('d.m.Y. H:i'), 0, 1, 'R');
    
    // Dodaj naslov
    $pdf->SetFont('freeserif', 'B', 8);
    $pdf->Cell(0, 3, 'PRODAJA', 0, 1, 'C');
    
    // Dodaj informacije o turi
    $pdf->SetFont('freeserif', 'B', 7);
    $pdf->Cell(0, 3, 'Tura: ' . $tour['name'], 0, 1);
    $pdf->SetFont('freeserif', '', 7);
    $pdf->Cell(0, 3, 'Dobavljač: ' . $tour['supplier_name'], 0, 1);
    $pdf->Cell(0, 3, 'Period: ' . date('d.m.Y.', strtotime($tour['start_date'])), 0, 1);
    $pdf->Cell(0, 3, date('d.m.Y.', strtotime($tour['end_date'])), 0, 1);
    $pdf->Ln(1);
    
    // Iscrtaj podatke
    $pdf->SetFont('freeserif', '', 7);
    foreach($sales as $sale) {
        // Datum, prodavač i proizvod u jednom redu
        $pdf->Cell(12, 3, date('d.m.', strtotime($sale['created_at'])), 0);
        $pdf->Cell(0, 3, $sale['product_name'], 0, 1);
        
        // Prodavač i kupac u jednom redu
        $pdf->Cell(25, 3, $sale['salesperson_name'], 0);
        $pdf->Cell(0, 3, $sale['customer_name'], 0, 1);
        
        // Količina, cijena i popust u jednom redu
        $pdf->Cell(12, 3, 'Kol:' . $sale['quantity'], 0);
        $pdf->Cell(20, 3, number_format($sale['price'], 2) . '€', 0);
        $pdf->Cell(0, 3, ($sale['discount_type'] == 'percentage' ? 
            $sale['discount'] . '%' : 
            number_format($sale['discount'], 2) . '€'), 0, 1);
        
        // Izračunaj i prikaži ukupno
        $total = $sale['price'] * $sale['quantity'];
        if ($sale['discount_type'] == 'percentage') {
            $total = $total * (1 - $sale['discount']/100);
        } else {
            $total = $total - $sale['discount'];
        }
        $pdf->SetFont('freeserif', 'B', 7);
        $pdf->Cell(0, 3, 'Ukupno: ' . number_format($total, 2) . ' €', 0, 1);
        
        // Dodaj liniju između prodaja
        $pdf->Line(2, $pdf->GetY(), 53, $pdf->GetY());
        $pdf->Ln(1);
        $pdf->SetFont('freeserif', '', 7);
    }
    
    // Ispiši PDF
    $pdf->Output('prodaja_printer.pdf', 'D');
    
} catch (PDOException $e) {
    ob_end_clean();
    die('Greška pri dohvaćanju podataka: ' . $e->getMessage());
}
?> 