<?php
session_start();
include 'db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: index.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id > 0){
    // Don't allow admin to delete themselves
    $check = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $check->bind_result($username);
    $check->fetch();
    
    if($username != $_SESSION['user']){
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        require_once 'functions.php';
        logActivity($_SESSION['user_id'], "Delete User", "Deleted user ID $id");
        $stmt->close();
        $_SESSION['success_message'] = "User deleted successfully!";
    } else {
        $_SESSION['error_message'] = "You cannot delete your own account!";
    }
    $check->close();
}

header("Location: admin_add_users.php");
exit();
?>