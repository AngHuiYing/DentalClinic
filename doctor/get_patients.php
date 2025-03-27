<?php
header('Content-Type: application/json');
include '../includes/db.php';
session_start();

$doctor_id = $_SESSION['user_id'];

$sql = "SELECT DISTINCT u.id, u.name 
        FROM users u 
        JOIN chat_messages c ON (u.id = c.sender_id OR u.id = c.receiver_id)
        WHERE (c.sender_id = ? OR c.receiver_id = ?) AND u.role = 'patient'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $doctor_id, $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

$patients = [];
while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}

echo json_encode($patients, JSON_UNESCAPED_UNICODE);
?>
