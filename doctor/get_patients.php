<?php
include '../includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['doctor_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$doctor_id = $_SESSION['doctor_id'];

$sql = "SELECT DISTINCT users.id, users.name 
        FROM messages 
        JOIN users ON messages.sender_id = users.id 
        WHERE messages.receiver_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

$patients = [];
while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}

echo json_encode($patients);
exit;
?>
