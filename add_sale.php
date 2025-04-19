<?php

try {
    // Provjeri dostupnu količinu
    $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
    $stmt->execute([$_POST['product_id']]);
    $product = $stmt->fetch();
    
    if ($product['stock_quantity'] < $_POST['quantity']) {
        echo json_encode(['success' => false, 'message' => 'Nema dovoljno proizvoda na zalihi']);
        exit;
    }
    
    // Unesi prodaju
    $stmt = $pdo->prepare("INSERT INTO sales (tour_id, product_id, salesperson_id, quantity, price, discount, discount_type, discount_reason, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['tour_id'],
        $_POST['product_id'],
        $_POST['salesperson_id'],
        $_POST['quantity'],
        $_POST['price'],
        $_POST['discount'],
        $_POST['discount_type'],
        $_POST['discount_reason'],
        $_POST['notes']
    ]);
    
    // Ažuriraj zalihe
    $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
    $stmt->execute([$_POST['quantity'], $_POST['product_id']]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 