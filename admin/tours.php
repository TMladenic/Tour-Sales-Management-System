<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Handle export request
if (isset($_GET['export'])) {
    $tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : null;
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="tour_' . ($tour_id ? $tour_id : 'all') . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    fputcsv($output, [
        'Tour ID', 'Tour Name', 'Supplier', 'Start', 'End', 'Status', 'Archived', 'Notes',
        'Sale ID', 'Product', 'Quantity', 'Price', 'Discount', 'Total', 'Salesperson',
        'Expense ID', 'Expense Category', 'Expense Amount', 'Expense Description',
        'Promoter ID', 'Promoter Name', 'Coefficient', 'Quantity', 'Total Promoter',
        'Total Sales', 'Total Expenses', 'Total Promoters', 'Net Profit'
    ], ';');
    
    // Get tour data
    $tours_query = "
        SELECT t.*, s.name as supplier_name 
        FROM tours t 
        LEFT JOIN suppliers s ON t.supplier_id = s.id 
        " . ($tour_id ? "WHERE t.id = ?" : "") . "
        ORDER BY t.start_date DESC
    ";
    
    $stmt = $pdo->prepare($tours_query);
    if ($tour_id) {
        $stmt->execute([$tour_id]);
    } else {
        $stmt->execute();
    }
    
    while ($tour = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Get sales data
        $sales_query = "
            SELECT s.*, p.name as product_name, sp.name as salesperson_name
            FROM sales s
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN salespeople sp ON s.salesperson_id = sp.id
            WHERE s.tour_id = ?
        ";
        $sales_stmt = $pdo->prepare($sales_query);
        $sales_stmt->execute([$tour['id']]);
        $sales = $sales_stmt->fetchAll();
        
        // Get expenses data
        $expenses_query = "
            SELECT e.*, ec.name as category_name
            FROM expenses e
            LEFT JOIN expense_categories ec ON e.category_id = ec.id
            WHERE e.tour_id = ?
        ";
        $expenses_stmt = $pdo->prepare($expenses_query);
        $expenses_stmt->execute([$tour['id']]);
        $expenses = $expenses_stmt->fetchAll();
        
        // Get promoters data
        $promoters_query = "
            SELECT ps.*, p.name as promoter_name, p.coefficient
            FROM promoter_sales ps
            LEFT JOIN promoters p ON ps.promoter_id = p.id
            WHERE ps.tour_id = ?
        ";
        $promoters_stmt = $pdo->prepare($promoters_query);
        $promoters_stmt->execute([$tour['id']]);
        $promoters = $promoters_stmt->fetchAll();
        
        // Calculate totals
        $total_sales = array_sum(array_map(function($sale) {
            return ($sale['price'] * $sale['quantity']) - $sale['discount'];
        }, $sales));
        
        $total_expenses = array_sum(array_map(function($expense) {
            return $expense['amount'];
        }, $expenses));
        
        $total_promoters = array_sum(array_map(function($promoter) {
            return $promoter['quantity'] * $promoter['coefficient'];
        }, $promoters));
        
        $net_profit = $total_sales - $total_expenses - $total_promoters;
        
        // Write tour data with all related information
        $max_rows = max(count($sales), count($expenses), count($promoters));
        
        for ($i = 0; $i < $max_rows; $i++) {
            $row = [
                $tour['id'],
                $tour['name'],
                $tour['supplier_name'],
                date('d.m.Y.', strtotime($tour['start_date'])),
                date('d.m.Y.', strtotime($tour['end_date'])),
                $tour['status'],
                $tour['archived'] ? 'Yes' : 'No',
                $tour['notes'],
                $sales[$i]['id'] ?? '',
                $sales[$i]['product_name'] ?? '',
                $sales[$i]['quantity'] ?? '',
                number_format($sales[$i]['price'] ?? 0, 2, ',', '.'),
                number_format($sales[$i]['discount'] ?? 0, 2, ',', '.'),
                isset($sales[$i]) ? number_format(($sales[$i]['price'] * $sales[$i]['quantity'] - $sales[$i]['discount']), 2, ',', '.') : '',
                $sales[$i]['salesperson_name'] ?? '',
                $expenses[$i]['id'] ?? '',
                $expenses[$i]['category_name'] ?? '',
                number_format($expenses[$i]['amount'] ?? 0, 2, ',', '.'),
                $expenses[$i]['description'] ?? '',
                $promoters[$i]['id'] ?? '',
                $promoters[$i]['promoter_name'] ?? '',
                number_format($promoters[$i]['coefficient'] ?? 0, 2, ',', '.'),
                $promoters[$i]['quantity'] ?? '',
                isset($promoters[$i]) ? number_format(($promoters[$i]['quantity'] * $promoters[$i]['coefficient']), 2, ',', '.') : '',
                number_format($total_sales, 2, ',', '.'),
                number_format($total_expenses, 2, ',', '.'),
                number_format($total_promoters, 2, ',', '.'),
                number_format($net_profit, 2, ',', '.')
            ];
            
            fputcsv($output, $row, ';');
        }
    }
    
    fclose($output);
    exit;
}

