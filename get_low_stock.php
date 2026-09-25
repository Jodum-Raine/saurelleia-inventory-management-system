<?php
session_start();
include 'db.php';

if(!isset($_SESSION['user'])){
    echo json_encode([]);
    exit();
}

$threshold = 6;
$query = "SELECT id, product_name as name, quantity FROM products WHERE quantity < $threshold ORDER BY quantity ASC";
$result = mysqli_query($conn, $query);

$low_stock = [];
while($row = mysqli_fetch_assoc($result)) {
    $low_stock[] = $row;
}

echo json_encode($low_stock);
?>