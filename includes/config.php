<?php
// Database configuration
if (!defined('DB_HOST')) define('DB_HOST', 'host');
if (!defined('DB_NAME')) define('DB_NAME', 'name');
if (!defined('DB_USER')) define('DB_USER', 'user');
if (!defined('DB_PASS')) define('DB_PASS', 'pass');
if (!defined('DB_PORT')) define('DB_PORT', 'port');

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
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default timezone
date_default_timezone_set('Europe/Zagreb');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1); 