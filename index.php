<!-- Author: Toni Mladenic (tonimladenic@gmail.com) -->
<!DOCTYPE html>
<html lang="en">
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Provjeri je li tura arhivirana
if (isset($_SESSION['current_tour_id'])) {
    $stmt = $pdo->prepare("SELECT archived FROM tours WHERE id = ?");
    $stmt->execute([$_SESSION['current_tour_id']]);
    $tour = $stmt->fetch();
    
    if ($tour && $tour['archived']) {
        $_SESSION['message'] = 'Ova tura je arhivirana. Možete samo pregledavati podatke.';
        $_SESSION['message_type'] = 'warning';
    }
}

// Handle tour selection
if (isset($_POST['tour_id'])) {
    $_SESSION['current_tour_id'] = $_POST['tour_id'];
    header('Location: index.php');
    exit;
}

// Postavi da smo na index stranici
$isIndexPage = true;
require_once 'includes/header.php';

// Provjeri je li odabrana turneja
if (!isset($_SESSION['current_tour_id'])) {
    $_SESSION['message'] = "Molimo odaberite turneju.";
    $_SESSION['message_type'] = "warning";
}

// Prikaži upozorenje za arhiviranu turu
if (isset($_SESSION['current_tour_id'])) {
    $stmt = $pdo->prepare("SELECT archived FROM tours WHERE id = ?");
    $stmt->execute([$_SESSION['current_tour_id']]);
    $tour = $stmt->fetch();
    
    if ($tour && $tour['archived']) {
        echo '<div class="container">';
        echo '<div class="archive-warning">';
        echo '<i class="fas fa-archive"></i> Ova tura je arhivirana. Možete samo pregledavati podatke.';
        echo '</div>';
        echo '</div>';
    }
}

