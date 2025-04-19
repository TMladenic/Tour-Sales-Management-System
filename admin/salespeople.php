<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once '../includes/functions.php';

// Handle salespeople actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/config.php';
    
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create') {
                $name = $_POST['name'];
                
                $stmt = $pdo->prepare("INSERT INTO salespeople (name) VALUES (?)");
                $stmt->execute([$name]);
                
                $_SESSION['message'] = 'Salesperson has been successfully added.';
                $_SESSION['message_type'] = 'success';
            } elseif ($_POST['action'] === 'update') {
                $id = $_POST['id'];
                $name = $_POST['name'];
                $active = isset($_POST['active']) ? 1 : 0;
                
                $stmt = $pdo->prepare("UPDATE salespeople SET name = ?, active = ? WHERE id = ?");
                $stmt->execute([$name, $active, $id]);
                
                $_SESSION['message'] = 'Salesperson data has been successfully updated.';
                $_SESSION['message_type'] = 'success';
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                
                // Check if salesperson has sales
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE salesperson_id = ?");
                $stmt->execute([$id]);
                $hasSales = $stmt->fetchColumn() > 0;
                
                if ($hasSales) {
                    $_SESSION['message'] = 'Cannot delete a salesperson who has recorded sales.';
                    $_SESSION['message_type'] = 'error';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM salespeople WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $_SESSION['message'] = 'Salesperson has been removed.';
                    $_SESSION['message_type'] = 'success';
                }
            }
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = 'An error occurred: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: salespeople.php');
    exit;
}

require_once '../includes/header.php';
require_once '../includes/config.php';

// Get all salespeople
$stmt = $pdo->query("SELECT * FROM salespeople ORDER BY name");
$salespeople = $stmt->fetchAll();
?>

<div class="admin-container">
    <h2>Salespeople Management</h2>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message <?php echo $_SESSION['message_type']; ?>">
            <?php 
            echo $_SESSION['message'];
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Form for new salesperson -->
    <div class="form-section">
        <h3>New Salesperson</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label for="name">Name and surname:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <button type="submit">Add Salesperson</button>
        </form>
    </div>

    <!-- Salespeople List -->
    <div class="list-section">
        <h3>Salespeople List</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name and surname</th>
                    <th>Status</th>
                    <th>Added</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($salespeople as $salesperson): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($salesperson['name']); ?></td>
                        <td>
                            <?php if ($salesperson['active']): ?>
                                <span class="status active">Active</span>
                            <?php else: ?>
                                <span class="status inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d.m.Y. H:i', strtotime($salesperson['created_at'])); ?></td>
                        <td>
                            <button class="btn-edit" onclick="editSalesperson(<?php echo $salesperson['id']; ?>)">Edit</button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $salesperson['id']; ?>">
                                <button type="submit" class="btn-delete" onclick="return confirm('Are you sure?')">Delete</button>
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
        <h3>Edit Salesperson</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label for="edit_name">Name and surname:</label>
                <input type="text" id="edit_name" name="name" required>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="edit_active" name="active" checked>
                    Active
                </label>
            </div>
            <button type="submit">Save Changes</button>
        </form>
    </div>
</div>

<script>
function editSalesperson(id) {
    const modal = document.getElementById('editModal');
    const salesperson = <?php echo json_encode($salespeople); ?>.find(s => s.id === id);
    
    document.getElementById('edit_id').value = salesperson.id;
    document.getElementById('edit_name').value = salesperson.name;
    document.getElementById('edit_active').checked = salesperson.active == 1;
    
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