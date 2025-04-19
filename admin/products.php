<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Handle product creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create') {
                $name = $_POST['name'];
                $description = $_POST['description'];
                $price = $_POST['price'];
                $stock_quantity = $_POST['stock_quantity'];
                $tour_id = $_POST['tour_id'];

                $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock_quantity) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $description, $price, $stock_quantity]);
                
                // Update or add tour connection
                if ($tour_id) {
                    $stmt = $pdo->prepare("INSERT INTO tour_products (tour_id, product_id, quantity, price) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = ?, quantity = ?");
                    $stmt->execute([$tour_id, $pdo->lastInsertId(), $stock_quantity, $price, $price, $stock_quantity]);
                }
                
                $_SESSION['message'] = 'Product has been successfully created.';
                $_SESSION['message_type'] = 'success';
            } elseif ($_POST['action'] === 'update') {
                $id = $_POST['id'];
                $name = $_POST['name'];
                $description = $_POST['description'];
                $price = $_POST['price'];
                $stock_quantity = $_POST['stock_quantity'];
                $tour_id = $_POST['tour_id'];
                
                $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock_quantity = ? WHERE id = ?");
                $stmt->execute([$name, $description, $price, $stock_quantity, $id]);
                
                // Update or add tour connection
                if ($tour_id) {
                    // First check if connection exists
                    $stmt = $pdo->prepare("SELECT id FROM tour_products WHERE product_id = ? AND tour_id = ?");
                    $stmt->execute([$id, $tour_id]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // Update existing connection
                        $stmt = $pdo->prepare("UPDATE tour_products SET quantity = ?, price = ? WHERE product_id = ? AND tour_id = ?");
                        $stmt->execute([$stock_quantity, $price, $id, $tour_id]);
                    } else {
                        // Add new connection
                        $stmt = $pdo->prepare("INSERT INTO tour_products (tour_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$tour_id, $id, $stock_quantity, $price]);
                    }
                } else {
                    // If no tour is selected, delete all tour connections
                    $stmt = $pdo->prepare("DELETE FROM tour_products WHERE product_id = ?");
                    $stmt->execute([$id]);
                }
                
                $_SESSION['message'] = 'Product has been successfully updated.';
                $_SESSION['message_type'] = 'success';
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id]);
                
                $_SESSION['message'] = 'Product has been successfully deleted.';
                $_SESSION['message_type'] = 'success';
            }
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = 'An error occurred: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: products.php' . (isset($_GET['tour_id']) ? '?tour_id=' . $_GET['tour_id'] : ''));
    exit;
}

require_once '../includes/header.php';

// Get all products with tour information
$stmt = $pdo->prepare("
    SELECT p.*, tp.tour_id, tp.quantity as tour_quantity, GROUP_CONCAT(DISTINCT CONCAT(t.name, ' (', tp.quantity, ' pcs)') SEPARATOR ', ') as tours
    FROM products p
    LEFT JOIN tour_products tp ON p.id = tp.product_id
    LEFT JOIN tours t ON tp.tour_id = t.id
    GROUP BY p.id
    ORDER BY p.name
");
$stmt->execute();
$products = $stmt->fetchAll();

// Get all tours
$stmt = $pdo->query("SELECT * FROM tours WHERE status = 'active' ORDER BY name");
$tours = $stmt->fetchAll();

// Get tour products if tour is selected
$tourProducts = [];
if (isset($_GET['tour_id'])) {
    $stmt = $pdo->prepare("
        SELECT tp.*, p.name as product_name, p.description as product_description, p.stock_quantity
        FROM tour_products tp
        JOIN products p ON tp.product_id = p.id
        WHERE tp.tour_id = ?
        ORDER BY p.name
    ");
    $stmt->execute([$_GET['tour_id']]);
    $tourProducts = $stmt->fetchAll();
}
?>

<div class="admin-container">
    <h2>Product Management</h2>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message <?php echo $_SESSION['message_type']; ?>">
            <?php 
            echo $_SESSION['message'];
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Form for new product -->
    <div class="form-section">
        <h3>New Product</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label for="name">Product Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label for="price">Price (€):</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="stock_quantity">Initial Stock:</label>
                <input type="number" id="stock_quantity" name="stock_quantity" min="0" required>
            </div>
            <div class="form-group">
                <label for="tour_id">Tour:</label>
                <select name="tour_id" id="tour_id">
                    <option value="">Select tour...</option>
                    <?php foreach ($tours as $tour): ?>
                        <option value="<?php echo $tour['id']; ?>">
                            <?php echo htmlspecialchars($tour['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Add Product</button>
        </form>
    </div>

    <!-- Products List -->
    <div class="list-section">
        <h3>Existing Products</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Tours</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo $product['id']; ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['description'] ?? ''); ?></td>
                        <td><?php echo number_format($product['price'], 2); ?> €</td>
                        <td><?php echo $product['stock_quantity']; ?></td>
                        <td><?php echo htmlspecialchars($product['tours'] ?? '-'); ?></td>
                        <td>
                            <button class="btn-edit" onclick="editProduct(<?php echo $product['id']; ?>)">Edit</button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                <button type="submit" class="btn-delete" onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Products by Tour Table -->
<div class="admin-container">
    <div class="form-section">
        <h3>Products by Tour</h3>
        <form method="GET" class="admin-form">
            <div class="form-group">
                <label for="filter_tour_id">Select tour:</label>
                <select name="tour_id" id="filter_tour_id" onchange="this.form.submit()">
                    <option value="">Select tour...</option>
                    <?php foreach ($tours as $tour): ?>
                        <option value="<?php echo $tour['id']; ?>" <?php echo isset($_GET['tour_id']) && $_GET['tour_id'] == $tour['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tour['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if (isset($_GET['tour_id'])): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tourProducts as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($product['product_description']); ?></td>
                            <td><?php echo $product['stock_quantity']; ?></td>
                            <td><?php echo number_format($product['price'], 2); ?> €</td>
                            <td><?php echo number_format($product['stock_quantity'] * $product['price'], 2); ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for editing product -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Product</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label for="edit_name">Product Name:</label>
                <input type="text" id="edit_name" name="name" required>
            </div>
            <div class="form-group">
                <label for="edit_description">Description:</label>
                <textarea id="edit_description" name="description" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label for="edit_price">Price (€):</label>
                <input type="number" id="edit_price" name="price" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="edit_stock_quantity">Current Stock:</label>
                <input type="number" id="edit_stock_quantity" name="stock_quantity" min="0" required>
            </div>
            <div class="form-group">
                <label for="edit_tour_id">Tour:</label>
                <select id="edit_tour_id" name="tour_id">
                    <option value="">Select tour...</option>
                    <?php foreach ($tours as $tour): ?>
                        <option value="<?php echo $tour['id']; ?>">
                            <?php echo htmlspecialchars($tour['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Save Changes</button>
        </form>
    </div>
</div>

<script>
function editProduct(id) {
    const modal = document.getElementById('editModal');
    const product = <?php echo json_encode($products); ?>.find(p => p.id === id);
    
    document.getElementById('edit_id').value = product.id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_description').value = product.description || '';
    document.getElementById('edit_price').value = product.price;
    document.getElementById('edit_stock_quantity').value = product.stock_quantity;
    
    // Set selected tour in select element
    const tourSelect = document.getElementById('edit_tour_id');
    if (tourSelect) {
        tourSelect.value = product.tour_id || '';
    }
    
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

<?php require_once '../includes/footer.php'; ?> 