<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once '../includes/functions.php';

// Handle supplier creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/config.php';
    
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create') {
                $name = $_POST['name'];
                $phone = $_POST['phone'];
                $email = $_POST['email'];
                $contact_person = $_POST['contact_person'];
                $address = $_POST['address'];

                $stmt = $pdo->prepare("INSERT INTO suppliers (name, phone, email, contact_person, address) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $phone, $email, $contact_person, $address]);
                
                $_SESSION['message'] = 'Supplier has been successfully created.';
                $_SESSION['message_type'] = 'success';
            } elseif ($_POST['action'] === 'update') {
                $id = $_POST['id'];
                $name = $_POST['name'];
                $phone = $_POST['phone'];
                $email = $_POST['email'];
                $contact_person = $_POST['contact_person'];
                $address = $_POST['address'];
                
                $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, phone = ?, email = ?, contact_person = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $email, $contact_person, $address, $id]);
                
                $_SESSION['message'] = 'Supplier has been successfully updated.';
                $_SESSION['message_type'] = 'success';
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                
                // Check if there are tours using this supplier
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tours WHERE supplier_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['message'] = 'Cannot delete supplier because there are tours that use it.';
                    $_SESSION['message_type'] = 'error';
                    header('Location: suppliers.php');
                    exit;
                }
                
                $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
                $stmt->execute([$id]);
                
                $_SESSION['message'] = 'Supplier has been successfully deleted.';
                $_SESSION['message_type'] = 'success';
            }
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = 'An error occurred: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: suppliers.php');
    exit;
}

require_once '../includes/header.php';
require_once '../includes/config.php';

// Get all suppliers
$stmt = $pdo->query("SELECT * FROM suppliers ORDER BY name");
$suppliers = $stmt->fetchAll();
?>

<div class="admin-container">
    <h2>Suppliers Management</h2>
    
    <!-- Form for new supplier -->
    <div class="form-section">
        <h3>New Supplier</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label for="name">Supplier name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone:</label>
                <input type="tel" id="phone" name="phone">
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email">
            </div>
            <div class="form-group">
                <label for="contact_person">Contact person:</label>
                <input type="text" id="contact_person" name="contact_person">
            </div>
            <div class="form-group">
                <label for="address">Address:</label>
                <textarea id="address" name="address"></textarea>
            </div>
            <button type="submit">Add Supplier</button>
        </form>
    </div>

    <!-- Suppliers list -->
    <div class="list-section">
        <h3>Existing Suppliers</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Contact person</th>
                    <th>Address</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $supplier): ?>
                    <tr>
                        <td><?php echo $supplier['id']; ?></td>
                        <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                        <td><?php echo htmlspecialchars($supplier['phone'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($supplier['email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($supplier['contact_person'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></td>
                        <td>
                            <button class="btn-edit" onclick="editSupplier(<?php echo $supplier['id']; ?>)">Edit</button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $supplier['id']; ?>">
                                <button type="submit" class="btn-delete" onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal for editing supplier -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Supplier</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label for="edit_name">Supplier name:</label>
                <input type="text" id="edit_name" name="name" required>
            </div>
            <div class="form-group">
                <label for="edit_phone">Phone:</label>
                <input type="tel" id="edit_phone" name="phone">
            </div>
            <div class="form-group">
                <label for="edit_email">Email:</label>
                <input type="email" id="edit_email" name="email">
            </div>
            <div class="form-group">
                <label for="edit_contact_person">Contact person:</label>
                <input type="text" id="edit_contact_person" name="contact_person">
            </div>
            <div class="form-group">
                <label for="edit_address">Address:</label>
                <textarea id="edit_address" name="address"></textarea>
            </div>
            <button type="submit">Save Changes</button>
        </form>
    </div>
</div>

<script>
function editSupplier(id) {
    const modal = document.getElementById('editModal');
    const supplier = <?php echo json_encode($suppliers); ?>.find(s => s.id === id);
    
    document.getElementById('edit_id').value = supplier.id;
    document.getElementById('edit_name').value = supplier.name;
    document.getElementById('edit_phone').value = supplier.phone || '';
    document.getElementById('edit_email').value = supplier.email || '';
    document.getElementById('edit_contact_person').value = supplier.contact_person || '';
    document.getElementById('edit_address').value = supplier.address || '';
    
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