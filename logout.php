<?php
session_start();
require_once 'config/database.php';

try {
    if (isset($_SESSION['user_id'])) {
        // Log activity before destroying session
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'logout', 'User logged out')");
        $stmt->execute([$_SESSION['user_id']]);
    }

    // Destroy session
    session_destroy();

    // Redirect to login page
    header('Location: login.php');
    exit;
} catch (PDOException $e) {
    error_log("Error during logout: " . $e->getMessage());
    // Even if logging fails, still destroy session and redirect
    session_destroy();
    header('Location: login.php');
    exit;
} 