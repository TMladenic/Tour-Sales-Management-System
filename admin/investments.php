<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Provjeri je li korisnik admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Dohvati sve aktivne prodavače
$stmt = $pdo->query("SELECT * FROM salespeople WHERE active = 1 ORDER BY name");
$salespeople = $stmt->fetchAll();

// Dohvati sve promotere
$stmt = $pdo->query("SELECT * FROM promoters ORDER BY name");
$promoters = $stmt->fetchAll();

// Dohvati trenutnu turu
$currentTour = null;
if (isset($_SESSION['current_tour_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM tours WHERE id = ?");
    $stmt->execute([$_SESSION['current_tour_id']]);
    $currentTour = $stmt->fetch();
}

if (!isset($_SESSION['current_tour_id'])) {
    $_SESSION['message'] = "Molimo odaberite turu.";
    $_SESSION['message_type'] = "warning";
    header('Location: tours.php');
    exit;
}

// Clear the tour selection message if it exists
if (isset($_SESSION['message']) && $_SESSION['message'] === "Molimo odaberite turu.") {
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Dohvati ukupne troškove za trenutnu turu
$totalExpenses = 0;
$totalDiscounts = 0;
if ($currentTour) {
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE tour_id = ?");
    $stmt->execute([$currentTour['id']]);
    $result = $stmt->fetch();
    $totalExpenses = $result['total'] ?? 0;

    // Dohvati ukupne popuste za trenutnu turu
    $stmt = $pdo->prepare("SELECT SUM(discount) as total FROM sales WHERE tour_id = ?");
    $stmt->execute([$currentTour['id']]);
    $result = $stmt->fetch();
    $totalDiscounts = $result['total'] ?? 0;
}

// Obradi formu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $totalInvestment = $_POST['total_investment'];
        $productPrice = $_POST['product_price'];
        $productQuantity = $_POST['product_quantity'];
        $futureInvestment = $_POST['future_investment'];
        
        // Izračunaj bruto dobit
        $grossProfit = $productPrice * $productQuantity;
        
        // Izračunaj neto dobit
        $netProfit = $grossProfit - $totalInvestment - $totalExpenses - $totalDiscounts;
        
        // Spremi glavni izračun
        $stmt = $pdo->prepare("
            INSERT INTO investment_calculations (
                tour_id, total_investment, product_price, product_quantity,
                total_expenses, total_discounts, gross_profit, net_profit, future_investment,
                created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $currentTour['id'],
            $totalInvestment,
            $productPrice,
            $productQuantity,
            $totalExpenses,
            $totalDiscounts,
            $grossProfit,
            $netProfit,
            $futureInvestment,
            $_SESSION['user_id']
        ]);
        
        $calculationId = $pdo->lastInsertId();
        
        // Spremi investitore
        foreach ($_POST['investor_id'] as $key => $investorId) {
            $percentage = $_POST['percentage'][$key];
            $notes = $_POST['notes'][$key];
            
            // Izračunaj ulaganje i dobit za svakog investitora
            $investment = $totalInvestment * ($percentage / 100);
            $profitShare = $grossProfit * ($percentage / 100);
            $expensesShare = $totalExpenses * ($percentage / 100);
            $discountsShare = $totalDiscounts * ($percentage / 100);
            $futureInvestmentShare = $futureInvestment * ($percentage / 100);
            $payout = $profitShare - $expensesShare - $discountsShare;
            $finalPayout = $payout - $futureInvestmentShare;
            
            // Odredi tip i ID investitora
            $idParts = explode('_', $investorId);
            $investorType = $idParts[0];
            $investorId = $idParts[1];
            
            $stmt = $pdo->prepare("
                INSERT INTO investment_investors (
                    calculation_id, investor_type, investor_id, percentage,
                    investment, profit_share, expenses_share, discounts_share, payout,
                    future_investment_share, final_payout, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $calculationId,
                $investorType,
                $investorId,
                $percentage,
                $investment,
                $profitShare,
                $expensesShare,
                $discountsShare,
                $payout,
                $futureInvestmentShare,
                $finalPayout,
                $notes
            ]);
        }
        
        $_SESSION['message'] = 'Calculation has been successfully saved.';
        $_SESSION['message_type'] = 'success';
        
        // Dohvati spremljeni izračun za prikaz
        $stmt = $pdo->prepare("
            SELECT ic.*, 
                   GROUP_CONCAT(
                       CONCAT(
                           ii.investor_type, '_', ii.investor_id, '|',
                           ii.percentage, '|',
                           ii.investment, '|',
                           ii.profit_share, '|',
                           ii.expenses_share, '|',
                           COALESCE(ii.discounts_share, 0), '|',
                           ii.payout, '|',
                           ii.future_investment_share, '|',
                           ii.final_payout, '|',
                           COALESCE(ii.notes, '')
                       ) SEPARATOR '||'
                   ) as investors_data
            FROM investment_calculations ic
            LEFT JOIN investment_investors ii ON ic.id = ii.calculation_id
            WHERE ic.id = ?
            GROUP BY ic.id
        ");
        $stmt->execute([$calculationId]);
        $savedCalculation = $stmt->fetch();
        
        // Preusmjeri na pregled spremljenog izračuna
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $calculationId);
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['message'] = 'Error saving calculation: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Dohvati sve spremljene izračune za trenutnu turu
$stmt = $pdo->prepare("
    SELECT ic.*, 
           GROUP_CONCAT(
               CONCAT(
                   ii.investor_type, '_', ii.investor_id, '|',
                   ii.percentage, '|',
                   ii.investment, '|',
                   ii.profit_share, '|',
                   ii.expenses_share, '|',
                   COALESCE(ii.discounts_share, 0), '|',
                   ii.payout, '|',
                   ii.future_investment_share, '|',
                   ii.final_payout, '|',
                   COALESCE(ii.notes, '')
               ) SEPARATOR '||'
           ) as investors_data
    FROM investment_calculations ic
    LEFT JOIN investment_investors ii ON ic.id = ii.calculation_id
    WHERE ic.tour_id = ?
    GROUP BY ic.id
    ORDER BY ic.created_at DESC
");
$stmt->execute([$currentTour['id']]);
$savedCalculations = $stmt->fetchAll();

// Na početku datoteke, nakon dohvaćanja trenutne turneje
$calculationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$currentCalculation = null;

if ($calculationId > 0) {
    // Dohvati glavni izračun
    $stmt = $pdo->prepare("SELECT * FROM investment_calculations WHERE id = ?");
    $stmt->execute([$calculationId]);
    $currentCalculation = $stmt->fetch();

    // Dohvati podatke o investitorima
    if ($currentCalculation) {
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
        $currentCalculation['investors'] = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investments and Profit - Sales Tracking System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php require_once '../includes/header.php'; ?>

<div class="admin-container">
    <h2>Investments and Profit</h2>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message <?php echo $_SESSION['message_type']; ?>">
            <?php 
            echo $_SESSION['message'];
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="form-section">
        <h3>Investment and Profit Calculation</h3>
        <form method="POST" class="admin-form">
            <div class="form-group">
                <label for="total_investment">Total investment (€):</label>
                <input type="number" id="total_investment" name="total_investment" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="product_price">Product price (€):</label>
                <input type="number" id="product_price" name="product_price" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="product_quantity">Product quantity:</label>
                <input type="number" id="product_quantity" name="product_quantity" step="1" required>
            </div>
            
            <div class="form-group">
                <label for="future_investment">Future investment (€):</label>
                <input type="number" id="future_investment" name="future_investment" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label>Total expenses (automatically calculated):</label>
                <input type="text" value="<?php echo number_format($totalExpenses, 2); ?> €" readonly>
            </div>
            
            <div class="form-group">
                <label>Total discounts (automatically calculated):</label>
                <input type="text" value="<?php echo number_format($totalDiscounts, 2); ?> €" readonly>
            </div>
            
            <div id="investors-container">
                <h4>Investors</h4>
                <div class="investor-row">
                    <div class="form-group">
                        <label>Investor:</label>
                        <select name="investor_id[]" required>
                            <option value="">Choose an investor...</option>
                            <?php foreach ($salespeople as $salesperson): ?>
                                <option value="salesperson_<?php echo $salesperson['id']; ?>">
                                    <?php echo htmlspecialchars($salesperson['name']); ?> (Salesperson)
                                </option>
                            <?php endforeach; ?>
                            <?php foreach ($promoters as $promoter): ?>
                                <option value="promoter_<?php echo $promoter['id']; ?>">
                                    <?php echo htmlspecialchars($promoter['name']); ?> (Promoter)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Percentage (%):</label>
                        <input type="number" name="percentage[]" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes:</label>
                        <input type="text" name="notes[]">
                    </div>
                </div>
            </div>
            
            <button type="button" id="add-investor" class="btn btn-secondary">Add investor</button>
            <button type="submit" class="btn btn-primary">Calculate and save</button>
        </form>
    </div>
    
    <?php if (!empty($savedCalculations)): ?>
        <div class="list-section">
            <h3>Saved calculations</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total investment</th>
                        <th>Gross profit</th>
                        <th>Net profit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($savedCalculations as $calculation): ?>
                        <tr>
                            <td><?php echo date('d.m.Y. H:i', strtotime($calculation['created_at'])); ?></td>
                            <td><?php echo number_format($calculation['total_investment'], 2); ?> €</td>
                            <td><?php echo number_format($calculation['gross_profit'], 2); ?> €</td>
                            <td><?php echo number_format($calculation['net_profit'], 2); ?> €</td>
                            <td>
                                <button type="button" class="btn btn-primary" onclick="showCalculation(<?php echo $calculation['id']; ?>)">
                                    Preview
                                </button>
                                <button type="button" class="btn btn-danger" onclick="deleteCalculation(<?php echo $calculation['id']; ?>)">
                                    Delete
                                </button>
                                <a href="generate_pdf.php?id=<?php echo $calculation['id']; ?>" class="btn btn-info">
                                    Download PDF
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <?php if ($currentCalculation): ?>
        <div class="results-section">
            <h3>Results</h3>
            
            <div class="summary">
                <p><strong>Total investment:</strong> <?php echo number_format($currentCalculation['total_investment'], 2); ?> €</p>
                <p><strong>Gross profit:</strong> <?php echo number_format($currentCalculation['gross_profit'], 2); ?> €</p>
                <p><strong>Total expenses:</strong> <?php echo number_format($currentCalculation['total_expenses'], 2); ?> €</p>
                <p><strong>Total discounts:</strong> <?php echo number_format($currentCalculation['total_discounts'], 2); ?> €</p>
                <p><strong>Net profit:</strong> <?php echo number_format($currentCalculation['net_profit'], 2); ?> €</p>
            </div>
            
            <h4>Investor distribution</h4>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Investor</th>
                        <th>Percentage</th>
                        <th>Investment</th>
                        <th>Profit</th>
                        <th>Expenses</th>
                        <th>Discounts</th>
                        <th>Payout</th>
                        <th>Future investment</th>
                        <th>Final payout</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($currentCalculation['investors'] as $investor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($investor['name']); ?></td>
                            <td><?php echo number_format($investor['percentage'], 2); ?>%</td>
                            <td><?php echo number_format($investor['investment'], 2); ?> €</td>
                            <td><?php echo number_format($investor['profit_share'], 2); ?> €</td>
                            <td><?php echo number_format($investor['expenses_share'], 2); ?> €</td>
                            <td><?php echo number_format($investor['discounts_share'], 2); ?> €</td>
                            <td><?php echo number_format($investor['payout'], 2); ?> €</td>
                            <td><?php echo number_format($investor['future_investment_share'], 2); ?> €</td>
                            <td><?php echo number_format($investor['final_payout'], 2); ?> €</td>
                            <td><?php echo isset($investor['notes']) ? htmlspecialchars($investor['notes']) : ''; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal za prikaz detalja -->
<div id="calculationModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <div id="modalContent"></div>
    </div>
</div>

<script>
document.getElementById('add-investor').addEventListener('click', function() {
    const container = document.getElementById('investors-container');
    const newRow = document.createElement('div');
    newRow.className = 'investor-row';
    newRow.innerHTML = `
        <div class="form-group">
            <label>Investor:</label>
            <select name="investor_id[]" required>
                <option value="">Choose an investor...</option>
                <?php foreach ($salespeople as $salesperson): ?>
                    <option value="salesperson_<?php echo $salesperson['id']; ?>">
                        <?php echo htmlspecialchars($salesperson['name']); ?> (Salesperson)
                    </option>
                <?php endforeach; ?>
                <?php foreach ($promoters as $promoter): ?>
                    <option value="promoter_<?php echo $promoter['id']; ?>">
                        <?php echo htmlspecialchars($promoter['name']); ?> (Promoter)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Percentage (%):</label>
            <input type="number" name="percentage[]" step="0.01" required>
        </div>
        
        <div class="form-group">
            <label>Notes:</label>
            <input type="text" name="notes[]">
        </div>
        
        <button type="button" class="btn btn-danger remove-investor">Remove</button>
    `;
    
    container.appendChild(newRow);
    
    // Dodaj event listener za gumb za uklanjanje
    newRow.querySelector('.remove-investor').addEventListener('click', function() {
        container.removeChild(newRow);
    });
});

// Dodaj event listenere za postojeće gumbe za uklanjanje
document.querySelectorAll('.remove-investor').forEach(button => {
    button.addEventListener('click', function() {
        this.closest('.investor-row').remove();
    });
});

// Funkcija za prikaz detalja izračuna
function showCalculation(id) {
    const modal = document.getElementById('calculationModal');
    const modalContent = document.getElementById('modalContent');
    
    // Dohvati podatke o izračunu
    fetch(`get_calculation.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            // Pripremi HTML za prikaz
            let html = `
                <h3>Calculation details</h3>
                <div class="summary">
                    <p><strong>Total investment:</strong> ${data.total_investment} €</p>
                    <p><strong>Gross profit:</strong> ${data.gross_profit} €</p>
                    <p><strong>Total expenses:</strong> ${data.total_expenses} €</p>
                    <p><strong>Total discounts:</strong> ${data.total_discounts} €</p>
                    <p><strong>Net profit:</strong> ${data.net_profit} €</p>
                </div>
                <h4>Investor distribution</h4>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Investor</th>
                            <th>Percentage</th>
                            <th>Investment</th>
                            <th>Profit</th>
                            <th>Expenses</th>
                            <th>Discounts</th>
                            <th>Payout</th>
                            <th>Future investment</th>
                            <th>Final payout</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            // Dodaj redove za investitore
            data.investors.forEach(investor => {
                html += `
                    <tr>
                        <td>${investor.name}</td>
                        <td>${investor.percentage}%</td>
                        <td>${investor.investment} €</td>
                        <td>${investor.profit_share} €</td>
                        <td>${investor.expenses_share} €</td>
                        <td>${investor.discounts_share} €</td>
                        <td>${investor.payout} €</td>
                        <td>${investor.future_investment_share} €</td>
                        <td>${investor.final_payout} €</td>
                        <td>${investor.notes || ''}</td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            modalContent.innerHTML = html;
            modal.style.display = "block";
        })
        .catch(error => {
            console.error('Greška pri dohvaćanju podataka:', error);
            alert('Došlo je do greške pri dohvaćanju podataka.');
        });
}

// Funkcija za brisanje izračuna
function deleteCalculation(id) {
    if (confirm('Jeste li sigurni da želite izbrisati ovaj izračun?')) {
        fetch(`delete_calculation.php?id=${id}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Osvježi stranicu
                location.reload();
            } else {
                alert('Došlo je do greške pri brisanju izračuna.');
            }
        })
        .catch(error => {
            console.error('Greška pri brisanju:', error);
            alert('Došlo je do greške pri brisanju izračuna.');
        });
    }
}

// Zatvori modal kada korisnik klikne na X
document.querySelector('.close').addEventListener('click', function() {
    document.getElementById('calculationModal').style.display = "none";
});

// Zatvori modal kada korisnik klikne izvan modalnog prozora
window.addEventListener('click', function(event) {
    const modal = document.getElementById('calculationModal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
});
</script>

<style>
.investor-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 1rem;
    background: #f5f5f5;
    border-radius: 4px;
}

.remove-investor {
    align-self: end;
    margin-bottom: 0.5rem;
}

.results-section {
    margin-top: 2rem;
    padding: 1.5rem;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.summary {
    margin-bottom: 2rem;
    padding: 1rem;
    background: #f5f5f5;
    border-radius: 4px;
}

.summary p {
    margin: 0.5rem 0;
    font-size: 1.1rem;
}

/* Stilovi za modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 2% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 95%;
    max-width: 1400px;
    border-radius: 5px;
    position: relative;
}

/* Stilovi za responzivnu tablicu u modalu */
.modal .admin-table {
    width: 100%;
    font-size: 0.9em;
    white-space: nowrap;
    display: block;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.modal .admin-table th,
.modal .admin-table td {
    padding: 6px 8px;
    min-width: 80px;
}

.modal .admin-table th:first-child,
.modal .admin-table td:first-child {
    position: sticky;
    left: 0;
    background: #fff;
    z-index: 1;
    border-right: 2px solid #ddd;
}

.modal .summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.modal .summary p {
    margin: 0;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    position: absolute;
    right: 20px;
    top: 10px;
}

.close:hover,
.close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}

/* Stilovi za gumb za brisanje */
.btn-danger {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
    margin-left: 5px;
}

.btn-danger:hover {
    background-color: #c82333;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
    margin-left: 5px;
    text-decoration: none;
    display: inline-block;
}

.btn-info:hover {
    background-color: #138496;
    color: white;
    text-decoration: none;
}
</style>

<?php require_once '../includes/footer.php'; ?>
</body>
</html> 