// Handle import request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    try {
        if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error loading file.");
        }
        
        $file = $_FILES['import_file']['tmp_name'];
        $handle = fopen($file, "r");
        
        // Skip header row
        fgetcsv($handle);
        
        $pdo->beginTransaction();
        
        $current_tour = null;
        $tour_data = [];
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            // If this is a new tour
            if (!empty($data[0]) && $data[0] != $current_tour) {
                if ($current_tour !== null) {
                    // Process the previous tour's data
                    processTourData($pdo, $tour_data);
                }
                
                $current_tour = $data[0];
                $tour_data = [
                    'tour' => [
                        'id' => $data[0],
                        'name' => $data[1],
                        'supplier_name' => $data[2],
                        'start_date' => $data[3],
                        'end_date' => $data[4],
                        'status' => $data[5],
                        'archived' => strtolower($data[6]) === 'da' ? 1 : 0,
                        'notes' => $data[7]
                    ],
                    'sales' => [],
                    'expenses' => [],
                    'promoters' => []
                ];
            }
            
            // Add sales data if present
            if (!empty($data[8])) {
                $tour_data['sales'][] = [
                    'id' => $data[8],
                    'product_name' => $data[9],
                    'quantity' => $data[10],
                    'price' => $data[11],
                    'discount' => $data[12],
                    'salesperson_name' => $data[14]
                ];
            }
            
            // Add expenses data if present
            if (!empty($data[15])) {
                $tour_data['expenses'][] = [
                    'id' => $data[15],
                    'category_name' => $data[16],
                    'amount' => $data[17],
                    'description' => $data[18]
                ];
            }
            
            // Add promoters data if present
            if (!empty($data[19])) {
                $tour_data['promoters'][] = [
                    'id' => $data[19],
                    'name' => $data[20],
                    'coefficient' => $data[21]
                ];
            }
        }
        
        // Process the last tour's data
        if ($current_tour !== null) {
            processTourData($pdo, $tour_data);
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Tours have been successfully imported.";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header('Location: tours.php');
    exit;
}

