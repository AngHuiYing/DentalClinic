<?php
include '../includes/db.php';
session_start();

$doctor_id = $_SESSION['user_id'];
$patient_id = isset($_GET['receiver_id']) ? intval($_GET['receiver_id']) : 0;

if (!$patient_id) {
    echo json_encode(["error" => "Invalid patient ID"]);
    exit;
}

$sql = "SELECT * FROM chat_messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?) 
        ORDER BY timestamp ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $doctor_id, $patient_id, $patient_id, $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode($messages);
?>
