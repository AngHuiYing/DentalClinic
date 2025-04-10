<?php
include "../db.php";
$doctor_id = $_GET['doctor_id'];
$data = [];

// 1. 读取预约记录
$sql = "SELECT appointment_date, appointment_time FROM appointments WHERE doctor_id = ? AND status != 'rejected'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $key = $row['appointment_date'];
    if (!isset($data[$key])) $data[$key] = [];
    $data[$key][] = $row['appointment_time'];
}

// 2. 返回 JSON
header('Content-Type: application/json');
echo json_encode($data);
?>