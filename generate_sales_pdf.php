<?php
// First prevent any output
ob_start();

// Error reporting settings - but without sending to browser
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
    // Get tour information
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

    // Get all sales for the tour
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            p.name as product_name,
            sp.name as salesperson_name
        FROM sales s
        JOIN products p ON s.product_id = p.id
        JOIN salespeople sp ON s.salesperson_id = sp.id
        WHERE s.tour_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$tour_id]);
    $sales = $stmt->fetchAll();

    // Clear output buffer before creating PDF
    ob_end_clean();
    
    // Create new PDF document
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Sales Tracking System');
    $pdf->SetTitle('Sales Details');
    
    // Set margins
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add page
    $pdf->AddPage();
    
    // Add issue date and time to header
    $pdf->SetFont('freeserif', '', 8);
    $pdf->Cell(0, 5, 'Issued: ' . date('d.m.Y. H:i'), 0, 1, 'R');
    $pdf->Ln(2);
    
    // Add title
    $pdf->SetFont('freeserif', 'B', 14);
    $pdf->Cell(0, 8, 'Sales Details', 0, 1, 'C');
    $pdf->Ln(2);
    
    // Add tour information
    $pdf->SetFont('freeserif', 'B', 10);
    $pdf->Cell(0, 6, 'Tour: ' . $tour['name'], 0, 1);
    $pdf->SetFont('freeserif', '', 10);
    $pdf->Cell(0, 6, 'Supplier: ' . $tour['supplier_name'], 0, 1);
    $pdf->Cell(0, 6, 'Period: ' . date('d.m.Y.', strtotime($tour['start_date'])) . ' - ' . date('d.m.Y.', strtotime($tour['end_date'])), 0, 1);
    $pdf->Ln(3);

    // Calculate total data
    $total_quantity = 0;
    $total_revenue = 0;
    $total_discount = 0;

    foreach($sales as $sale) {
        $total = $sale['price'] * $sale['quantity'];
        if ($sale['discount_type'] === 'percentage') {
            $total = $total * (1 - $sale['discount']/100);
            $total_discount += ($sale['price'] * $sale['quantity'] * $sale['discount']/100);
        } else {
            $total = $total - $sale['discount'];
            $total_discount += $sale['discount'];
        }
        
        $total_quantity += $sale['quantity'];
        $total_revenue += $total;
    }

    // Add total data
    $pdf->SetFont('freeserif', 'B', 10);
    $pdf->Cell(0, 8, 'TOTAL:', 0, 1);
    $pdf->SetFont('freeserif', '', 10);
    $pdf->Cell(0, 6, 'Total quantity: ' . number_format($total_quantity, 0) . ' pcs', 0, 1);
    $pdf->Cell(0, 6, 'Total discounts: ' . number_format($total_discount, 2) . ' €', 0, 1);
    $pdf->Cell(0, 6, 'Total sales: ' . number_format($total_revenue, 2) . ' €', 0, 1);
    $pdf->Ln(5);

    // Calculate maximum width for product
    $max_product_width = 0;
    $pdf->SetFont('freeserif', '', 8);
    foreach($sales as $sale) {
        $width = $pdf->GetStringWidth($sale['product_name']);
        if ($width > $max_product_width) {
            $max_product_width = $width;
        }
    }
    $max_product_width = min($max_product_width + 4, 60); // Limit max width to 60mm

    // Set column widths
    $w = array(25, $max_product_width, 30, 25, 20, 20, 20, 20);
    
    // Add table header
    $pdf->SetFont('freeserif', 'B', 8);
    $header = array('Date', 'Product', 'Customer', 'Salesperson', 'Quantity', 'Price', 'Discount', 'Total');
    foreach($header as $i => $col) {
        $pdf->Cell($w[$i], 6, $col, 1, 0, 'C');
    }
    $pdf->Ln();
    
    // Add sales data
    $pdf->SetFont('freeserif', '', 8);
    foreach($sales as $sale) {
        $total = $sale['price'] * $sale['quantity'];
        if ($sale['discount_type'] === 'percentage') {
            $total = $total * (1 - $sale['discount']/100);
        } else {
            $total = $total - $sale['discount'];
        }
        
        $pdf->Cell($w[0], 6, date('d.m.Y.', strtotime($sale['created_at'])), 1);
        $pdf->Cell($w[1], 6, $sale['product_name'], 1);
        $pdf->Cell($w[2], 6, $sale['customer_name'], 1);
        $pdf->Cell($w[3], 6, $sale['salesperson_name'], 1);
        $pdf->Cell($w[4], 6, number_format($sale['quantity'], 0), 1, 0, 'C');
        $pdf->Cell($w[5], 6, number_format($sale['price'], 2) . ' €', 1, 0, 'C');
        
        // Show discount based on type
        if ($sale['discount_type'] === 'percentage') {
            $pdf->Cell($w[6], 6, number_format($sale['discount'], 2) . '%', 1, 0, 'C');
        } else {
            $pdf->Cell($w[6], 6, number_format($sale['discount'], 2) . ' €', 1, 0, 'C');
        }
        
        $pdf->Cell($w[7], 6, number_format($total, 2) . ' €', 1, 0, 'C');
        $pdf->Ln();
    }
    
    // Output PDF
    $pdf->Output('sales_details.pdf', 'D');
    
} catch (PDOException $e) {
    ob_end_clean();
    die('Error retrieving data: ' . $e->getMessage());
}
?> 