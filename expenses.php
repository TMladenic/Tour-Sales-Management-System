<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if tour is selected
if (!isset($_SESSION['current_tour_id'])) {
    $_SESSION['message'] = "Please select a tour.";
    $_SESSION['message_type'] = "warning";
    header('Location: index.php');
    exit;
}

// Check if tour is archived
$stmt = $pdo->prepare("SELECT archived FROM tours WHERE id = ?");
$stmt->execute([$_SESSION['current_tour_id']]);
$tour = $stmt->fetch();

if ($tour && $tour['archived']) {
    $_SESSION['message'] = 'This tour is archived. You can only view data.';
    $_SESSION['message_type'] = 'warning';
}

// Get current tour
$stmt = $pdo->prepare("SELECT * FROM tours WHERE id = ?");
$stmt->execute([$_SESSION['current_tour_id']]);
$currentTour = $stmt->fetch();

if (!$currentTour) {
    $_SESSION['message'] = "Selected tour does not exist.";
    $_SESSION['message_type'] = "error";
    header('Location: index.php');
    exit;
}

// Handle expenses actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create') {
                $stmt = $pdo->prepare("
                    INSERT INTO expenses (tour_id, category_id, description, amount, salesperson_id, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['current_tour_id'],
                    $_POST['category_id'],
                    $_POST['description'],
                    $_POST['amount'],
                    $_POST['salesperson_id'],
                    $_SESSION['user_id']
                ]);
                
                $_SESSION['message'] = 'Expense has been successfully recorded.';
                $_SESSION['message_type'] = 'success';
                header('Location: expenses.php');
                exit;
            } elseif ($_POST['action'] === 'update') {
                $id = $_POST['id'];
                $description = $_POST['description'];
                $amount = $_POST['amount'];
                $category_id = $_POST['category_id'];
                $salesperson_id = $_POST['salesperson_id'];
                
                $stmt = $pdo->prepare("
                    UPDATE expenses 
                    SET description = ?, amount = ?, category_id = ?, salesperson_id = ? 
                    WHERE id = ? AND tour_id = ?
                ");
                $stmt->execute([$description, $amount, $category_id, $salesperson_id, $id, $_SESSION['current_tour_id']]);
                
                $_SESSION['message'] = 'Expense data has been successfully updated.';
                $_SESSION['message_type'] = 'success';
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                
                $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND tour_id = ?");
                $stmt->execute([$id, $_SESSION['current_tour_id']]);
                
                $_SESSION['message'] = 'Expense has been removed.';
                $_SESSION['message_type'] = 'success';
            }
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
        header('Location: expenses.php');
        exit;
    }
}

require_once 'includes/header.php';

// Get all expense categories
$stmt = $pdo->prepare("SELECT * FROM expense_categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Get all active salespeople
$stmt = $pdo->prepare("SELECT * FROM salespeople WHERE active = 1 ORDER BY name");
$stmt->execute();
$salespeople = $stmt->fetchAll();

// Get all expenses for current tour with category details
$stmt = $pdo->prepare("
    SELECT e.*, ec.name as category_name, ec.id as category_id, sp.name as salesperson_name
    FROM expenses e 
    LEFT JOIN expense_categories ec ON e.category_id = ec.id 
    LEFT JOIN salespeople sp ON e.salesperson_id = sp.id
    WHERE e.tour_id = ? 
    ORDER BY e.created_at DESC
");
$stmt->execute([$_SESSION['current_tour_id']]);
$expenses = $stmt->fetchAll();

// Calculate total expenses
$totalExpenses = array_sum(array_column($expenses, 'amount'));

?>

<div class="container standard-page">
    <h2>Expenses<?php if ($currentTour): ?> - <?php echo htmlspecialchars($currentTour['name']); ?><?php endif; ?></h2>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message <?php echo $_SESSION['message_type']; ?>">
            <?php 
            echo htmlspecialchars($_SESSION['message']);
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>

    <div class="form-section">
        <h3>Add Expense</h3>
        <form method="POST" class="form">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label for="category_id">Category:</label>
                <select name="category_id" id="category_id" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="salesperson_id">Salesperson:</label>
                <select name="salesperson_id" id="salesperson_id">
                    <option value="">Select salesperson</option>
                    <?php foreach ($salespeople as $salesperson): ?>
                        <option value="<?php echo $salesperson['id']; ?>">
                            <?php echo htmlspecialchars($salesperson['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="amount">Amount (€):</label>
                <input type="number" name="amount" id="amount" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea name="description" id="description" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Add Expense</button>
        </form>
    </div>
    
    <div class="list-section">
        <h3>Expenses List</h3>
        <div class="total-amount">
            <strong>Total expenses:</strong> €<?php echo number_format($totalExpenses, 2); ?>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Salesperson</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $expense): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($expense['category_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($expense['salesperson_name'] ?? '-'); ?></td>
                        <td><?php echo number_format($expense['amount'] ?? 0, 2); ?> €</td>
                        <td><?php echo htmlspecialchars($expense['description'] ?? ''); ?></td>
                        <td><?php echo date('d.m.Y. H:i', strtotime($expense['created_at'] ?? 'now')); ?></td>
                        <td>
                            <button class="btn-edit" onclick="editExpense(<?php echo $expense['id']; ?>)">Edit</button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $expense['id']; ?>">
                                <button type="submit" class="btn-delete" onclick="return confirm('Are you sure you want to delete this expense?')">Delete</button>
                            </form>
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
        <h3>Edit Expense</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label for="edit_category_id">Category:</label>
                <select id="edit_category_id" name="category_id" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_salesperson_id">Salesperson:</label>
                <select id="edit_salesperson_id" name="salesperson_id">
                    <option value="">Select salesperson</option>
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
                <label for="edit_description">Description:</label>
                <textarea id="edit_description" name="description" required></textarea>
            </div>
            <button type="submit">Save Changes</button>
        </form>
    </div>
</div>

<script>
function editExpense(id) {
    const modal = document.getElementById('editModal');
    const expense = <?php echo json_encode($expenses); ?>.find(e => e.id === id);
    
    document.getElementById('edit_id').value = expense.id;
    document.getElementById('edit_category_id').value = expense.category_id;
    document.getElementById('edit_salesperson_id').value = expense.salesperson_id;
    document.getElementById('edit_amount').value = expense.amount;
    document.getElementById('edit_description').value = expense.description;
    
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