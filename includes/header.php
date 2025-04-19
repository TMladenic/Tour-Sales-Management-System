<!-- Author: Toni Mladenic (tonimladenic@gmail.com) -->
<?php
// session_start() removed as it's handled in config.php

require_once __DIR__ . '/config.php'; // if you use config.php for connection
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

// Sigurno pozivanje funkcije
if (isset($_SESSION['current_tour_id']) && function_exists('isTourArchived')) {
    $isArchived = isTourArchived($pdo, $_SESSION['current_tour_id']);
} else {
    $isArchived = false;
}

// Provjera je li tura arhivirana
$isArchived = false;
if (isset($_SESSION['current_tour_id']) && function_exists('isTourArchived')) {
    $isArchived = isTourArchived($pdo, $_SESSION['current_tour_id']);
}




// TEMPORARY DEBUGGING
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "<b>ERROR [$errno]</b> $errstr in <code>$errfile</code> on line <b>$errline</b><br>";
});
set_exception_handler(function($e) {
    echo "<b>EXCEPTION:</b> " . $e->getMessage() . "<br>";
    echo "<pre>";
    print_r($e->getTrace());
    echo "</pre>";
});


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Provjeri postoji li redirekcija u session-u
if (isset($_SESSION['redirect'])) {
    $redirect = $_SESSION['redirect'];
    unset($_SESSION['redirect']);
    header('Location: ' . $redirect);
    exit;
}

