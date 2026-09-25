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

$sale_id = isset($_POST['sale_id']) ? (int)$_POST['sale_id'] : 0;
$new_quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
$price_per_unit = isset($_POST['price_per_unit']) ? (float)$_POST['price_per_unit'] : 0;

if ($sale_id <= 0 || $new_quantity <= 0) {
    $_SESSION['error_message'] = "Invalid input.";
    header("Location: sales.php");
    exit();
}

// Fetch the sale
$stmt = $conn->prepare("SELECT product_id, product_name, quantity FROM sales WHERE id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();
$sale = $result->fetch_assoc();
$stmt->close();

if (!$sale) {
    $_SESSION['error_message'] = "Sale not found.";
    header("Location: sales.php");
    exit();
}

$old_quantity = $sale['quantity'];
$difference = $new_quantity - $old_quantity;
$new_total = $price_per_unit * $new_quantity;

// If new quantity > old, we need more stock
if ($difference > 0) {
    $stmt = $conn->prepare("SELECT quantity FROM products WHERE id = ?");
    $stmt->bind_param("i", $sale['product_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();

    if (!$product || $difference > $product['quantity']) {
        $_SESSION['error_message'] = "Not enough stock. Only " . ($product['quantity'] ?? 0) . " more available.";
        header("Location: sales.php");
        exit();
    }
}

$conn->begin_transaction();

try {
    // 1. Update the sale record
    $stmt = $conn->prepare("UPDATE sales SET quantity = ?, total_amount = ? WHERE id = ?");
    $stmt->bind_param("idi", $new_quantity, $new_total, $sale_id);
    $stmt->execute();
    $stmt->close();

    // 2. Adjust inventory by the DIFFERENCE
    // If difference > 0: product sold more, so deduct more
    // If difference < 0: product sold less, so return some to stock
    $stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
    $stmt->bind_param("ii", $difference, $sale['product_id']);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    require_once 'functions.php';
    logActivity($_SESSION['user_id'], "Edit Sale", "Updated sale #$sale_id from $old_quantity to $new_quantity units");

    $_SESSION['success_message'] = "Sale updated! Quantity changed from $old_quantity to $new_quantity.";
    header("Location: sales.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "Error updating sale: " . $e->getMessage();
    header("Location: sales.php");
    exit();
}
?>