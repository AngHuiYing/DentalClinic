<?php
include 'includes/db.php';
session_start();

$sender_id = $_SESSION['user_id'] ?? 0;
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($sender_id === 0 || $receiver_id === 0 || empty($message)) {
    echo json_encode(["status" => "error", "message" => "Invalid sender, receiver, or empty message"]);
    exit;
}

// 🔍 检查 `receiver_id` 是否在 `users` 表里
$check_user = $conn->prepare("SELECT id FROM users WHERE id = ?");
$check_user->bind_param("i", $receiver_id);
$check_user->execute();
$check_user->store_result();
if ($check_user->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Receiver ID not found in users table"]);
    exit;
}

$sql = "INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $sender_id, $receiver_id, $message);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to send"]);
}

?>