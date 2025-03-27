<?php
include 'includes/db.php'; // 修正路径
session_start();

$receiver_id = isset($_GET['receiver_id']) ? intval($_GET['receiver_id']) : 0;
$last_message_id = isset($_GET['last_message_id']) ? intval($_GET['last_message_id']) : 0;

if (!$receiver_id) {
    echo json_encode(["error" => "Invalid receiver ID"]);
    exit;
}

$sql = "SELECT * FROM chat_messages 
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) 
        ORDER BY timestamp ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $_SESSION['user_id'], $receiver_id, $receiver_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode($messages);
?>