// Get current tour if set
$currentTour = null;
if (isset($_SESSION['current_tour_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, s.name as supplier_name 
            FROM tours t 
            LEFT JOIN suppliers s ON t.supplier_id = s.id 
            WHERE t.id = ?
        ");
        $stmt->execute([$_SESSION['current_tour_id']]);
        $currentTour = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching current tour: " . $e->getMessage());
    }
}

// Get all tours
$stmt = $pdo->prepare("
    SELECT t.*, s.name as supplier_name 
    FROM tours t 
    LEFT JOIN suppliers s ON t.supplier_id = s.id 
    ORDER BY t.name
");
$stmt->execute();
$tours = $stmt->fetchAll();

// Get all products
$stmt = $pdo->prepare("SELECT * FROM products ORDER BY name");
$stmt->execute();
$products = $stmt->fetchAll();

// Get all active salespeople
$stmt = $pdo->prepare("SELECT * FROM salespeople WHERE active = 1 ORDER BY name");
$stmt->execute();
$salespeople = $stmt->fetchAll();

// Get all expenses for current tour
$totalExpenses = 0;
if ($currentTour) {
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE tour_id = ?");
    $stmt->execute([$_SESSION['current_tour_id']]);
    $result = $stmt->fetch();
    $totalExpenses = $result['total'] ?? 0;
}

// Get all sales for current tour
$totalSales = 0;
if ($currentTour) {
    $stmt = $pdo->prepare("SELECT SUM(price * quantity) as total FROM sales WHERE tour_id = ?");
    $stmt->execute([$_SESSION['current_tour_id']]);
    $result = $stmt->fetch();
    $totalSales = $result['total'] ?? 0;
}

// Get all deposits for current tour
$totalDeposits = 0;
if ($currentTour) {
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM deposits WHERE tour_id = ?");
    $stmt->execute([$_SESSION['current_tour_id']]);
    $result = $stmt->fetch();
    $totalDeposits = $result['total'] ?? 0;
}

// Calculate profit
$profit = $totalSales - $totalExpenses;

// Calculate profit percentage
$profitPercentage = $totalSales > 0 ? ($profit / $totalSales) * 100 : 0;

// Format numbers
$totalSales = number_format($totalSales, 2, ',', '.');
$totalExpenses = number_format($totalExpenses, 2, ',', '.');
$totalDeposits = number_format($totalDeposits, 2, ',', '.');
$profit = number_format($profit, 2, ',', '.');
$profitPercentage = number_format($profitPercentage, 2, ',', '.');

// Display tour selection form
echo '<div class="container">';
echo '<div class="tour-selection">';
echo '<h3>Odabir ture</h3>';
echo '<form method="post" action="index.php">';
echo '<select name="tour_id" onchange="this.form.submit()">';
echo '<option value="">-- Odaberi turu --</option>';

if (count($tours) > 0) {
    foreach ($tours as $tour) {
        $selected = isset($_SESSION['current_tour_id']) && $_SESSION['current_tour_id'] == $tour['id'] ? 'selected' : '';
        echo '<option value="' . $tour['id'] . '" ' . $selected . '>';
        echo htmlspecialchars($tour['name']) . ' (' . date('d.m.Y.', strtotime($tour['start_date'])) . ' - ' . date('d.m.Y.', strtotime($tour['end_date'])) . ')';
        echo '</option>';
    }
} else {
    echo '<option value="" disabled>Nema dostupnih tura</option>';
}

echo '</select>';
echo '</form>';
echo '</div>';
echo '</div>';

// Display current tour info and statistics
if ($currentTour) {
    // Display quick actions
    if ($currentTour && !isTourArchived($pdo, $currentTour['id'])) {
        echo '<div class="container">';
        echo '<div class="quick-actions">';
        echo '<h3>Brze akcije</h3>';
        echo '<div class="action-buttons">';
        echo '<a href="sales.php" class="action-btn">Nova prodaja</a>';
        echo '<a href="waiting_list.php" class="action-btn">Lista čekanja</a>';
        echo '<a href="expenses.php" class="action-btn">Troškovi</a>';
        echo '<a href="notes.php" class="action-btn">Bilješke</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    echo '<div class="container">';
    echo '<div class="current-tour-info">';
    echo '<h2>' . htmlspecialchars($currentTour['name']) . '</h2>';
    echo '<p>Dobavljač: ' . htmlspecialchars($currentTour['supplier_name']) . '</p>';
    echo '<p>Datum početka: ' . date('d.m.Y.', strtotime($currentTour['start_date'])) . '</p>';
    echo '<p>Datum završetka: ' . date('d.m.Y.', strtotime($currentTour['end_date'])) . '</p>';
    
    // Display tour statistics
    echo '<div class="tour-statistics">';
    echo '<h3>Statistika</h3>';
    echo '<div class="stat-grid">';
    echo '<div class="stat-item">';
    echo '<h4>Ukupna prodaja</h4>';
    echo '<p>' . $totalSales . ' €</p>';
    echo '</div>';
    echo '<div class="stat-item">';
    echo '<h4>Ukupni troškovi</h4>';
    echo '<p>' . $totalExpenses . ' €</p>';
    echo '</div>';
    echo '<div class="stat-item">';
    echo '<h4>Ukupni polog</h4>';
    echo '<p>' . $totalDeposits . ' €</p>';
    echo '</div>';
    echo '<div class="stat-item">';
    echo '<h4>Profit</h4>';
    echo '<p>' . $profit . ' € (' . $profitPercentage . '%)</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

?>

<style>
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

.current-tour-info, .tour-selection, .quick-actions {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    width: 100%;
}

/* Stilovi za brze akcije */
.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: space-between;
}

.action-btn {
    flex: 1;
    min-width: 150px;
    text-align: center;
    padding: 10px;
    background-color: #2196F3;
    color: white;
    border-radius: 4px;
    text-decoration: none;
    transition: background-color 0.3s;
}

.action-btn:hover {
    background-color: #1976D2;
}
</style>

<?php require_once 'includes/footer.php'; ?> 
</html> 