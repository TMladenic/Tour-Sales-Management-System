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
    $_SESSION['message'] = "Please select a tour.";
    $_SESSION['message_type'] = "warning";
    header('Location: index.php');
    exit;
}

// Clear the tour selection message if it exists
if (isset($_SESSION['message']) && $_SESSION['message'] === "Please select a tour.") {
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Provjeri je li tura arhivirana
$stmt = $pdo->prepare("SELECT archived FROM tours WHERE id = ?");
$stmt->execute([$_SESSION['current_tour_id']]);
$tour = $stmt->fetch();

if ($tour && $tour['archived']) {
    $_SESSION['message'] = 'This tour is archived. You can only view data.';
    $_SESSION['message_type'] = 'warning';
    header('Location: index.php');
    exit;
}

// Dohvati trenutnu turu
$stmt = $pdo->prepare("SELECT * FROM tours WHERE id = ?");
$stmt->execute([$_SESSION['current_tour_id']]);
$currentTour = $stmt->fetch();

if (!$currentTour) {
    $_SESSION['message'] = "Odabrana tura ne postoji.";
    $_SESSION['message_type'] = "error";
    header('Location: index.php');
    exit;
}

// Dohvati sve proizvode
$stmt = $pdo->prepare("SELECT * FROM products ORDER BY name");
$stmt->execute();
$products = $stmt->fetchAll();

// Dohvati sve aktivne prodavače
$stmt = $pdo->prepare("SELECT * FROM salespeople WHERE active = 1 ORDER BY name");
$stmt->execute();
$salespeople = $stmt->fetchAll();

// Dohvati sve kategorije troškova
$stmt = $pdo->prepare("SELECT * FROM expense_categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Dohvati sve prodaje za trenutni tur
if (!isset($_SESSION['current_tour_id'])) {
    $sales = [];
} else {
    $stmt = $pdo->prepare("
        SELECT s.*, p.name as product_name, sp.name as salesperson_name 
        FROM sales s 
        JOIN products p ON s.product_id = p.id 
        JOIN salespeople sp ON s.salesperson_id = sp.id 
        WHERE s.tour_id = ? 
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$_SESSION['current_tour_id']]);
    $sales = $stmt->fetchAll();
}

// Dohvati sve troškove za trenutni tur
if (!isset($_SESSION['current_tour_id'])) {
    $expenses = [];
} else {
    $stmt = $pdo->prepare("
        SELECT e.*, ec.name as category_name 
        FROM expenses e 
        JOIN expense_categories ec ON e.category_id = ec.id 
        WHERE e.tour_id = ? 
        ORDER BY e.created_at DESC
    ");
    $stmt->execute([$_SESSION['current_tour_id']]);
    $expenses = $stmt->fetchAll();
}

// Izračunaj ukupnu prodaju
$totalSales = 0;
foreach ($sales as $sale) {
    $totalSales += ($sale['price'] * $sale['quantity']) - $sale['discount'];
}

// Izračunaj ukupne troškove
$totalExpenses = 0;
foreach ($expenses as $expense) {
    $totalExpenses += $expense['amount'];
}

// Izračunaj profit
$profit = $totalSales - $totalExpenses;

// Handle sales actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create') {
                $stmt = $pdo->prepare("INSERT INTO sales (
                    tour_id, 
                    salesperson_id, 
                    product_id, 
                    customer_name, 
                    customer_address, 
                    instagram_handle, 
                    delivery_method, 
                    package_email, 
                    package_number, 
                    package_location, 
                    quantity, 
                    price, 
                    discount, 
                    discount_type, 
                    discount_reason, 
                    notes
                ) VALUES (
                    :tour_id, 
                    :salesperson_id, 
                    :product_id, 
                    :customer_name, 
                    :customer_address, 
                    :instagram_handle, 
                    :delivery_method, 
                    :package_email, 
                    :package_number, 
                    :package_location, 
                    :quantity, 
                    :price, 
                    :discount, 
                    :discount_type, 
                    :discount_reason, 
                    :notes
                )");
                
                $stmt->execute([
                    'tour_id' => $_POST['tour_id'],
                    'salesperson_id' => $_POST['salesperson_id'],
                    'product_id' => $_POST['product_id'],
                    'customer_name' => $_POST['customer_name'],
                    'customer_address' => $_POST['customer_address'],
                    'instagram_handle' => $_POST['instagram_handle'],
                    'delivery_method' => $_POST['delivery_method'],
                    'package_email' => $_POST['package_email'] ?? null,
                    'package_number' => $_POST['package_number'] ?? null,
                    'package_location' => $_POST['package_location'] ?? null,
                    'quantity' => $_POST['quantity'],
                    'price' => $_POST['price'],
                    'discount' => $_POST['discount'],
                    'discount_type' => $_POST['discount_type'],
                    'discount_reason' => $_POST['discount_reason'],
                    'notes' => $_POST['notes']
                ]);
                
                // Ažuriraj količinu na skladištu
                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :product_id");
                $stmt->execute([
                    'quantity' => $_POST['quantity'],
                    'product_id' => $_POST['product_id']
                ]);
                
                $_SESSION['message'] = "Sale has been successfully recorded.";
                $_SESSION['message_type'] = 'success';
                header("Location: sales.php");
                exit;
            } elseif ($_POST['action'] === 'update') {
                $id = $_POST['id'];
                $product_id = $_POST['product_id'];
                $salesperson_id = $_POST['salesperson_id'];
                $quantity = $_POST['quantity'];
                $price = $_POST['price'];
                $customer_name = $_POST['customer_name'];
                $customer_address = $_POST['customer_address'];
                $instagram_handle = $_POST['instagram_handle'];
                $delivery_method = $_POST['delivery_method'];
                $package_email = $_POST['delivery_method'] === 'package' ? $_POST['package_email'] : null;
                $package_number = $_POST['delivery_method'] === 'package' ? $_POST['package_number'] : null;
                $package_location = $_POST['delivery_method'] === 'package' ? $_POST['package_location'] : null;
                $discount = $_POST['discount'];
                $discount_type = $_POST['discount_type'];
                $discount_reason = $_POST['discount_reason'];
                $notes = $_POST['notes'];
                
                $stmt = $pdo->prepare("
                    UPDATE sales 
                    SET product_id = ?, salesperson_id = ?, quantity = ?, 
                        price = ?, customer_name = ?, customer_address = ?, 
                        instagram_handle = ?, delivery_method = ?, package_email = ?, 
                        package_number = ?, package_location = ?, discount = ?, discount_type = ?, 
                        discount_reason = ?, notes = ? 
                    WHERE id = ? AND tour_id = ?
                ");
                $stmt->execute([
                    $product_id, $salesperson_id, $quantity, $price, 
                    $customer_name, $customer_address, $instagram_handle, 
                    $delivery_method, $package_email, $package_number, 
                    $package_location, $discount, $discount_type, $discount_reason, 
                    $notes, $id, $_SESSION['current_tour_id']
                ]);
                
                $_SESSION['message'] = 'Sale data has been successfully updated.';
                $_SESSION['message_type'] = 'success';
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                
                $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ? AND tour_id = ?");
                $stmt->execute([$id, $_SESSION['current_tour_id']]);
                
                $_SESSION['message'] = 'Prodaja je uklonjena.';
                $_SESSION['message_type'] = 'success';
                header('Location: sales.php');
                exit;
            }
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
        header('Location: sales.php');
        exit;
    }
}