function processTourData($pdo, $tour_data) {
    // Get or create supplier
    $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE name = ?");
    $stmt->execute([$tour_data['tour']['supplier_name']]);
    $supplier = $stmt->fetch();
    
    if (!$supplier) {
        $stmt = $pdo->prepare("INSERT INTO suppliers (name) VALUES (?)");
        $stmt->execute([$tour_data['tour']['supplier_name']]);
        $supplier_id = $pdo->lastInsertId();
    } else {
        $supplier_id = $supplier['id'];
    }
    
    // Insert or update tour
    $stmt = $pdo->prepare("
        INSERT INTO tours (id, name, supplier_id, start_date, end_date, status, archived, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        name = VALUES(name),
        supplier_id = VALUES(supplier_id),
        start_date = VALUES(start_date),
        end_date = VALUES(end_date),
        status = VALUES(status),
        archived = VALUES(archived),
        notes = VALUES(notes)
    ");
    
    $stmt->execute([
        $tour_data['tour']['id'],
        $tour_data['tour']['name'],
        $supplier_id,
        $tour_data['tour']['start_date'],
        $tour_data['tour']['end_date'],
        $tour_data['tour']['status'],
        $tour_data['tour']['archived'],
        $tour_data['tour']['notes']
    ]);
    
    // Process sales
    foreach ($tour_data['sales'] as $sale) {
        // Get or create product
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
        $stmt->execute([$sale['product_name']]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $stmt = $pdo->prepare("INSERT INTO products (name) VALUES (?)");
            $stmt->execute([$sale['product_name']]);
            $product_id = $pdo->lastInsertId();
        } else {
            $product_id = $product['id'];
        }
        
        // Get or create salesperson
        $stmt = $pdo->prepare("SELECT id FROM salespeople WHERE name = ?");
        $stmt->execute([$sale['salesperson_name']]);
        $salesperson = $stmt->fetch();
        
        if (!$salesperson) {
            $stmt = $pdo->prepare("INSERT INTO salespeople (name) VALUES (?)");
            $stmt->execute([$sale['salesperson_name']]);
            $salesperson_id = $pdo->lastInsertId();
        } else {
            $salesperson_id = $salesperson['id'];
        }
        
        // Insert or update sale
        $stmt = $pdo->prepare("
            INSERT INTO sales (id, tour_id, product_id, quantity, price, discount, salesperson_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            product_id = VALUES(product_id),
            quantity = VALUES(quantity),
            price = VALUES(price),
            discount = VALUES(discount),
            salesperson_id = VALUES(salesperson_id)
        ");
        
        $stmt->execute([
            $sale['id'],
            $tour_data['tour']['id'],
            $product_id,
            $sale['quantity'],
            $sale['price'],
            $sale['discount'],
            $salesperson_id
        ]);
    }
    
    // Process expenses
    foreach ($tour_data['expenses'] as $expense) {
        // Get or create expense category
        $stmt = $pdo->prepare("SELECT id FROM expense_categories WHERE name = ?");
        $stmt->execute([$expense['category_name']]);
        $category = $stmt->fetch();
        
        if (!$category) {
            $stmt = $pdo->prepare("INSERT INTO expense_categories (name) VALUES (?)");
            $stmt->execute([$expense['category_name']]);
            $category_id = $pdo->lastInsertId();
        } else {
            $category_id = $category['id'];
        }
        
        // Insert or update expense
        $stmt = $pdo->prepare("
            INSERT INTO expenses (id, tour_id, category_id, amount, description)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            category_id = VALUES(category_id),
            amount = VALUES(amount),
            description = VALUES(description)
        ");
        
        $stmt->execute([
            $expense['id'],
            $tour_data['tour']['id'],
            $category_id,
            $expense['amount'],
            $expense['description']
        ]);
    }
    
    // Process promoters
    foreach ($tour_data['promoters'] as $promoter) {
        // Get or create user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE name = ?");
        $stmt->execute([$promoter['name']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $stmt = $pdo->prepare("INSERT INTO users (name, role) VALUES (?, 'promoter')");
            $stmt->execute([$promoter['name']]);
            $user_id = $pdo->lastInsertId();
        } else {
            $user_id = $user['id'];
        }
        
        // Insert or update promoter
        $stmt = $pdo->prepare("
            INSERT INTO promoters (id, tour_id, user_id, coefficient)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            coefficient = VALUES(coefficient)
        ");
        
        $stmt->execute([
            $promoter['id'],
            $tour_data['tour']['id'],
            $user_id,
            $promoter['coefficient']
        ]);
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    // Check if all required data is present
                    if (empty($_POST['name']) || empty($_POST['supplier_id']) || empty($_POST['start_date']) || empty($_POST['end_date'])) {
                        throw new Exception("Missing required data for tour creation.");
                    }
                    
                    // Check supplier existence
                    $checkSupplier = $pdo->prepare("SELECT id FROM suppliers WHERE id = ?");
                    $checkSupplier->execute([$_POST['supplier_id']]);
                    if (!$checkSupplier->fetch()) {
                        throw new Exception("Supplier with ID " . $_POST['supplier_id'] . " does not exist.");
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO tours (name, supplier_id, start_date, end_date, status, notes, created_at) VALUES (?, ?, ?, ?, 'active', '', CURRENT_TIMESTAMP)");
                    $result = $stmt->execute([$_POST['name'], $_POST['supplier_id'], $_POST['start_date'], $_POST['end_date']]);
                    
                    if ($result) {
                        $_SESSION['success'] = "Tour has been successfully added.";
                    } else {
                        throw new Exception("Error saving tour.");
                    }
                    break;
                    
                case 'update':
                    $stmt = $pdo->prepare("UPDATE tours SET name = ?, supplier_id = ?, start_date = ?, end_date = ?, status = ?, notes = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['name'], 
                        $_POST['supplier_id'], 
                        $_POST['start_date'], 
                        $_POST['end_date'],
                        $_POST['status'],
                        $_POST['notes'],
                        $_POST['id']
                    ]);
                    $_SESSION['success'] = "Tour has been successfully updated.";
                    break;
                    
                case 'archive':
                    $tour_id = $_POST['tour_id'];
                    $stmt = $pdo->prepare("UPDATE tours SET archived = TRUE WHERE id = ?");
                    $stmt->execute([$tour_id]);
                    $_SESSION['success'] = "Tour has been successfully archived.";
                    break;
                    
                case 'unarchive':
                    $tour_id = $_POST['tour_id'];
                    $stmt = $pdo->prepare("UPDATE tours SET archived = FALSE WHERE id = ?");
                    $stmt->execute([$tour_id]);
                    $_SESSION['success'] = "Tour has been successfully restored from archive.";
                    break;
                    
                case 'delete':
                    $tour_id = $_POST['tour_id'];
                    $stmt = $pdo->prepare("DELETE FROM tours WHERE id = ?");
                    $stmt->execute([$tour_id]);
                    $_SESSION['success'] = "Tour has been successfully deleted.";
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header('Location: tours.php');
    exit;
}

require_once '../includes/header.php';

// Get all suppliers for dropdown
try {
    $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY name");
    $suppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching suppliers: " . $e->getMessage();
    $suppliers = [];
}

// Get all tours with their status and counts
try {
    // First, get basic tour information
    $stmt = $pdo->query("
        SELECT 
            t.id, t.name, t.supplier_id, t.start_date, t.end_date, t.created_at,
            t.archived, t.status, t.notes,
            sup.name as supplier_name
        FROM tours t
        LEFT JOIN suppliers sup ON t.supplier_id = sup.id
        ORDER BY t.start_date DESC
    ");
    $tours = $stmt->fetchAll();

    // Then, get sales statistics for each tour
    $salesStats = [];
    $stmt = $pdo->query("
        SELECT 
            tour_id,
            COUNT(DISTINCT id) as total_sales,
            SUM(quantity) as total_quantity,
            SUM(price * quantity) as total_revenue,
            SUM(discount) as total_discounts
        FROM sales
        GROUP BY tour_id
    ");
    $salesStats = $stmt->fetchAll(PDO::FETCH_GROUP);

    // Get expenses for each tour
    $expenseStats = [];
    $stmt = $pdo->query("
        SELECT 
            tour_id,
            SUM(amount) as total_expenses
        FROM expenses
        GROUP BY tour_id
    ");
    $expenseStats = $stmt->fetchAll(PDO::FETCH_GROUP);

    // Get waiting list count for each tour
    $waitingStats = [];
    $stmt = $pdo->query("
        SELECT 
            tour_id,
            COUNT(*) as waiting_count
        FROM waiting_list
        GROUP BY tour_id
    ");
    $waitingStats = $stmt->fetchAll(PDO::FETCH_GROUP);

    // Combine all statistics
    foreach ($tours as &$tour) {
        $tourId = $tour['id'];
        $tour['total_sales'] = $salesStats[$tourId][0]['total_sales'] ?? 0;
        $tour['total_quantity'] = $salesStats[$tourId][0]['total_quantity'] ?? 0;
        $tour['total_revenue'] = $salesStats[$tourId][0]['total_revenue'] ?? 0;
        $tour['total_discounts'] = $salesStats[$tourId][0]['total_discounts'] ?? 0;
        $tour['total_expenses'] = $expenseStats[$tourId][0]['total_expenses'] ?? 0;
        $tour['waiting_count'] = $waitingStats[$tourId][0]['waiting_count'] ?? 0;
    }
    unset($tour); // Break the reference
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching tours: " . $e->getMessage();
    $tours = [];
}

?>

<div class="admin-container">
    <h2>Tour Management</h2>
    
    <!-- Export/Import buttons -->
    <div class="import-export-buttons">
        <a href="?export=csv" class="btn btn-export">Export all tours</a>
        <form method="POST" enctype="multipart/form-data" class="import-form">
            <input type="file" name="import_file" accept=".csv" required>
            <button type="submit" class="btn btn-import">Import CSV</button>
        </form>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="message success">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="message error">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>
    
    <!-- Form for adding new tour -->
    <div class="form-section">
        <h3>Add New Tour</h3>
        <form method="POST" class="admin-form" onsubmit="return validateForm()">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label for="name">Tour Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="supplier_id">Supplier:</label>
                <select id="supplier_id" name="supplier_id" required>
                    <option value="">Select supplier...</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier['id']; ?>">
                            <?php echo htmlspecialchars($supplier['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date" required>
            </div>
            <div class="form-group">
                <label for="end_date">End Date:</label>
                <input type="date" id="end_date" name="end_date" required>
            </div>
            <button type="submit" class="btn btn-primary">Add Tour</button>
        </form>
    </div>
    
    <!-- Active Tours Table -->
    <div class="list-section">
        <h3>Active Tours</h3>
        <?php 
        $active_tours = array_filter($tours, function($tour) {
            return !$tour['archived'];
        });
        if (empty($active_tours)): 
        ?>
            <p>No active tours.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Supplier</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Sales</th>
                        <th>Expenses</th>
                        <th>Waiting</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_tours as $tour): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tour['name']); ?></td>
                            <td><?php echo htmlspecialchars($tour['supplier_name']); ?></td>
                            <td><?php echo date('d.m.Y.', strtotime($tour['start_date'])); ?></td>
                            <td><?php echo date('d.m.Y.', strtotime($tour['end_date'])); ?></td>
                            <td><?php echo htmlspecialchars($tour['status']); ?></td>
                            <td><?php echo $tour['notes'] ? htmlspecialchars($tour['notes']) : '-'; ?></td>
                            <td><?php echo isset($tour['total_sales']) ? number_format($tour['total_sales'], 2) . ' €' : '0.00 €'; ?></td>
                            <td><?php echo isset($tour['total_expenses']) ? number_format($tour['total_expenses'], 2) . ' €' : '0.00 €'; ?></td>
                            <td><?php echo isset($tour['waiting_count']) ? $tour['waiting_count'] : 0; ?></td>
                            <td>
                                <button class="btn-edit" onclick="archiveTour(<?php echo $tour['id']; ?>)">
                                    <i class="fas fa-archive"></i> Archive
                                </button>
                                <button class="btn-edit" onclick="editTour(<?php echo $tour['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn-delete" onclick="deleteTour(<?php echo $tour['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                <a href="?export=csv&tour_id=<?php echo $tour['id']; ?>" class="btn btn-export-small">Export Tour</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Archived Tours Table -->
    <div class="list-section">
        <h3>Archived Tours</h3>
        <?php 
        $archived_tours = array_filter($tours, function($tour) {
            return $tour['archived'];
        });
        if (empty($archived_tours)): 
        ?>
            <p>No archived tours.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Supplier</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Sales</th>
                        <th>Expenses</th>
                        <th>Waiting</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archived_tours as $tour): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tour['name']); ?></td>
                            <td><?php echo htmlspecialchars($tour['supplier_name']); ?></td>
                            <td><?php echo date('d.m.Y.', strtotime($tour['start_date'])); ?></td>
                            <td><?php echo date('d.m.Y.', strtotime($tour['end_date'])); ?></td>
                            <td><?php echo htmlspecialchars($tour['status']); ?></td>
                            <td><?php echo $tour['notes'] ? htmlspecialchars($tour['notes']) : '-'; ?></td>
                            <td><?php echo isset($tour['total_sales']) ? number_format($tour['total_sales'], 2) . ' €' : '0.00 €'; ?></td>
                            <td><?php echo isset($tour['total_expenses']) ? number_format($tour['total_expenses'], 2) . ' €' : '0.00 €'; ?></td>
                            <td><?php echo isset($tour['waiting_count']) ? $tour['waiting_count'] : 0; ?></td>
                            <td>
                                <button class="btn-edit" onclick="unarchiveTour(<?php echo $tour['id']; ?>)">
                                    <i class="fas fa-box-open"></i> Restore from Archive
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Tour Modal -->
<div id="editTourModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Tour</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label for="edit_name">Tour Name:</label>
                <input type="text" id="edit_name" name="name" required>
            </div>
            <div class="form-group">
                <label for="edit_supplier_id">Supplier:</label>
                <select id="edit_supplier_id" name="supplier_id" required>
                    <option value="">Select supplier</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier['id']; ?>">
                            <?php echo htmlspecialchars($supplier['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_start_date">Start Date:</label>
                <input type="date" id="edit_start_date" name="start_date" required>
            </div>
            <div class="form-group">
                <label for="edit_end_date">End Date:</label>
                <input type="date" id="edit_end_date" name="end_date" required>
            </div>
            <div class="form-group">
                <label for="edit_status">Status:</label>
                <select id="edit_status" name="status" required>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_notes">Notes:</label>
                <textarea id="edit_notes" name="notes"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<script>
function editTour(id) {
    const modal = document.getElementById('editTourModal');
    const tour = <?php echo json_encode($tours); ?>.find(t => t.id === id);
    
    document.getElementById('edit_id').value = tour.id;
    document.getElementById('edit_name').value = tour.name;
    document.getElementById('edit_supplier_id').value = tour.supplier_id;
    document.getElementById('edit_start_date').value = tour.start_date;
    document.getElementById('edit_end_date').value = tour.end_date;
    document.getElementById('edit_status').value = tour.status;
    document.getElementById('edit_notes').value = tour.notes;
    
    modal.style.display = 'block';
}

// Close modal when clicking the X
document.querySelector('.close').onclick = function() {
    document.getElementById('editTourModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editTourModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}

// Handle delete button click
function deleteTour(id) {
    if (confirm('Are you sure you want to delete this tour?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="tour_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function archiveTour(tourId) {
    if (confirm('Are you sure you want to archive this tour?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="archive">
            <input type="hidden" name="tour_id" value="${tourId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function unarchiveTour(tourId) {
    if (confirm('Are you sure you want to restore this tour from archive?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="unarchive">
            <input type="hidden" name="tour_id" value="${tourId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function validateForm() {
    const name = document.getElementById('name').value;
    const supplierId = document.getElementById('supplier_id').value;
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    if (!name || !supplierId || !startDate || !endDate) {
        alert('Please fill in all required fields.');
        return false;
    }
    
    return true;
}
</script>

<style>
.import-export-buttons {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-export, .btn-import {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
}

.btn-export {
    background-color: #4CAF50;
    color: white;
}

.btn-import {
    background-color: #2196F3;
    color: white;
}

.import-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-export-small {
    padding: 4px 8px;
    font-size: 0.9em;
    margin-left: 5px;
}
</style>

<?php require_once '../includes/footer.php'; ?> 