<?php
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/config.php';
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                try {
                    $name = $_POST['name'];
                    $coefficient = $_POST['coefficient'];
                    
                    $stmt = $pdo->prepare("INSERT INTO promoters (name, coefficient) VALUES (?, ?)");
                    $stmt->execute([$name, $coefficient]);
                    
                    $_SESSION['success'] = 'Promoter has been successfully created.';
                } catch (PDOException $e) {
                    $_SESSION['error'] = 'Error creating promoter: ' . $e->getMessage();
                }
                break;
            case 'create_deposit':
                try {
                    $tour_id = $_POST['tour_id'];
                    $salesperson_id = $_POST['salesperson_id'];
                    $amount = $_POST['amount'];
                    $percentage = $_POST['percentage'];
                    
                    $stmt = $pdo->prepare("INSERT INTO deposits (tour_id, salesperson_id, amount, percentage) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$tour_id, $salesperson_id, $amount, $percentage]);
                    
                    $_SESSION['success'] = 'Investor has been successfully added.';
                } catch (PDOException $e) {
                    $_SESSION['error'] = 'Error adding investor: ' . $e->getMessage();
                }
                break;
            case 'update':
                try {
                    $id = $_POST['id'];
                    $name = $_POST['name'];
                    $coefficient = $_POST['coefficient'];
                    
                    $stmt = $pdo->prepare("UPDATE promoters SET name = ?, coefficient = ? WHERE id = ?");
                    $stmt->execute([$name, $coefficient, $id]);
                    
                    $_SESSION['success'] = 'Promoter data has been successfully updated.';
                } catch (PDOException $e) {
                    $_SESSION['error'] = 'Error updating promoter: ' . $e->getMessage();
                }
                break;
            case 'delete':
                try {
                    $id = $_POST['id'];
                    
                    $stmt = $pdo->prepare("DELETE FROM promoters WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $_SESSION['success'] = 'Promoter has been removed.';
                } catch (PDOException $e) {
                    $_SESSION['error'] = 'Error deleting promoter: ' . $e->getMessage();
                }
                break;
            case 'delete_deposit':
                try {
                    $id = $_POST['id'];
                    
                    $stmt = $pdo->prepare("DELETE FROM deposits WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $_SESSION['success'] = 'Investor has been removed.';
                } catch (PDOException $e) {
                    $_SESSION['error'] = 'Error deleting investor: ' . $e->getMessage();
                }
                break;
            case 'update_deposit':
                try {
                    $id = $_POST['id'];
                    $tour_id = $_POST['tour_id'];
                    $salesperson_id = $_POST['salesperson_id'];
                    $amount = $_POST['amount'];
                    $percentage = $_POST['percentage'];
                    
                    $stmt = $pdo->prepare("UPDATE deposits SET tour_id = ?, salesperson_id = ?, amount = ?, percentage = ? WHERE id = ?");
                    $stmt->execute([$tour_id, $salesperson_id, $amount, $percentage, $id]);
                    
                    $_SESSION['success'] = 'Investor data has been successfully updated.';
                } catch (PDOException $e) {
                    $_SESSION['error'] = 'Error updating investor: ' . $e->getMessage();
                }
                break;
        }
    }
    
    header('Location: promoters.php');
    exit;
}

require_once '../includes/header.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Get all promoters
$stmt = $pdo->query("SELECT * FROM promoters ORDER BY name");
$promoters = $stmt->fetchAll();

// Get all tours
$stmt = $pdo->query("SELECT * FROM tours ORDER BY name");
$tours = $stmt->fetchAll();

// Get all salespeople
$stmt = $pdo->query("SELECT * FROM salespeople ORDER BY name");
$salespeople = $stmt->fetchAll();

