<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sale_id <= 0) {
    $_SESSION['error_message'] = "Invalid sale ID.";
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

$conn->begin_transaction();

try {
    // 1. Restock the product (add quantity back)
    $stmt = $conn->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
    $stmt->bind_param("ii", $sale['quantity'], $sale['product_id']);
    $stmt->execute();
    $stmt->close();

    // 2. Delete the sale record
    $stmt = $conn->prepare("DELETE FROM sales WHERE id = ?");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    require_once 'functions.php';
    logActivity($_SESSION['user_id'], "Delete Sale", "Deleted sale #$sale_id ({$sale['quantity']}x {$sale['product_name']}) — restocked");

    $_SESSION['success_message'] = "Sale deleted. {$sale['quantity']}x {$sale['product_name']} returned to inventory.";
    header("Location: sales.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "Error deleting sale: " . $e->getMessage();
    header("Location: sales.php");
    exit();
}
?>