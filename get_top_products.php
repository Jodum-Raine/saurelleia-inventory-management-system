<?php
session_start();
include 'db.php';

if(!isset($_SESSION['user'])){
    echo json_encode([]);
    exit();
}

$query = "SELECT product_name as name, quantity, price, (quantity * price) as total_value FROM products ORDER BY total_value DESC LIMIT 5";
$result = mysqli_query($conn, $query);

$products = [];
$rank = 1;
while($row = mysqli_fetch_assoc($result)) {
    $row['rank'] = $rank++;
    $row['value'] = number_format($row['price'], 2);
    $row['total_value'] = number_format($row['total_value'], 2);
    $products[] = $row;
}

echo json_encode($products);
?>