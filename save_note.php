<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$content = isset($_POST['content']) ? $_POST['content'] : '';

if (empty($title)) {
    echo json_encode(['success' => false, 'error' => 'Title is required']);
    exit();
}

if ($id > 0) {
    // Update existing note (verify ownership)
    $check = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $id, $user_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows == 0) {
        echo json_encode(['success' => false, 'error' => 'Note not found']);
        exit();
    }
    $check->close();

    $stmt = $conn->prepare("UPDATE notes SET title = ?, content = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ssii", $title, $content, $id, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'id' => $id]);
} else {
    // Insert new note
    $stmt = $conn->prepare("INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $title, $content);
    $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();

    echo json_encode(['success' => true, 'id' => $new_id]);
}
?>