<?php
session_start();
require_once '../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once '../includes/functions.php';

// Handle user creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create') {
                $username = $_POST['username'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $role = $_POST['role'];

                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->execute([$username, $password, $role]);
                
                $_SESSION['message'] = 'User has been successfully created.';
                $_SESSION['message_type'] = 'success';
            } elseif ($_POST['action'] === 'update') {
                $id = $_POST['id'];
                $username = $_POST['username'];
                $role = $_POST['role'];
                
                $sql = "UPDATE users SET username = ?, role = ?";
                $params = [$username, $role];
                
                if (!empty($_POST['password'])) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $_SESSION['message'] = 'User has been successfully updated.';
                $_SESSION['message_type'] = 'success';
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                
                $_SESSION['message'] = 'User has been successfully deleted.';
                $_SESSION['message_type'] = 'success';
            }
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = 'An error occurred: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: users.php');
    exit;
}

require_once '../includes/header.php';

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY username");
$users = $stmt->fetchAll();
?>

<div class="admin-container">
    <h2>User Management</h2>
    
    <!-- New user form -->
    <div class="form-section">
        <h3>New User</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="user">User</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <button type="submit">Add User</button>
        </form>
    </div>

    <!-- User list -->
    <div class="list-section">
        <h3>Existing Users</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo $user['role']; ?></td>
                        <td>
                            <button class="btn-edit" onclick="editUser(<?php echo $user['id']; ?>)">Edit</button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="btn-delete" onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit user modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit User</h3>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label for="edit_username">Username:</label>
                <input type="text" id="edit_username" name="username" required>
            </div>
            <div class="form-group">
                <label for="edit_password">New password (leave empty to keep unchanged):</label>
                <input type="password" id="edit_password" name="password">
            </div>
            <div class="form-group">
                <label for="edit_role">Role:</label>
                <select id="edit_role" name="role" required>
                    <option value="user">User</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <button type="submit">Save Changes</button>
        </form>
    </div>
</div>

<script>
function editUser(id) {
    const modal = document.getElementById('editModal');
    const user = <?php echo json_encode($users); ?>.find(u => u.id === id);
    
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_role').value = user.role;
    
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