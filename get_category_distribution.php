<?php
session_start();
include 'db.php';

if(!isset($_SESSION['user'])){
    echo json_encode([]);
    exit();
}

$query = "SELECT category, COUNT(*) as count, SUM(quantity * price) as total_value 
          FROM products 
          GROUP BY category 
          ORDER BY count DESC";
$result = mysqli_query($conn, $query);

$data = [];
$total = 0;

// First pass: total count
while($row = mysqli_fetch_assoc($result)) {
    $total += $row['count'];
    $data[] = $row;
}

// Add percentage
foreach($data as &$row) {
    $row['percentage'] = $total > 0 ? round(($row['count'] / $total) * 100) : 0;
}
unset($row);

echo json_encode($data);
?>