// Get all products for dropdown
if (!isset($_SESSION['current_tour_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM products ORDER BY name");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, tp.quantity as tour_quantity, tp.price as tour_price
        FROM products p
        INNER JOIN tour_products tp ON p.id = tp.product_id AND tp.tour_id = ?
        ORDER BY p.name
    ");
    $stmt->execute([$_SESSION['current_tour_id']]);
}
$products = $stmt->fetchAll();

// Get all active salespeople for dropdown
$stmt = $pdo->prepare("SELECT * FROM salespeople WHERE active = 1 ORDER BY name");
$stmt->execute();
$salespeople = $stmt->fetchAll();

// Get all sales for current tour with product and salesperson details
if (!isset($_SESSION['current_tour_id'])) {
    $sales = [];
} else {
    $stmt = $pdo->prepare("
        SELECT s.*, p.name as product_name, sp.name as salesperson_name 
        FROM sales s 
        LEFT JOIN products p ON s.product_id = p.id 
        LEFT JOIN salespeople sp ON s.salesperson_id = sp.id 
        WHERE s.tour_id = ? 
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$_SESSION['current_tour_id']]);
    $sales = $stmt->fetchAll();
}

// Izračunaj ukupnu prodaju
$totalSales = 0;
foreach ($sales as $sale) {
    $price = $sale['price'];
    $quantity = $sale['quantity'];
    $discount = $sale['discount'];
    $discountType = $sale['discount_type'];
    
    if ($discountType === 'percentage') {
        $price = $price * (1 - ($discount / 100));
    } else {
        $price = $price - $discount;
    }
    
    $totalSales += $price * $quantity;
}

// Dohvati sve promotere
$stmt = $pdo->query("SELECT * FROM promoters ORDER BY name");
$promoters = $stmt->fetchAll();

// Dohvati prodaju promotera za odabranu turu
if (!isset($_SESSION['current_tour_id'])) {
    $promoter_sales = [];
} else {
    $stmt = $pdo->prepare("
        SELECT ps.*, p.name as promoter_name, p.coefficient
        FROM promoter_sales ps
        JOIN promoters p ON ps.promoter_id = p.id
        WHERE ps.tour_id = ?
        ORDER BY ps.created_at DESC
    ");
    $stmt->execute([$_SESSION['current_tour_id']]);
    $promoter_sales = $stmt->fetchAll();
}

require_once 'includes/header.php';
?>

<div class="container standard-page">
    <h2>Sales</h2>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message <?php echo $_SESSION['message_type']; ?>">
            <?php 
            echo $_SESSION['message'];
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Forma za prodaju -->
    <div class="form-section">
        <h3>New Sale</h3>
        <form id="salesForm" class="form" method="POST">
            <input type="hidden" name="action" value="create">
            <?php if (isset($_SESSION['current_tour_id'])): ?>
                <input type="hidden" name="tour_id" value="<?php echo $_SESSION['current_tour_id']; ?>">
            <?php endif; ?>
            <div class="form-group">
                <label for="salesperson_id">Salesperson:</label>
                <select id="salesperson_id" name="salesperson_id" required>
                    <option value="">Select salesperson</option>
                    <?php foreach ($salespeople as $salesperson): ?>
                        <option value="<?php echo $salesperson['id']; ?>">
                            <?php echo htmlspecialchars($salesperson['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="product_id">Product:</label>
                <select id="product_id" name="product_id" required>
                    <option value="">Select product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>" 
                                data-price="<?php echo $product['price']; ?>"
                                data-stock="<?php echo $product['stock_quantity']; ?>">
                            <?php echo htmlspecialchars($product['name']); ?>
                            (<?php echo number_format($product['price'], 2); ?> €) 
                            - Stock: <?php echo $product['stock_quantity']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="stock_info" class="stock-info"></div>
            </div>
            <div class="form-group">
                <label for="quantity">Quantity:</label>
                <input type="number" id="quantity" name="quantity" min="1" required>
            </div>
            <div class="form-group">
                <label for="price">Price (€):</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="customer_name">Customer Name:</label>
                <input type="text" id="customer_name" name="customer_name" required>
            </div>
            <div class="form-group">
                <label for="customer_address">Customer Address:</label>
                <textarea id="customer_address" name="customer_address" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label for="instagram_handle">Instagram Handle:</label>
                <input type="text" id="instagram_handle" name="instagram_handle" placeholder="@username">
            </div>
            <div class="form-group">
                <label for="delivery_method">Delivery Method:</label>
                <select id="delivery_method" name="delivery_method" required onchange="toggleDeliveryDetails()">
                    <option value="live">Live Delivery</option>
                    <option value="package">Package Machine</option>
                </select>
            </div>
            <div id="package_details" style="display: none;">
                <div class="form-group">
                    <label for="package_email">Package Machine Email:</label>
                    <input type="email" id="package_email" name="package_email">
                </div>
                <div class="form-group">
                    <label for="package_number">Package Machine Number:</label>
                    <input type="text" id="package_number" name="package_number">
                </div>
                <div class="form-group">
                    <label for="package_location">Package Machine Location:</label>
                    <input type="text" id="package_location" name="package_location" placeholder="e.g. GLS Center Zagreb">
                </div>
            </div>
            <div class="form-group">
                <label>Discount:</label>
                <div class="discount-input-group">
                    <input type="number" id="discount" name="discount" step="0.01" min="0" value="0">
                    <select id="discount_type" name="discount_type">
                        <option value="fixed">€</option>
                        <option value="percentage">%</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="discount_reason">Discount Reason:</label>
                <input type="text" id="discount_reason" name="discount_reason">
            </div>
            <div class="form-group">
                <label for="notes">Notes:</label>
                <textarea id="notes" name="notes"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Record Sale</button>
        </form>
    </div>

    <!-- Forma za prodaju promotera -->
    <div class="form-section">
        <h3>Promoter Sale</h3>
        <form method="POST" action="add_promoter_sale.php">
            <input type="hidden" name="tour_id" value="<?php echo $_SESSION['current_tour_id']; ?>">
            <div class="form-group">
                <label for="promoter_id">Promoter:</label>
                <select id="promoter_id" name="promoter_id" required>
                    <option value="">Select promoter</option>
                    <?php foreach ($promoters as $promoter): ?>
                        <option value="<?php echo $promoter['id']; ?>" data-coefficient="<?php echo $promoter['coefficient']; ?>">
                            <?php echo htmlspecialchars($promoter['name']); ?> (<?php echo $promoter['coefficient']; ?> €)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="promoter_quantity">Quantity:</label>
                <input type="number" id="promoter_quantity" name="promoter_quantity" min="1" required>
            </div>
            <div class="form-group">
                <label>Estimated Earnings:</label>
                <div id="estimated_earnings">0.00 €</div>
            </div>
            <button type="submit" class="btn btn-primary">Add Sale</button>
        </form>
    </div>

    <!-- Lista prodaja promotera -->
    <div class="list-section">
        <h3>Promoter Sales</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Promoter</th>
                    <th>Quantity</th>
                    <th>Coefficient</th>
                    <th>Earnings</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($promoter_sales)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No promoter sales</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($promoter_sales as $sale): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sale['promoter_name']); ?></td>
                            <td><?php echo $sale['quantity']; ?></td>
                            <td><?php echo $sale['coefficient']; ?> €</td>
                            <td><?php echo number_format($sale['quantity'] * $sale['coefficient'], 2); ?> €</td>
                            <td><?php echo date('d.m.Y. H:i', strtotime($sale['created_at'])); ?></td>
                            <td>
                                <button onclick="deletePromoterSale(<?php echo $sale['id']; ?>)" class="btn btn-danger">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Lista prodaja -->
    <div class="list-section">
        <h3>Sales List</h3>
        <div class="export-buttons">
            <a href="generate_sales_pdf.php?tour_id=<?php echo $_SESSION['current_tour_id']; ?>" class="btn btn-primary">
                <i class="fas fa-file-pdf"></i> Download PDF
            </a>
            <a href="generate_sales_printer_pdf.php?tour_id=<?php echo $_SESSION['current_tour_id']; ?>" class="btn btn-secondary">
                <i class="fas fa-print"></i> Printer PDF
            </a>
        </div>
        <div class="total-amount">
            Total Sales: <?php echo number_format($totalSales, 2); ?> €
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Salesperson</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Discount</th>
                    <th>Total</th>
                    <th>Customer</th>
                    <th>Instagram</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td data-label="Date"><?php echo date('d.m.Y. H:i', strtotime($sale['created_at'])); ?></td>
                        <td data-label="Salesperson"><?php echo htmlspecialchars($sale['salesperson_name']); ?></td>
                        <td data-label="Quantity"><?php echo $sale['quantity']; ?></td>
                        <td data-label="Price"><?php echo number_format($sale['price'], 2); ?> €</td>
                        <td data-label="Discount">
                            <?php if ($sale['discount'] > 0): ?>
                                <?php echo $sale['discount_type'] === 'percentage' ? $sale['discount'] . '%' : number_format($sale['discount'], 2) . ' €'; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td data-label="Total">
                            <?php
                            $price = $sale['price'];
                            if ($sale['discount'] > 0) {
                                if ($sale['discount_type'] === 'percentage') {
                                    $price = $price * (1 - ($sale['discount'] / 100));
                                } else {
                                    $price = $price - $sale['discount'];
                                }
                            }
                            echo number_format($price * $sale['quantity'], 2);
                            ?> €
                        </td>
                        <td data-label="Customer"><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                        <td data-label="Instagram"><?php echo htmlspecialchars($sale['instagram_handle']); ?></td>
                        <td data-label="Notes"><?php echo htmlspecialchars($sale['notes']); ?></td>
                        <td data-label="Actions" class="action-buttons">
                            <button onclick="deleteSale(<?php echo $sale['id']; ?>)" class="btn btn-danger">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Sale</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label for="edit_salesperson_id">Salesperson:</label>
                <select id="edit_salesperson_id" name="salesperson_id" required>
                    <option value="">Select salesperson</option>
                    <?php foreach ($salespeople as $salesperson): ?>
                        <option value="<?php echo $salesperson['id']; ?>">
                            <?php echo htmlspecialchars($salesperson['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_product_id">Product:</label>
                <select id="edit_product_id" name="product_id" required>
                    <option value="">Select product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>" 
                                data-price="<?php echo $product['price']; ?>"
                                data-stock="<?php echo $product['stock_quantity']; ?>">
                            <?php echo htmlspecialchars($product['name']); ?> 
                            (<?php echo number_format($product['price'], 2); ?> €) 
                            - Stock: <?php echo $product['stock_quantity']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_quantity">Quantity:</label>
                <input type="number" id="edit_quantity" name="quantity" min="1" required>
            </div>
            <div class="form-group">
                <label for="edit_price">Price (€):</label>
                <input type="number" id="edit_price" name="price" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="edit_discount">Discount (%):</label>
                <input type="number" id="edit_discount" name="discount" min="0" max="100" value="0">
            </div>
            <button type="submit">Save Changes</button>
        </form>
    </div>
</div>

<script>
function updateDiscountLabel() {
    const discountType = document.getElementById('discount_type').value;
    const discountInput = document.getElementById('discount');
    const discountLabel = document.querySelector('label[for="discount"]');
    
    if (discountType === 'percentage') {
        discountLabel.textContent = 'Discount (%):';
        discountInput.step = '0.01';
        discountInput.max = '100';
    } else {
        discountLabel.textContent = 'Discount (€):';
        discountInput.step = '0.01';
        discountInput.max = '';
    }
}

// Check discount reason before submitting form
document.querySelector('form').addEventListener('submit', function(e) {
    const discount = parseFloat(document.getElementById('discount').value);
    const discountReason = document.getElementById('discount_reason').value.trim();
    
    if (discount > 0 && !discountReason) {
        e.preventDefault();
        alert('Please enter a discount reason.');
        document.getElementById('discount_reason').focus();
    }
});

// Update discount display in table
function formatDiscount(discount, type) {
    if (type === 'percentage') {
        return discount + '%';
    } else {
        return '€' + parseFloat(discount).toFixed(2);
    }
}

function toggleDeliveryDetails() {
    const deliveryMethod = document.getElementById('delivery_method').value;
    const packageDetails = document.getElementById('package_details');
    const packageEmail = document.getElementById('package_email');
    const packageNumber = document.getElementById('package_number');
    const packageLocation = document.getElementById('package_location');
    
    if (deliveryMethod === 'package') {
        packageDetails.style.display = 'block';
        packageEmail.required = true;
        packageNumber.required = true;
        packageLocation.required = true;
    } else {
        packageDetails.style.display = 'none';
        packageEmail.required = false;
        packageNumber.required = false;
        packageLocation.required = false;
    }
}

// Update JavaScript for editing
function editSale(id) {
    const modal = document.getElementById('editModal');
    const sale = <?php echo json_encode($sales); ?>.find(s => s.id === id);
    
    document.getElementById('edit_id').value = sale.id;
    document.getElementById('edit_salesperson_id').value = sale.salesperson_id;
    document.getElementById('edit_product_id').value = sale.product_id;
    document.getElementById('edit_quantity').value = sale.quantity;
    document.getElementById('edit_price').value = sale.price;
    document.getElementById('edit_discount').value = sale.discount;
    
    toggleDeliveryDetails();
    updateDiscountLabel();
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

// Function to calculate estimated promoter earnings
function calculatePromoterEarnings() {
    const promoterSelect = document.getElementById('promoter_id');
    const quantity = document.getElementById('promoter_quantity').value;
    const coefficient = promoterSelect.options[promoterSelect.selectedIndex].dataset.coefficient || 0;
    const earnings = quantity * coefficient;
    document.getElementById('estimated_earnings').textContent = earnings.toFixed(2) + ' €';
}

// Update price and available quantity display when product changes
document.getElementById('product_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const price = selectedOption.dataset.price;
    const stock = selectedOption.dataset.stock;
    
    document.getElementById('price').value = price;
    document.getElementById('stock_info').textContent = `Available: ${stock} units`;
    
    // Update maximum quantity in input field
    document.getElementById('quantity').max = stock;
});

// Function to delete sale
function deleteSale(id) {
    if (confirm('Are you sure you want to delete this sale?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Function to delete promoter sale
function deletePromoterSale(id) {
    if (confirm('Are you sure you want to delete this promoter sale?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete_promoter_sale.php';
        form.innerHTML = `<input type="hidden" name="id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<style>
.discount-input-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.discount-input-group input {
    width: 80px;
}

.discount-input-group select {
    width: 100px;
}
</style>

<?php require_once 'includes/footer.php'; ?> 