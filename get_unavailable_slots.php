<?php
include "db.php";

header('Content-Type: application/json');

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';

$response = [
    "booked" => [],
    "unavailable" => []
];

if ($doctor_id && $date) {
    // 1️⃣ 已被預約的時間（只包含有效預約，排除已取消的）
    $sql = "SELECT appointment_time 
            FROM appointments
            WHERE doctor_id = ? 
              AND appointment_date = ?
              AND status NOT IN ('cancelled_by_patient', 'cancelled_by_admin', 'rejected', 'cancelled')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $doctor_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['booked'][] = $row['appointment_time'];
    }

    // 2️⃣ 醫生不可用時段
    $sql2 = "SELECT from_time, to_time 
             FROM unavailable_slots
             WHERE doctor_id = ? AND date = ?";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("is", $doctor_id, $date);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    while ($row = $result2->fetch_assoc()) {
        $response['unavailable'][] = $row;
    }
}

echo json_encode($response);
