<?php
session_start();
require_once 'config/database.php';

// Ako je korisnik već prijavljen, preusmjeri ga na početnu stranicu
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Debug: Ispiši unesene podatke
    error_log("Pokušaj prijave - Username: " . $username);

    try {
        // Dohvati korisnika iz baze
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Debug: Ispiši podatke iz baze
        error_log("Pronađeni korisnik: " . print_r($user, true));

        if ($user && password_verify($password, $user['password'])) {
            // Debug: Ispiši uspješnu provjeru lozinke
            error_log("Lozinka je ispravna");

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Debug: Ispiši session podatke prije preusmjeravanja
            error_log("Session data before redirect: " . print_r($_SESSION, true));

            // Log activity
            try {
                $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'login', 'User logged in')");
                $stmt->execute([$user['id']]);
                error_log("Activity logged successfully");
            } catch (PDOException $e) {
                error_log("Error logging activity: " . $e->getMessage());
            }

            // Debug: Provjeri postoji li redirekcija
            if (isset($_SESSION['redirect'])) {
                $redirect = $_SESSION['redirect'];
                unset($_SESSION['redirect']);
                header('Location: ' . $redirect);
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            // Debug: Ispiši neuspješnu prijavu
            error_log("Neuspješna prijava - Provjera lozinke nije uspjela");
            $error = 'Pogrešno korisničko ime ili lozinka!';
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error = 'Došlo je do greške pri prijavi. Molimo pokušajte ponovno.';
    }
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Shop</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/dark-mode.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .login-container {
            background-color: var(--card-background);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--border-color);
            position: relative;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            color: var(--text-color);
            margin: 0;
            font-size: 1.8rem;
        }

        .login-header h2 {
            color: var(--text-color);
            margin: 0;
            font-size: 1.2rem;
        }

        .login-form {
            display: grid;
            gap: 1rem;
        }

        .form-group {
            display: grid;
            gap: 0.5rem;
        }

        .form-group label {
            font-weight: 500;
            color: var(--text-color);
        }

        .form-group input {
            padding: 0.75rem;
            border: 1px solid var(--input-border);
            border-radius: 4px;
            font-size: 1rem;
            background-color: var(--input-background);
            color: var(--input-text);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .login-button {
            background-color: var(--button-primary);
            color: white;
            padding: 0.75rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .login-button:hover {
            background-color: var(--button-primary-hover);
        }

        .error-message {
            background-color: var(--message-error-bg);
            color: var(--error-color);
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border: 1px solid var(--message-error-border);
        }

        .dark-mode-toggle {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: var(--text-color);
            cursor: pointer;
            font-size: 1.2em;
        }

        .dark-mode-toggle:hover {
            opacity: 0.8;
        }

        .dark-mode-icon {
            display: inline-block;
            width: 20px;
            height: 20px;
            text-align: center;
        }

        /* Dark Mode Active State */
        body.dark-mode .dark-mode-icon i.fa-sun {
            display: none;
        }

        body.dark-mode .dark-mode-icon i.fa-moon {
            display: inline-block;
        }

        body:not(.dark-mode) .dark-mode-icon i.fa-sun {
            display: inline-block;
        }

        body:not(.dark-mode) .dark-mode-icon i.fa-moon {
            display: none;
        }
    </style>
</head>
<body class="<?php echo isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark-mode' : ''; ?>">
    <div class="login-container">
        <button id="darkModeToggle" class="dark-mode-toggle">
            <i class="fas fa-moon"></i>
        </button>
        <div class="login-header">
            <h1>Shop</h1>
            <h2>Login</h2>
        </div>
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <form class="login-form" method="POST" action="">
            <div class="form-group">
                <label for="username">Korisničko ime:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Lozinka:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="login-button">Prijavi se</button>
        </form>
    </div>

    <script>
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const isDarkMode = document.body.classList.contains('dark-mode');
            localStorage.setItem('darkMode', isDarkMode);
            const darkModeToggle = document.getElementById('darkModeToggle');
            darkModeToggle.innerHTML = isDarkMode ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        }
    </script>
</body>
</html> 