<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Provjeri je li odabrana turneja
if (!isset($_SESSION['current_tour_id'])) {
    $_SESSION['message'] = "Molimo odaberite turneju.";
    $_SESSION['message_type'] = "warning";
    header('Location: index.php');
    exit;
}

// Provjeri je li tura arhivirana
$stmt = $pdo->prepare("SELECT archived FROM tours WHERE id = ?");
$stmt->execute([$_SESSION['current_tour_id']]);
$tour = $stmt->fetch();

if ($tour && $tour['archived']) {
    $_SESSION['message'] = 'Ova tura je arhivirana. Možete samo pregledavati podatke.';
    $_SESSION['message_type'] = 'warning';
}

// Dohvati trenutnu turu
$stmt = $pdo->prepare("SELECT * FROM tours WHERE id = ?");
$stmt->execute([$_SESSION['current_tour_id']]);
$currentTour = $stmt->fetch();

if (!$currentTour) {
    $_SESSION['message'] = "Odabrana turneja ne postoji.";
    $_SESSION['message_type'] = "error";
    header('Location: index.php');
    exit;
}

// Handle waiting list actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    $salesperson_id = $_POST['salesperson_id'];
                    $customer_name = $_POST['customer_name'];
                    $product_id = $_POST['product_id'];
                    $quantity = $_POST['quantity'];
                    $reason = $_POST['reason'];

                    $stmt = $pdo->prepare("
                        INSERT INTO waiting_list (tour_id, salesperson_id, customer_name, product_id, quantity, reason) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$_SESSION['current_tour_id'], $salesperson_id, $customer_name, $product_id, $quantity, $reason]);
                    
                    $_SESSION['message'] = 'Osoba je uspješno dodana na listu čekanja.';
                    $_SESSION['message_type'] = 'success';
                    header('Location: waiting_list.php');
                    exit;
                    break;
                
                case 'update':
                    $id = $_POST['id'];
                    $salesperson_id = $_POST['salesperson_id'];
                    $customer_name = $_POST['customer_name'];
                    $product_id = $_POST['product_id'];
                    $quantity = $_POST['quantity'];
                    $reason = $_POST['reason'];
                    
                    $stmt = $pdo->prepare("
                        UPDATE waiting_list 
                        SET salesperson_id = ?, customer_name = ?, product_id = ?, quantity = ?, reason = ? 
                        WHERE id = ? AND tour_id = ?
                    ");
                    $stmt->execute([$salesperson_id, $customer_name, $product_id, $quantity, $reason, $id, $_SESSION['current_tour_id']]);
                    
                    $_SESSION['message'] = 'Podaci su uspješno ažurirani.';
                    $_SESSION['message_type'] = 'success';
                    header('Location: waiting_list.php');
                    exit;
                    break;
                    
                case 'delete':
                    $id = $_POST['id'];
                    
                    $stmt = $pdo->prepare("DELETE FROM waiting_list WHERE id = ? AND tour_id = ?");
                    $stmt->execute([$id, $_SESSION['current_tour_id']]);
                    
                    $_SESSION['message'] = 'Osoba je uklonjena s liste čekanja.';
                    $_SESSION['message_type'] = 'success';
                    header('Location: waiting_list.php');
                    exit;
                    break;
            }
        }
    } catch (PDOException $e) {
        error_log("Error in waiting_list.php: " . $e->getMessage());
        $_SESSION['message'] = "Greška pri obradi zahtjeva.";
        $_SESSION['message_type'] = "error";
    }
}