// Get all deposits
$stmt = $pdo->query("
    SELECT d.*, t.name as tour_name, sp.name as salesperson_name 
    FROM deposits d 
    JOIN tours t ON d.tour_id = t.id 
    JOIN salespeople sp ON d.salesperson_id = sp.id 
    ORDER BY d.created_at DESC
");
$deposits = $stmt->fetchAll();

// Get total sales for each tour
$tourSales = [];
$stmt = $pdo->query("
    SELECT t.id, COALESCE(SUM(s.price * s.quantity), 0) as total_sales
    FROM tours t
    LEFT JOIN sales s ON t.id = s.tour_id
    GROUP BY t.id
");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($results as $row) {
    $tourSales[$row['id']] = $row['total_sales'];
}
?>

<div class="admin-container">
    <h2>Promoter Management</h2>
    
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
    
    <!-- Form for adding promoter -->
    <div class="form-section">
        <h3>Add Promoter</h3>
        <form id="addPromoterForm" class="admin-form" method="POST" action="promoters.php">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label for="name">Promoter Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="coefficient">Coefficient (€):</label>
                <input type="number" id="coefficient" name="coefficient" step="0.01" min="0" required>
            </div>
            <button type="submit" class="btn btn-primary">Add Promoter</button>
        </form>
    </div>
    
    <!-- Promoters List -->
    <div class="list-section">
        <h3>Promoters List</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Coefficient</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promoters as $promoter): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($promoter['name']); ?></td>
                        <td><?php echo number_format($promoter['coefficient'], 2); ?> €</td>
                        <td>
                            <button class="btn-edit" onclick="editPromoter(<?php echo $promoter['id']; ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-delete" onclick="deletePromoter(<?php echo $promoter['id']; ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Form for adding investor -->
    <div class="form-section">
        <h3>Add Investor</h3>
        <form id="addDepositForm" class="admin-form" method="POST" action="promoters.php">
            <input type="hidden" name="action" value="create_deposit">
            <div class="form-group">
                <label for="tour_id">Tour:</label>
                <select id="tour_id" name="tour_id" required>
                    <option value="">Select tour...</option>
                    <?php foreach ($tours as $tour): ?>
                        <option value="<?php echo $tour['id']; ?>">
                            <?php echo htmlspecialchars($tour['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="salesperson_id">Salesperson:</label>
                <select id="salesperson_id" name="salesperson_id" required>
                    <option value="">Select salesperson...</option>
                    <?php foreach ($salespeople as $salesperson): ?>
                        <option value="<?php echo $salesperson['id']; ?>">
                            <?php echo htmlspecialchars($salesperson['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="amount">Amount (€):</label>
                <input type="number" id="amount" name="amount" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="percentage">Percentage (%):</label>
                <input type="number" id="percentage" name="percentage" step="0.01" min="0" max="100" required>
            </div>
            <button type="submit" class="btn btn-primary">Add Investor</button>
        </form>
    </div>
    
    <!-- Investors List -->
    <div class="list-section">
        <h3>Investors List</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Tour</th>
                    <th>Salesperson</th>
                    <th>Amount</th>
                    <th>Percentage</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deposits as $deposit): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($deposit['tour_name']); ?></td>
                        <td><?php echo htmlspecialchars($deposit['salesperson_name']); ?></td>
                        <td><?php echo number_format($deposit['amount'], 2); ?> €</td>
                        <td><?php echo number_format($deposit['percentage'], 2); ?>%</td>
                        <td>
                            <button class="btn-edit" onclick="editDeposit(<?php echo $deposit['id']; ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-delete" onclick="deleteDeposit(<?php echo $deposit['id']; ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Promoter Modal -->
<div id="editPromoterModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Promoter</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_promoter_id">
            <div class="form-group">
                <label for="edit_name">Name:</label>
                <input type="text" id="edit_name" name="name" required>
            </div>
            <div class="form-group">
                <label for="edit_coefficient">Coefficient (€):</label>
                <input type="number" id="edit_coefficient" name="coefficient" step="0.01" min="0" required>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<!-- Edit Investor Modal -->
<div id="editDepositModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Investor</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="update_deposit">
            <input type="hidden" name="id" id="edit_deposit_id">
            <div class="form-group">
                <label for="edit_tour_id">Tour:</label>
                <select id="edit_tour_id" name="tour_id" required>
                    <option value="">Select tour...</option>
                    <?php foreach ($tours as $tour): ?>
                        <option value="<?php echo $tour['id']; ?>">
                            <?php echo htmlspecialchars($tour['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_salesperson_id">Salesperson:</label>
                <select id="edit_salesperson_id" name="salesperson_id" required>
                    <option value="">Select salesperson...</option>
                    <?php foreach ($salespeople as $salesperson): ?>
                        <option value="<?php echo $salesperson['id']; ?>">
                            <?php echo htmlspecialchars($salesperson['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_amount">Amount (€):</label>
                <input type="number" id="edit_amount" name="amount" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="edit_percentage">Percentage (%):</label>
                <input type="number" id="edit_percentage" name="percentage" step="0.01" min="0" max="100" required>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<script>
function editPromoter(id) {
    const modal = document.getElementById('editPromoterModal');
    const promoter = <?php echo json_encode($promoters); ?>.find(p => p.id === id);
    
    document.getElementById('edit_promoter_id').value = promoter.id;
    document.getElementById('edit_name').value = promoter.name;
    document.getElementById('edit_coefficient').value = promoter.coefficient;
    
    modal.style.display = 'block';
}

function editDeposit(id) {
    const modal = document.getElementById('editDepositModal');
    const deposit = <?php echo json_encode($deposits); ?>.find(d => d.id === id);
    
    document.getElementById('edit_deposit_id').value = deposit.id;
    document.getElementById('edit_tour_id').value = deposit.tour_id;
    document.getElementById('edit_salesperson_id').value = deposit.salesperson_id;
    document.getElementById('edit_amount').value = deposit.amount;
    document.getElementById('edit_percentage').value = deposit.percentage;
    
    modal.style.display = 'block';
}

function deletePromoter(id) {
    if (confirm('Are you sure you want to delete this promoter?')) {
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

function deleteDeposit(id) {
    if (confirm('Are you sure you want to delete this investor?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_deposit">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modals when clicking the X
document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.onclick = function() {
        this.closest('.modal').style.display = 'none';
    }
});

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?> 