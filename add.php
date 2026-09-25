<?php
session_start();
include 'db.php';

// Only logged-in users can add products
if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}

$message = '';
$type = '';

if(isset($_POST['product_name'], $_POST['quantity'], $_POST['price'], $_POST['category'])){
    $product_name = trim($_POST['product_name']);
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];
    $category = $_POST['category'];
    
    // Validate input
    if(empty($product_name) || $quantity < 0 || $price < 0) {
        $message = "Invalid input. Please check your values.";
        $type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO products (product_name, quantity, price, category) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sids", $product_name, $quantity, $price, $category);

        if($stmt->execute()){
            require_once 'functions.php';
            logActivity($_SESSION['user_id'], "Add Product", "Added product: $product_name");
            
            $_SESSION['success_message'] = "Product added successfully!";
            header("Location: index.php");
            exit();
        } else {
            $message = "Error: " . $stmt->error;
            $type = "error";
        }

        $stmt->close();
    }
}

// If there's an error, redirect back with message
if($message) {
    $_SESSION['error_message'] = $message;
    header("Location: index.php");
    exit();
}
?>