<?php
session_start();
include 'db.php';

if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id > 0){
    $stmt = $conn->prepare("DELETE FROM products WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    require_once 'functions.php';
    logActivity($_SESSION['user_id'], "Delete Product", "Deleted product ID $id");
    $stmt->close();
}

header("Location: index.php");
exit();
?>