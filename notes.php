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
    $_SESSION['message'] = 'This tour is archived. You can only view data.';
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
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes - Sales Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <link rel="stylesheet" href="assets/css/admin.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php
require_once 'includes/header.php';

// Get all notes for current tour
$stmt = $pdo->prepare("
    SELECT n.*, u.username as created_by_name 
    FROM notes n 
    LEFT JOIN users u ON n.created_by = u.id 
    WHERE n.tour_id = ? 
    ORDER BY n.created_at DESC
");
$stmt->execute([$_SESSION['current_tour_id']]);
$notes = $stmt->fetchAll();
?>

<div class="container">
    <h2>Notes<?php if ($currentTour): ?> - <?php echo htmlspecialchars($currentTour['name']); ?><?php endif; ?></h2>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message <?php echo $_SESSION['message_type']; ?>">
            <?php 
            echo htmlspecialchars($_SESSION['message']);
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Form for new note -->
    <div class="form-section">
        <h3>New Note</h3>
        <form method="POST" action="handle_note.php" class="form">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="tour_id" value="<?php echo $_SESSION['current_tour_id']; ?>">
            <div class="form-group">
                <label for="content">Content:</label>
                <textarea id="content" name="content" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Add Note</button>
        </form>
    </div>

    <!-- Notes list -->
    <div class="list-section">
        <h3>Notes List</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Content</th>
                    <th>Created By</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notes as $note): ?>
                    <tr>
                        <td><?php echo nl2br(htmlspecialchars($note['content'])); ?></td>
                        <td><?php echo htmlspecialchars($note['created_by_name']); ?></td>
                        <td><?php echo date('d.m.Y. H:i', strtotime($note['created_at'])); ?></td>
                        <td>
                            <button class="btn-edit" onclick="editNote(<?php echo $note['id']; ?>)">Edit</button>
                            <form method="POST" action="handle_note.php" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $note['id']; ?>">
                                <input type="hidden" name="tour_id" value="<?php echo $_SESSION['current_tour_id']; ?>">
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
        <h3>Edit Note</h3>
        <form method="POST" action="handle_note.php" class="admin-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="tour_id" value="<?php echo $_SESSION['current_tour_id']; ?>">
            <div class="form-group">
                <label for="edit_content">Content:</label>
                <textarea id="edit_content" name="content" rows="5" required></textarea>
            </div>
            <button type="submit">Save Changes</button>
        </form>
    </div>
</div>

<script>
function editNote(id) {
    const modal = document.getElementById('editModal');
    const note = <?php echo json_encode($notes); ?>.find(n => n.id === id);
    
    document.getElementById('edit_id').value = note.id;
    document.getElementById('edit_content').value = note.content;
    
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