try {
    // Get current user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();

    // Get current tour if set
    $currentTour = null;
    if (isset($_SESSION['current_tour_id'])) {
        $stmt = $pdo->prepare("
            SELECT t.*, s.name as supplier_name 
            FROM tours t 
            LEFT JOIN suppliers s ON t.supplier_id = s.id 
            WHERE t.id = ?
        ");
        $stmt->execute([$_SESSION['current_tour_id']]);
        $currentTour = $stmt->fetch();
    }

    // Get all tours for navigation
    $tours = [];
    try {
        $stmt = $pdo->query("
            SELECT t.*, s.name as supplier_name 
            FROM tours t 
            LEFT JOIN suppliers s ON t.supplier_id = s.id 
            ORDER BY t.start_date DESC
        ");
        $tours = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching tours: " . $e->getMessage());
    }

    // Dohvati bilješke
    $notes = [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM notes WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $notes = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching notes: " . $e->getMessage());
    }
} catch (PDOException $e) {
    error_log("Error in header.php: " . $e->getMessage());
    // Continue with empty data
    $currentUser = null;
    $currentTour = null;
    $tours = [];
    $notes = [];
}

// Odredi putanju do root direktorija
$rootPath = '';
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $rootPath = '../';
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop</title>
    <link rel="stylesheet" href="<?php echo $rootPath; ?>assets/css/main.css">
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <link rel="stylesheet" href="<?php echo $rootPath; ?>assets/css/admin.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $rootPath; ?>assets/css/dark-mode.css">
    <style>
        :root {
            --primary-color: #2c2c2c;
            --secondary-color: #1a1a1a;
            --button-danger: #dc3545;
            --button-primary: #2196F3;
            --button-primary-hover: #1976D2;
        }

        /* Mobile-first styles */
        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-radius: 0;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
            padding: 0;
            background-color: var(--primary-color);
        }

        .mobile-menu-btn {
            display: block;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 1rem;
            margin-right: 1rem;
            position: relative;
            width: 50px;
            height: 40px;
            z-index: 1002;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mobile-menu-btn span {
            display: block;
            position: absolute;
            height: 2px;
            width: 30px;
            background: white;
            border-radius: 2px;
            transition: all 0.3s ease-in-out;
        }

        .mobile-menu-btn span:nth-child(1) {
            top: 12px;
        }

        .mobile-menu-btn span:nth-child(2) {
            top: 20px;
        }

        .mobile-menu-btn span:nth-child(3) {
            top: 28px;
        }

        .mobile-menu-btn.active span:nth-child(1) {
            transform: rotate(45deg);
            top: 20px;
        }

        .mobile-menu-btn.active span:nth-child(2) {
            opacity: 0;
        }

        .mobile-menu-btn.active span:nth-child(3) {
            transform: rotate(-45deg);
            top: 20px;
        }

        .main-nav {
            position: fixed;
            top: 60px;
            left: -250px;
            width: 250px;
            height: calc(100vh - 60px);
            background-color: var(--primary-color);
            padding: 1rem;
            box-shadow: 2px 0 5px rgba(0,0,0,0.2);
            overflow-y: auto;
            z-index: 1001;
            transition: left 0.3s ease;
            display: block;
        }

        .main-nav.active {
            left: 0;
        }

        .nav-overlay {
            position: fixed;
            top: 60px;
            left: 0;
            width: 100%;
            height: calc(100vh - 60px);
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease;
        }

        .nav-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .main-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .main-nav li {
            margin: 0.5rem 0;
            opacity: 0;
            transform: translateX(-20px);
            transition: all 0.3s ease;
        }

        .main-nav.active li {
            opacity: 1;
            transform: translateX(0);
        }

        .main-nav li:nth-child(1) { transition-delay: 0.1s; }
        .main-nav li:nth-child(2) { transition-delay: 0.15s; }
        .main-nav li:nth-child(3) { transition-delay: 0.2s; }
        .main-nav li:nth-child(4) { transition-delay: 0.25s; }
        .main-nav li:nth-child(5) { transition-delay: 0.3s; }
        .main-nav li:nth-child(6) { transition-delay: 0.35s; }
        .main-nav li:nth-child(7) { transition-delay: 0.4s; }
        .main-nav li:nth-child(8) { transition-delay: 0.45s; }
        .main-nav li:nth-child(9) { transition-delay: 0.5s; }
        .main-nav li:nth-child(10) { transition-delay: 0.55s; }
        .main-nav li:nth-child(11) { transition-delay: 0.6s; }
        .main-nav li:nth-child(12) { transition-delay: 0.65s; }

        .main-nav a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 0.8rem;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .main-nav a:hover {
            background-color: var(--secondary-color);
        }

        .mobile-only {
            display: block;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .mobile-only .password-btn,
        .mobile-only .logout-btn {
            display: block;
            width: 100%;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .mobile-only .password-btn {
            margin-bottom: 1rem;
        }

        .mobile-only span {
            display: block;
            margin-bottom: 1rem;
            color: white;
            font-weight: 500;
        }

        /* Desktop styles */
        @media screen and (min-width: 768px) {
            .header-content {
                max-width: 1200px;
                margin: 0 auto;
            }

            .user-menu {
                display: flex;
                align-items: center;
                gap: 1rem;
                padding-right: 1rem;
            }

            .user-menu span {
                margin-right: 1rem;
            }

            .password-btn, .logout-btn {
                padding: 0.8rem 1.5rem;
                font-size: 1rem;
                margin-left: 0.5rem;
            }

            .mobile-only {
                display: none !important;
            }
        }

        @media screen and (max-width: 767px) {
            .user-menu {
                display: none;
            }
        }

        .content {
            margin-top: 60px;
            padding: 1rem;
        }

        .current-tour {
            color: white;
            font-size: 0.9rem;
            margin-right: 1rem;
        }

        .logout-btn {
            background-color: var(--button-danger);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .password-btn {
            background-color: var(--button-primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .password-btn:hover {
            background-color: var(--button-primary-hover);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <a href="<?php echo $rootPath; ?>index.php" class="logo">Shop</a>
            <?php if ($currentTour): ?>
                <div class="current-tour">
                    <?php echo htmlspecialchars($currentTour['name']); ?>
                </div>
            <?php endif; ?>
            <div class="user-menu">
                <?php if ($currentUser): ?>
                    <span><?php echo htmlspecialchars($currentUser['username']); ?></span>
                <?php endif; ?>
                <button id="darkModeToggle" class="dark-mode-toggle">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="change_password.php" class="password-btn">Promjena lozinke</a>
                <a href="../logout.php" class="logout-btn">Odjava</a>
            </div>
        </div>
    </div>

    <div class="nav-overlay" id="navOverlay"></div>
    <nav class="main-nav" id="mainNav">
        <ul>
            <li><a href="<?php echo $rootPath; ?>index.php">Home</a></li>
            <?php if (isset($_SESSION['current_tour_id'])): ?>
                <li><a href="<?php echo $rootPath; ?>statistics.php">Statistika</a></li>
            <?php endif; ?>
            <?php if (!isTourArchived($pdo, $_SESSION['current_tour_id'] ?? null)): ?>
                <li><a href="<?php echo $rootPath; ?>sales.php">Nova Prodaja</a></li>
                <li><a href="<?php echo $rootPath; ?>waiting_list.php">Waiting List</a></li>
                <li><a href="<?php echo $rootPath; ?>expenses.php">Expenses</a></li>
                <li><a href="<?php echo $rootPath; ?>notes.php">Notes</a></li>
            <?php endif; ?>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a href="<?php echo $rootPath; ?>admin/tours.php">Ture</a></li>
                <li><a href="<?php echo $rootPath; ?>admin/products.php">Proizvodi</a></li>
                <li><a href="<?php echo $rootPath; ?>admin/salespeople.php">Salespeople</a></li>
                <li><a href="<?php echo $rootPath; ?>admin/suppliers.php">Suppliers</a></li>
                <li><a href="<?php echo $rootPath; ?>admin/expense_categories.php">Expense Categories</a></li>
                <li><a href="<?php echo $rootPath; ?>admin/users.php">Korisnici</a></li>
                <li><a href="<?php echo $rootPath; ?>admin/investments.php">Ulaganja i Dobit</a></li>
            <?php endif; ?>
            <li class="mobile-only">
                <?php if ($currentUser): ?>
                    <span><?php echo htmlspecialchars($currentUser['username']); ?></span>
                <?php endif; ?>
                <a href="change_password.php" class="password-btn">Promjena lozinke</a>
                <a href="../logout.php" class="logout-btn">Odjava</a>
            </li>
        </ul>
    </nav>

    <div class="content">
        <main>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mainNav = document.getElementById('mainNav');
            const navOverlay = document.getElementById('navOverlay');

            if (mobileMenuBtn && mainNav && navOverlay) {
                function toggleMenu(event) {
                    if (event) {
                        event.preventDefault();
                    }
                    mobileMenuBtn.classList.toggle('active');
                    mainNav.classList.toggle('active');
                    navOverlay.classList.toggle('active');
                }

                function closeMenu() {
                    mobileMenuBtn.classList.remove('active');
                    mainNav.classList.remove('active');
                    navOverlay.classList.remove('active');
                }

                mobileMenuBtn.addEventListener('click', toggleMenu);
                navOverlay.addEventListener('click', closeMenu);

                // Zatvori meni kada se klikne na link
                const navLinks = mainNav.querySelectorAll('a');
                navLinks.forEach(link => {
                    link.addEventListener('click', closeMenu);
                });

                // Zatvori meni na Escape tipku
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && mainNav.classList.contains('active')) {
                        closeMenu();
                    }
                });

                // Zatvori meni kada se klikne izvan njega
                document.addEventListener('click', function(event) {
                    const isClickInsideMenu = mainNav.contains(event.target);
                    const isClickOnButton = mobileMenuBtn.contains(event.target);
                    
                    if (!isClickInsideMenu && !isClickOnButton && mainNav.classList.contains('active')) {
                        closeMenu();
                        event.stopPropagation(); // Prevent event from bubbling up
                    }
                }, true); // Use capture phase to handle the event first
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const darkModeToggle = document.getElementById('darkModeToggle');
            const body = document.body;
            
            // Check for saved dark mode preference
            if (localStorage.getItem('darkMode') === 'true') {
                body.classList.add('dark-mode');
                darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            }
            
            darkModeToggle.addEventListener('click', function() {
                body.classList.toggle('dark-mode');
                const isDarkMode = body.classList.contains('dark-mode');
                localStorage.setItem('darkMode', isDarkMode);
                darkModeToggle.innerHTML = isDarkMode ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            });
        });
    </script>

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
</body>
</html> 