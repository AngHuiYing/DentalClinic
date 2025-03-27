<?php
include '../includes/db.php';
session_start();

$sender_id = $_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($sender_id === 0 || $receiver_id === 0 || empty($message)) {
    echo json_encode(["status" => "error", "message" => "Invalid sender, receiver, or empty message"]);
    exit;
}

$sql = "INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $sender_id, $receiver_id, $message);
$stmt->execute();

echo json_encode(["status" => "success"]);
?>
