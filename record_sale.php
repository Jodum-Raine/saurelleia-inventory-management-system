<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: sales.php");
    exit();
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

// Validate
if ($product_id <= 0 || $quantity <= 0) {
    $_SESSION['error_message'] = "Invalid product or quantity.";
    header("Location: sales.php");
    exit();
}

// Fetch product
$stmt = $conn->prepare("SELECT product_name, quantity, price FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    $_SESSION['error_message'] = "Product not found.";
    header("Location: sales.php");
    exit();
}

if ($quantity > $product['quantity']) {
    $_SESSION['error_message'] = "Not enough stock. Only {$product['quantity']} available.";
    header("Location: sales.php");
    exit();
}

$price_per_unit = (float)$product['price'];
$total = $price_per_unit * $quantity;
$product_name = $product['product_name'];

// Begin transaction
$conn->begin_transaction();

try {
   
    $stmt = $conn->prepare("INSERT INTO sales (product_id, product_name, quantity, price_per_unit, total_amount, sold_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isiddi", $product_id, $product_name, $quantity, $price_per_unit, $total, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();

    // 2. Deduct from inventory
    $stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
    $stmt->bind_param("ii", $quantity, $product_id);
    $stmt->execute();
    $stmt->close();

    // Commit
    $conn->commit();

    // Log activity
    require_once 'functions.php';
    logActivity($_SESSION['user_id'], "Record Sale", "Sold $quantity x $product_name for ₱" . number_format($total, 2));

    $_SESSION['success_message'] = "Sale recorded! ₱" . number_format($total, 2) . " earned. Inventory deducted.";
    header("Location: sales.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "Error recording sale: " . $e->getMessage();
    header("Location: sales.php");
    exit();
}
?>