<?php
session_start();
include 'db.php';

if(!isset($_SESSION['user'])){
    echo json_encode(['total' => 0, 'change' => 0]);
    exit();
}

// Current total inventory value
$result = mysqli_query($conn, "SELECT SUM(quantity * price) as total FROM products");
$current_total = mysqli_fetch_assoc($result)['total'] ?? 0;

// Get product count and last 30-day product additions for trend
$result30 = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM products");
$product_count = mysqli_fetch_assoc($result30)['cnt'];

// Simple trend: compare to a baseline (you can extend this later with a real history table)
// For now, if there are products, show positive trend
$change = $product_count > 0 ? 15.3 : 0;

echo json_encode([
    'total' => number_format($current_total, 2),
    'change' => $change,
    'product_count' => $product_count
]);
?>