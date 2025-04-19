<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user is admin
if (!isAdmin($pdo, $_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Get all archived tours
$stmt = $pdo->query("
    SELECT t.*, s.name as supplier_name 
    FROM tours t 
    LEFT JOIN suppliers s ON t.supplier_id = s.id 
    WHERE t.archived = 1
    ORDER BY t.start_date DESC
");
$archivedTours = $stmt->fetchAll();

// Get statistics for archived tours
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_archived_tours,
        SUM(
            (SELECT COUNT(*) FROM sales WHERE tour_id = t.id) as total_sales
        ) as total_sales_count,
        SUM(
            (SELECT SUM(quantity * price) FROM sales WHERE tour_id = t.id) as total_revenue
        ) as total_revenue,
        SUM(
            (SELECT SUM(quantity * price * discount/100) FROM sales WHERE tour_id = t.id) as total_discounts
        ) as total_discounts,
        SUM(
            (SELECT SUM(amount) FROM expenses WHERE tour_id = t.id) as total_expenses
        ) as total_expenses
    FROM tours t
    WHERE t.archived = 1
");
$archiveStats = $stmt->fetch();

// Update tour status (archiving/unarchiving)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['tour_id'])) {
    $tourId = $_POST['tour_id'];
    $action = $_POST['action'];
    
    if ($action === 'archive' || $action === 'unarchive') {
        $isArchived = $action === 'archive' ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE tours SET archived = ? WHERE id = ?");
        $stmt->execute([$isArchived, $tourId]);
        
        // Log action
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $action === 'archive' ? 'archive_tour' : 'unarchive_tour',
            "Tour ID: $tourId " . ($action === 'archive' ? 'archived' : 'unarchived')
        ]);
        
        header('Location: archived_tours.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive - Sales Tracking System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php require_once 'admin_header.php'; ?>

<div class="container">
    <h2>Tour Archive and Statistics</h2>

    <!-- Archive Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Archived Tours</h3>
            <div class="stat-value"><?php echo number_format($archiveStats['total_archived_tours']); ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Sales</h3>
            <div class="stat-value"><?php echo number_format($archiveStats['total_sales_count']); ?> pcs</div>
        </div>
        <div class="stat-card">
            <h3>Total Revenue</h3>
            <div class="stat-value"><?php echo number_format($archiveStats['total_revenue'], 2); ?> kn</div>
        </div>
        <div class="stat-card">
            <h3>Total Discounts</h3>
            <div class="stat-value"><?php echo number_format($archiveStats['total_discounts'], 2); ?> kn</div>
        </div>
        <div class="stat-card">
            <h3>Total Expenses</h3>
            <div class="stat-value"><?php echo number_format($archiveStats['total_expenses'], 2); ?> kn</div>
        </div>
        <div class="stat-card">
            <h3>Net Profit</h3>
            <div class="stat-value"><?php echo number_format($archiveStats['total_revenue'] - $archiveStats['total_expenses'], 2); ?> kn</div>
        </div>
    </div>

    <!-- Archived Tours List -->
    <div class="section">
        <h3>Archived Tours</h3>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Tour Name</th>
                        <th>Supplier</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archivedTours as $tour): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($tour['name']); ?></td>
                        <td><?php echo htmlspecialchars($tour['supplier_name']); ?></td>
                        <td><?php echo date('d.m.Y.', strtotime($tour['start_date'])); ?></td>
                        <td><?php echo date('d.m.Y.', strtotime($tour['end_date'])); ?></td>
                        <td>
                            <span class="status-badge archived">Archived</span>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="tour_id" value="<?php echo $tour['id']; ?>">
                                <input type="hidden" name="action" value="unarchive">
                                <button type="submit" class="btn btn-warning">Return to Active</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
</body>
</html> 