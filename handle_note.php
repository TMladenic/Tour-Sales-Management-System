<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle note actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create') {
                $stmt = $pdo->prepare("
                    INSERT INTO notes (user_id, tour_id, content, created_by) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $_POST['tour_id'],
                    $_POST['content'],
                    $_SESSION['user_id']
                ]);
                
                $_SESSION['message'] = 'Note has been successfully added.';
                $_SESSION['message_type'] = 'success';
            } elseif ($_POST['action'] === 'update') {
                $stmt = $pdo->prepare("
                    UPDATE notes 
                    SET content = ? 
                    WHERE id = ? AND tour_id = ?
                ");
                $stmt->execute([
                    $_POST['content'],
                    $_POST['id'],
                    $_POST['tour_id']
                ]);
                
                $_SESSION['message'] = 'Note has been successfully updated.';
                $_SESSION['message_type'] = 'success';
            } elseif ($_POST['action'] === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND tour_id = ?");
                $stmt->execute([$_POST['id'], $_POST['tour_id']]);
                
                $_SESSION['message'] = 'Note has been successfully deleted.';
                $_SESSION['message_type'] = 'success';
            }
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: notes.php');
    exit;
}

// Return user to notes page
header('Location: notes.php');
exit;