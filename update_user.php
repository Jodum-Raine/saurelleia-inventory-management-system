<?php
session_start();
include 'db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$username = trim($_POST['username']);
$email = trim($_POST['email']);
$role = $_POST['role'];

// Don't allow changing own role
if($user_id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'Cannot modify your own account']);
    exit();
}

// Check if username already exists for another user
$check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$check->bind_param("si", $username, $user_id);
$check->execute();
$check->store_result();
if($check->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'Username already exists']);
    exit();
}
$check->close();

// Check if email already exists for another user
$check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$check->bind_param("si", $email, $user_id);
$check->execute();
$check->store_result();
if($check->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'Email already exists']);
    exit();
}
$check->close();

$stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
$stmt->bind_param("sssi", $username, $email, $role, $user_id);

if($stmt->execute()){
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
}
$stmt->close();
?>