// Get all waiting list entries for current tour with product details
$stmt = $pdo->prepare("
    SELECT wl.*, p.name as product_name, p.stock_quantity as product_stock, sp.name as salesperson_name 
    FROM waiting_list wl 
    LEFT JOIN products p ON wl.product_id = p.id 
    LEFT JOIN salespeople sp ON wl.salesperson_id = sp.id 
    WHERE wl.tour_id = ? 
    ORDER BY wl.created_at DESC
");
$stmt->execute([$_SESSION['current_tour_id']]);
$waitingList = $stmt->fetchAll();

// Calculate total quantities
$totalQuantity = 0;
$productQuantities = [];
foreach ($waitingList as $entry) {
    $totalQuantity += $entry['quantity'];
    if (!isset($productQuantities[$entry['product_id']])) {
        $productQuantities[$entry['product_id']] = 0;
    }
    $productQuantities[$entry['product_id']] += $entry['quantity'];
}

// Get all active salespeople
$stmt = $pdo->prepare("SELECT * FROM salespeople WHERE active = 1 ORDER BY name");
$stmt->execute();
$salespeople = $stmt->fetchAll();

// Get all products
$stmt = $pdo->prepare("
    SELECT p.* 
    FROM products p 
    JOIN tour_products tp ON p.id = tp.product_id 
    WHERE tp.tour_id = ? 
    ORDER BY p.name
");
$stmt->execute([$_SESSION['current_tour_id']]);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista čekanja - Sustav Praćenja Prodaje</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <link rel="stylesheet" href="assets/css/admin.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php
require_once 'includes/header.php';

?>

<div class="container standard-page">
    <h2>Lista Čekanja - <?php echo htmlspecialchars($currentTour['name']); ?></h2>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message <?php echo $_SESSION['message_type']; ?>">
            <?php 
            echo htmlspecialchars($_SESSION['message']);
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Total quantities display -->
    <div class="total-quantities">
        <h3>Ukupne količine</h3>
        <table class="table">
            <tbody>
                <tr>
                    <td>Ukupno na čekanju:</td>
                    <td class="text-right"><strong><?php echo $totalQuantity; ?></strong> komada</td>
                </tr>
                <?php foreach ($productQuantities as $productId => $quantity): ?>
                    <?php 
                    $product = array_filter($products, function($p) use ($productId) { return $p['id'] == $productId; });
                    $product = reset($product);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name']); ?>:</td>
                        <td class="text-right"><strong><?php echo $quantity; ?></strong> komada</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Forma za novu osobu na listi čekanja -->
    <div class="form-section">
        <h3>Nova Osoba na Listi Čekanja</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label for="salesperson_id">Prodavač:</label>
                <select id="salesperson_id" name="salesperson_id" required>
                    <option value="">Odaberi prodavača</option>
                    <?php foreach ($salespeople as $salesperson): ?>
                        <option value="<?php echo $salesperson['id']; ?>">
                            <?php echo htmlspecialchars($salesperson['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="customer_name">Kupac:</label>
                <input type="text" id="customer_name" name="customer_name" required>
            </div>
            <div class="form-group">
                <label for="product_id">Proizvod:</label>
                <select id="product_id" name="product_id" required>
                    <option value="">Odaberi proizvod</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>" data-stock="<?php echo $product['stock_quantity']; ?>">
                            <?php echo htmlspecialchars($product['name']); ?> (Zaliha: <?php echo $product['stock_quantity']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="quantity">Količina:</label>
                <input type="number" id="quantity" name="quantity" min="1" required>
            </div>
            <div class="form-group">
                <label for="reason">Razlog:</label>
                <textarea id="reason" name="reason" rows="3" required></textarea>
            </div>
            <button type="submit">Dodaj na Listu Čekanja</button>
        </form>
    </div>

    <!-- Lista čekanja -->
    <div class="list-section">
        <h3>Lista Čekanja</h3>
        <div class="filter-section">
            <div class="form-group">
                <label for="product_filter">Filtriraj po proizvodu:</label>
                <select id="product_filter" onchange="filterTable()">
                    <option value="">Svi proizvodi</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-container">
                <div class="form-group">
                    <label for="customer_search">Pretraži kupce:</label>
                    <input type="text" id="customer_search" onkeyup="filterTable()" placeholder="Unesi ime kupca...">
                </div>
                <div class="form-group">
                    <label for="seller_search">Pretraži prodavače:</label>
                    <input type="text" id="seller_search" onkeyup="filterTable()" placeholder="Unesi ime prodavača...">
                </div>
            </div>
        </div>
        <table class="table" id="waiting_list_table">
            <thead>
                <tr>
                    <th>Prodavač</th>
                    <th>Kupac</th>
                    <th>Proizvod</th>
                    <th>Količina</th>
                    <th>Razlog</th>
                    <th>Datum</th>
                    <th>Akcije</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($waitingList as $entry): ?>
                    <tr data-product-id="<?php echo $entry['product_id']; ?>">
                        <td><?php echo htmlspecialchars($entry['salesperson_name']); ?></td>
                        <td><?php echo htmlspecialchars($entry['customer_name']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($entry['product_name']); ?>
                            <br>
                            <small>Zaliha: <?php echo $entry['product_stock']; ?></small>
                        </td>
                        <td><?php echo $entry['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($entry['reason']); ?></td>
                        <td><?php echo date('d.m.Y. H:i', strtotime($entry['created_at'])); ?></td>
                        <td>
                            <button class="btn-edit" onclick="editEntry(<?php echo $entry['id']; ?>)">Uredi</button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $entry['id']; ?>">
                                <button type="submit" class="btn-delete" onclick="return confirm('Jeste li sigurni?')">Obriši</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal za uređivanje -->
<div id="editModal" class="modal">
    <div class="modal-content" style="max-height: 90vh; overflow-y: auto;">
        <span class="close">&times;</span>
        <h3>Uredi Unos</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label for="edit_salesperson_id">Prodavač:</label>
                <select id="edit_salesperson_id" name="salesperson_id" required>
                    <option value="">Odaberi prodavača</option>
                    <?php foreach ($salespeople as $salesperson): ?>
                        <option value="<?php echo $salesperson['id']; ?>">
                            <?php echo htmlspecialchars($salesperson['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_customer_name">Kupac:</label>
                <input type="text" id="edit_customer_name" name="customer_name" required>
            </div>
            <div class="form-group">
                <label for="edit_product_id">Proizvod:</label>
                <select id="edit_product_id" name="product_id" required>
                    <option value="">Odaberi proizvod</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_quantity">Količina:</label>
                <input type="number" id="edit_quantity" name="quantity" min="1" required>
            </div>
            <div class="form-group">
                <label for="edit_reason">Razlog:</label>
                <textarea id="edit_reason" name="reason" rows="3" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Spremi Promjene</button>
        </form>
    </div>
</div>

<script>
function filterTable() {
    const productFilter = document.getElementById('product_filter').value;
    const customerSearch = document.getElementById('customer_search').value.toLowerCase();
    const sellerSearch = document.getElementById('seller_search').value.toLowerCase();
    const rows = document.querySelectorAll('#waiting_list_table tbody tr');
    
    rows.forEach(row => {
        const productId = row.getAttribute('data-product-id');
        const customerName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const sellerName = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
        
        const productMatch = productFilter === '' || productId === productFilter;
        const customerMatch = customerName.includes(customerSearch);
        const sellerMatch = sellerName.includes(sellerSearch);
        
        if (productMatch && customerMatch && sellerMatch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Update stock info when product is selected
document.getElementById('product_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const stock = selectedOption.getAttribute('data-stock');
    const quantityInput = document.getElementById('quantity');
    quantityInput.max = stock;
});

function editEntry(id) {
    const modal = document.getElementById('editModal');
    const entry = <?php echo json_encode($waitingList); ?>.find(e => e.id === id);
    
    document.getElementById('edit_id').value = entry.id;
    document.getElementById('edit_salesperson_id').value = entry.salesperson_id;
    document.getElementById('edit_customer_name').value = entry.customer_name;
    document.getElementById('edit_product_id').value = entry.product_id;
    document.getElementById('edit_quantity').value = entry.quantity;
    document.getElementById('edit_reason').value = entry.reason;
    
    modal.style.display = 'block';
}

// Close modal when clicking the X
document.querySelector('.close').onclick = function() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?> 