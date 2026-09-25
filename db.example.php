<?php
// Copy this file to db.php and fill in your own values
$db_server = "localhost";
$db_user   = "root";
$db_pass   = "";
$db_name   = "inventory_db";

$conn = mysqli_connect($db_server, $db_user, $db_pass, $db_name);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");
?>