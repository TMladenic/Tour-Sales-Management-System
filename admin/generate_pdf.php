<?php
// First prevent any output
ob_start();

// Error reporting settings - but without sending to browser
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../vendor/tcpdf/tcpdf.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    ob_end_clean();
    die('Access denied');
}

if (!isset($_GET['id'])) {
    ob_end_clean();
    die('Calculation ID not specified');
}

$calculationId = (int)$_GET['id'];

try {
    // Get main calculation data
    $stmt = $pdo->prepare("
        SELECT ic.*, 
               t.name as tour_name,
               t.start_date as tour_start_date,
               t.end_date as tour_end_date,
               s.name as supplier_name,
               GROUP_CONCAT(
                   CONCAT(
                       ii.investor_type, '_', ii.investor_id, '|',
                       ii.percentage, '|',
                       ii.investment, '|',
                       ii.profit_share, '|',
                       ii.expenses_share, '|',
                       ii.discounts_share, '|',
                       ii.payout, '|',
                       ii.future_investment_share, '|',
                       ii.final_payout, '|',
                       ii.notes
                   ) SEPARATOR '||'
               ) as investors_data
        FROM investment_calculations ic
        LEFT JOIN tours t ON ic.tour_id = t.id
        LEFT JOIN suppliers s ON t.supplier_id = s.id
        LEFT JOIN investment_investors ii ON ic.id = ii.calculation_id
        WHERE ic.id = ?
        GROUP BY ic.id
    ");
    $stmt->execute([$calculationId]);
    $calculation = $stmt->fetch();
    
    if (!$calculation) {
        ob_end_clean();
        die('Calculation not found');
    }
    
    // Get investor data
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
    
    // Clear output buffer before creating PDF
    ob_end_clean();
    
    // Create new PDF document - set landscape orientation ('L')
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Sales Tracking System');
    $pdf->SetTitle('Investment and Profit Calculation');
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add page
    $pdf->AddPage();
    
    // Add title
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->Cell(0, 10, 'Investment and Profit Calculation', 0, 1, 'C');
    $pdf->Ln(2);
    
    // Add tour information
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 10, 'Tour: ' . $calculation['tour_name'], 0, 1);
    $pdf->SetFont('dejavusans', '', 12);
    $pdf->Cell(0, 10, 'Supplier: ' . $calculation['supplier_name'], 0, 1);
    $pdf->Cell(0, 10, 'Period: ' . date('d.m.Y.', strtotime($calculation['tour_start_date'])) . ' - ' . date('d.m.Y.', strtotime($calculation['tour_end_date'])), 0, 1);
    $pdf->Ln(5);
    
    // Add summary in two columns to save space
    $pdf->SetFont('dejavusans', '', 12);
    $pageWidth = $pdf->getPageWidth() - 30; // 30 for margins (15 on each side)
    $columnWidth = $pageWidth / 2;
    
    // First row of summary
    $pdf->Cell($columnWidth, 10, 'Total Investment: ' . number_format($calculation['total_investment'], 2) . ' €', 0, 0);
    $pdf->Cell($columnWidth, 10, 'Gross Profit: ' . number_format($calculation['gross_profit'], 2) . ' €', 0, 1);
    
    // Second row of summary
    $pdf->Cell($columnWidth, 10, 'Total Expenses: ' . number_format($calculation['total_expenses'], 2) . ' €', 0, 0);
    $pdf->Cell($columnWidth, 10, 'Total Discounts: ' . number_format($calculation['total_discounts'], 2) . ' €', 0, 1);
    
    // Third row of summary
    $pdf->Cell($columnWidth, 10, 'Net Profit: ' . number_format($calculation['net_profit'], 2) . ' €', 0, 0);
    $pdf->Cell($columnWidth, 10, 'Future Investment: ' . number_format($calculation['future_investment'], 2) . ' €', 0, 1);
    $pdf->Ln(5);
    
    // Add table title
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 10, 'Distribution by Investors', 0, 1);
    $pdf->Ln(2);
    
    // Set column widths - reduced values
    $w = array(45, 15, 20, 20, 20, 20, 20, 20, 20, 35);
    
    // Add table header
    $pdf->SetFont('dejavusans', 'B', 9); // Reduced font for header
    $header = array('Investor', 'Percentage', 'Investment', 'Profit', 'Expenses', 'Discounts', 'Payout', 'Future Inv.', 'Final Payout', 'Notes');
    foreach($header as $i => $col) {
        $pdf->Cell($w[$i], 7, $col, 1, 0, 'C');
    }
    $pdf->Ln();
    
    // Add investor data
    $pdf->SetFont('dejavusans', '', 9); // Reduced font for data
    foreach($investors as $investor) {
        $startY = $pdf->GetY(); // Remember initial Y position
        $currentX = $pdf->GetX(); // Remember initial X position
        
        // First calculate note height to know how much space we need
        $pdf->startTransaction();
        $start_page = $pdf->getPage();
        $pdf->MultiCell($w[9], 6, $investor['notes'] ?? '', 1, 'L');
        $notes_height = $pdf->getY() - $startY;
        $pdf->rollbackTransaction(true);
        
        // Set back to initial position
        $pdf->setPage($start_page);
        $pdf->SetY($startY);
        $pdf->SetX($currentX);
        
        // Print all cells with same height
        $row_height = max(6, $notes_height);
        
        $pdf->Cell($w[0], $row_height, $investor['name'], 1);
        $pdf->Cell($w[1], $row_height, number_format($investor['percentage'], 2) . '%', 1, 0, 'C');
        $pdf->Cell($w[2], $row_height, number_format($investor['investment'], 2) . ' €', 1, 0, 'C');
        $pdf->Cell($w[3], $row_height, number_format($investor['profit_share'], 2) . ' €', 1, 0, 'C');
        $pdf->Cell($w[4], $row_height, number_format($investor['expenses_share'], 2) . ' €', 1, 0, 'C');
        $pdf->Cell($w[5], $row_height, number_format($investor['discounts_share'], 2) . ' €', 1, 0, 'C');
        $pdf->Cell($w[6], $row_height, number_format($investor['payout'], 2) . ' €', 1, 0, 'C');
        $pdf->Cell($w[7], $row_height, number_format($investor['future_investment_share'], 2) . ' €', 1, 0, 'C');
        $pdf->Cell($w[8], $row_height, number_format($investor['final_payout'], 2) . ' €', 1, 0, 'C');
        
        // Print note with MultiCell
        $pdf->MultiCell($w[9], $row_height, $investor['notes'] ?? '', 1, 'L');
    }
    
    // Output PDF
    $pdf->Output('investment_and_profit_calculation.pdf', 'D');
    
} catch (PDOException $e) {
    ob_end_clean();
    die('Error retrieving data: ' . $e->getMessage());
} catch (Exception $e) {
    ob_end_clean();
    die('Error generating PDF: ' . $e->getMessage());
}
?> 