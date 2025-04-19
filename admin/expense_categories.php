<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once '../includes/functions.php';

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/config.php';
    
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create') {
                $name = $_POST['name'];
                $description = $_POST['description'];
                
                $stmt = $pdo->prepare("INSERT INTO expense_categories (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                
                $_SESSION['message'] = 'Category has been successfully added.';
                $_SESSION['message_type'] = 'success';
            } elseif ($_POST['action'] === 'update') {
                $id = $_POST['id'];
                $name = $_POST['name'];
                $description = $_POST['description'];
                
                $stmt = $pdo->prepare("UPDATE expense_categories SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
                
                $_SESSION['message'] = 'Category has been successfully updated.';
                $_SESSION['message_type'] = 'success';
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                
                // Check if category has associated expenses
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE category_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['message'] = 'Cannot delete category that has associated expenses.';
                    $_SESSION['message_type'] = 'error';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $_SESSION['message'] = 'Category has been successfully deleted.';
                    $_SESSION['message_type'] = 'success';
                }
            }
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = 'An error occurred: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: expense_categories.php');
    exit;
}

require_once '../includes/header.php';
require_once '../includes/config.php';

// Get all categories
$stmt = $pdo->prepare("
    SELECT ec.*, 
           (SELECT COUNT(*) FROM expenses WHERE category_id = ec.id) as expense_count 
    FROM expense_categories ec 
    ORDER BY ec.name
");
$stmt->execute();
$categories = $stmt->fetchAll();
?>

<div class="admin-container">
    <h2>Expense Categories</h2>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message <?php echo $_SESSION['message_type']; ?>">
            <?php 
            echo $_SESSION['message'];
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Form for new category -->
    <div class="form-section">
        <h3>New Category</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label for="name">Category name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="2"></textarea>
            </div>
            <button type="submit">Add Category</button>
        </form>
    </div>

    <!-- Categories list -->
    <div class="list-section">
        <h3>Existing Categories</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Number of expenses</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                        <td><?php echo htmlspecialchars($category['description']); ?></td>
                        <td><?php echo $category['expense_count']; ?></td>
                        <td>
                            <button class="btn-edit" onclick="editCategory(<?php echo $category['id']; ?>)">Edit</button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
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
        <h3>Edit Category</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label for="edit_name">Category name:</label>
                <input type="text" id="edit_name" name="name" required>
            </div>
            <div class="form-group">
                <label for="edit_description">Description:</label>
                <textarea id="edit_description" name="description" rows="2"></textarea>
            </div>
            <button type="submit">Save Changes</button>
        </form>
    </div>
</div>

<script>
function editCategory(id) {
    const modal = document.getElementById('editModal');
    const category = <?php echo json_encode($categories); ?>.find(c => c.id === id);
    
    document.getElementById('edit_id').value = category.id;
    document.getElementById('edit_name').value = category.name;
    document.getElementById('edit_description').value = category.description;
    
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