<?php
date_default_timezone_set('Europe/Zagreb');

if (!defined('DB_HOST')) define('DB_HOST', 'host');     // Adresa MySQL servera
if (!defined('DB_USER')) define('DB_USER', 'user');               // MySQL korisničko ime
if (!defined('DB_PASS')) define('DB_PASS', 'password');                 // MySQL lozinka
if (!defined('DB_NAME')) define('DB_NAME', 'database_name'); // Ime baze podataka
if (!defined('DB_PORT')) define('DB_PORT', 'port');                       // Port MySQL servera

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Postavi vremensku zonu na MySQL konekciji
    $pdo->exec("SET time_zone = '+01:00'");
} catch(PDOException $e) {
    die("Greška pri povezivanju s bazom podataka: " . $e->getMessage());
}
?> 