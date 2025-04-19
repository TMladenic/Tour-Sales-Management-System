<?php

function isTourArchived($pdo, $tourId) {
    $stmt = $pdo->prepare("SELECT archived FROM tours WHERE id = :id");
    $stmt->execute(['id' => $tourId]);
    $result = $stmt->fetch();

    if ($result) {
        return $result['archived'] == 1;
    }

    return false; // or throw exception, depending on what you want
}

function checkArchivedTour($pdo) {
    if (!isset($_SESSION['current_tour_id'])) {
        return false;
    }
    
    if (isTourArchived($pdo, $_SESSION['current_tour_id'])) {
        $_SESSION['message'] = 'This tour is archived. You can only view data.';
        $_SESSION['message_type'] = 'warning';
        return true;
    }
    return false;
}

function getCurrentTour($pdo) {
    if (!isset($_SESSION['current_tour_id'])) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM tours WHERE id = ?");
    $stmt->execute([$_SESSION['current_tour_id']]);
    return $stmt->fetch();
}
?> 