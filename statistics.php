<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get all tours
$stmt = $pdo->query("
    SELECT t.*, s.name as supplier_name 
    FROM tours t 
    LEFT JOIN suppliers s ON t.supplier_id = s.id 
    ORDER BY t.start_date DESC
");
$tours = $stmt->fetchAll();

// Initialize statistics variables
$totalSales = 0;
$totalExpenses = 0;
$totalProfit = 0;
$tourStats = [];

// Calculate statistics for each tour
foreach ($tours as $tour) {
    // Get sales
    $stmt = $pdo->prepare("SELECT SUM(price * quantity) as total FROM sales WHERE tour_id = ?");
    $stmt->execute([$tour['id']]);
    $sales = $stmt->fetch()['total'] ?? 0;

    // Get expenses
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE tour_id = ?");
    $stmt->execute([$tour['id']]);
    $expenses = $stmt->fetch()['total'] ?? 0;

    // Calculate profit
    $profit = $sales - $expenses;

    // Add to total amounts
    $totalSales += $sales;
    $totalExpenses += $expenses;
    $totalProfit += $profit;

    // Save tour statistics
    $tourStats[$tour['id']] = [
        'sales' => $sales,
        'expenses' => $expenses,
        'profit' => $profit
    ];
}

// Calculate average values
$avgSales = $tours ? $totalSales / count($tours) : 0;
$avgExpenses = $tours ? $totalExpenses / count($tours) : 0;
$avgProfit = $tours ? $totalProfit / count($tours) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - Sales Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php
require_once 'includes/header.php';

// Check if tour is selected
if (!isset($_SESSION['current_tour_id'])) {
    header('Location: index.php');
    exit;
}

$tour_id = $_SESSION['current_tour_id'];

// Get tour information
$stmt = $pdo->prepare("SELECT t.*, s.name as supplier_name 
                       FROM tours t 
                       LEFT JOIN suppliers s ON t.supplier_id = s.id 
                       WHERE t.id = ?");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch();

if (!$tour) {
    header('Location: index.php');
    exit;
}

// Show warning for archived tour
if ($tour && $tour['archived']) {
    echo '<div class="container">';
    echo '<div class="archive-warning">';
    echo '<i class="fas fa-archive"></i> This tour is archived. You can only view data.';
    echo '</div>';
    echo '</div>';
}

// Get statistics by salesperson
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

// Get statistics by product
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

// Get total statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_sales,
    COALESCE(SUM(quantity), 0) as total_quantity,
    COALESCE(SUM(price * quantity), 0) as total_revenue,
    COALESCE(SUM(discount), 0) as total_discounts
FROM sales WHERE tour_id = ?");
$stmt->execute([$tour_id]);
$total_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total expenses
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_expenses FROM expenses WHERE tour_id = ?");
$stmt->execute([$tour_id]);
$total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'];

// Get statistics for current tour
$stats = [
    'total_sales' => 0,
    'total_quantity' => 0,
    'total_revenue' => 0,
    'total_discounts' => 0,
    'waiting_list_count' => 0,
    'total_expenses' => 0,
    'sales_speed' => 0,
    'days_remaining' => 0
];

if ($tour) {
    try {
        // Get sales statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_sales,
                SUM(quantity) as total_quantity,
                SUM(quantity * price) as total_revenue,
                SUM(discount) as total_discounts
            FROM sales 
            WHERE tour_id = ?
        ");
        $stmt->execute([$tour['id']]);
        $salesStats = $stmt->fetch();

        // Get waiting list count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM waiting_list 
            WHERE tour_id = ?
        ");
        $stmt->execute([$tour['id']]);
        $waitingListCount = $stmt->fetchColumn();

        // Calculate sales speed and days remaining
        $startDate = new DateTime($tour['start_date']);
        $endDate = new DateTime($tour['end_date']);
        $today = new DateTime();
        
        $daysPassed = $startDate->diff($today)->days;
        $totalDays = $startDate->diff($endDate)->days;
        $daysRemaining = $endDate->diff($today)->days;
        
        $salesSpeed = $daysPassed > 0 ? ($salesStats['total_quantity'] ?? 0) / $daysPassed : 0;
        $estimatedRemainingSales = $salesSpeed * $daysRemaining;

        $stats = [
            'total_sales' => $salesStats['total_sales'] ?? 0,
            'total_quantity' => $salesStats['total_quantity'] ?? 0,
            'total_revenue' => $salesStats['total_revenue'] ?? 0,
            'total_discounts' => $salesStats['total_discounts'] ?? 0,
            'waiting_list_count' => $waitingListCount ?? 0,
            'total_expenses' => $total_expenses ?? 0,
            'sales_speed' => round($salesSpeed, 2),
            'days_remaining' => $daysRemaining,
            'estimated_remaining_sales' => round($estimatedRemainingSales, 2)
        ];
    } catch (PDOException $e) {
        error_log("Error fetching statistics: " . $e->getMessage());
    }
}
?>

<div class="container">
    <h1>Sales Statistics</h1>
    <h2><?php echo htmlspecialchars($tour['name']); ?></h2>
    <p>Supplier: <?php echo htmlspecialchars($tour['supplier_name']); ?></p>
    <p>Period: <?php echo date('d.m.Y.', strtotime($tour['start_date'])); ?> - <?php echo date('d.m.Y.', strtotime($tour['end_date'])); ?></p>

    <div class="export-buttons">
        <a href="generate_statistics_pdf.php?tour_id=<?php echo $tour_id; ?>" class="btn btn-primary">
            <i class="fas fa-file-pdf"></i> Download PDF
        </a>
        <a href="generate_statistics_printer_pdf.php?tour_id=<?php echo $tour_id; ?>" class="btn btn-secondary">
            <i class="fas fa-print"></i> Printer PDF
        </a>
    </div>

    <div class="tour-stats">
        <h2>Statistics for <?php echo htmlspecialchars($tour['name']); ?></h2>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Sales</h3>
                <p class="stat-value"><?php echo $stats['total_sales']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Quantity</h3>
                <p class="stat-value"><?php echo $stats['total_quantity']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <p class="stat-value"><?php echo number_format($stats['total_revenue'], 2); ?> €</p>
            </div>
            <div class="stat-card">
                <h3>Total Discounts</h3>
                <p class="stat-value"><?php echo number_format($stats['total_discounts'], 2); ?> €</p>
            </div>
            <div class="stat-card">
                <h3>Waiting List</h3>
                <p class="stat-value"><?php echo $stats['waiting_list_count']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Expenses</h3>
                <p class="stat-value"><?php echo number_format($stats['total_expenses'], 2); ?> €</p>
            </div>
            <div class="stat-card">
                <h3>Sales Speed</h3>
                <p class="stat-value"><?php echo $stats['sales_speed']; ?> per day</p>
            </div>
            <div class="stat-card">
                <h3>Days Remaining</h3>
                <p class="stat-value"><?php echo $stats['days_remaining']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Estimated Remaining Sales</h3>
                <p class="stat-value"><?php echo $stats['estimated_remaining_sales']; ?> per day</p>
            </div>
        </div>
    </div>

    <div class="stats-section">
        <h2>Statistics by Salesperson</h2>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Salesperson</th>
                        <th>Number of Sales</th>
                        <th>Quantity</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salesperson_stats as $stat): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($stat['salesperson_name']); ?></td>
                        <td><?php echo number_format($stat['total_sales']); ?></td>
                        <td><?php echo number_format($stat['total_quantity']); ?></td>
                        <td>€<?php echo number_format($stat['total_revenue'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="stats-section">
        <h2>Statistics by Product</h2>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Number of Sales</th>
                        <th>Quantity</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($product_stats as $stat): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($stat['product_name']); ?></td>
                        <td><?php echo number_format($stat['total_sales']); ?></td>
                        <td><?php echo number_format($stat['total_quantity']); ?></td>
                        <td>€<?php echo number_format($stat['total_revenue'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
:root {
    --background-color: #f5f5f5;
    --text-color: #333;
    --card-background: #fff;
    --border-color: #ddd;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.archive-warning {
    background-color: #fff3cd;
    color: #856404;
    padding: 1rem;
    margin-bottom: 20px;
    border: 1px solid #ffeeba;
    border-radius: 8px;
    text-align: center;
    font-weight: 500;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.archive-warning i {
    margin-right: 8px;
}

.tour-stats {
    margin: 30px 0;
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tour-stats h2 {
    margin-top: 0;
    color: #333;
    font-size: 1.5em;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 1.1em;
    color: #666;
}

.stat-value {
    font-size: 1.8em;
    font-weight: bold;
    color: #333;
    margin: 0;
}

.stats-section {
    margin: 30px 0;
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stats-section h2 {
    margin-top: 0;
    color: #333;
    font-size: 1.5em;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.table th,
.table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.table tr:hover {
    background-color: #f5f5f5;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?> 