<?php
session_start();
include "../db.php";

// 检查是否已登录
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: ../patient/login.php");
    exit;
}

$patient_id = $_SESSION['user_id'];

// 检查是否传入预约 ID
if (!isset($_GET['id'])) {
    echo "<script>alert('Invalid request.'); window.location.href = 'appointments.php';</script>";
    exit();
}

$appointment_id = intval($_GET['id']);

// 检查这个预约是否属于当前用户，且状态为 pending
$sql = "SELECT * FROM appointments WHERE id = ? AND patient_id = ? AND status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $appointment_id, $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('You are not authorized to cancel this appointment or it is already processed.'); window.location.href = 'appointments.php';</script>";
    exit();
}

// 执行取消预约（你可以选择删除或更改状态，这里我们更改状态）
$cancel_sql = "DELETE FROM appointments WHERE id = ?";
$cancel_stmt = $conn->prepare($cancel_sql);
$cancel_stmt->bind_param("i", $appointment_id);

if ($cancel_stmt->execute()) {
    echo "<script>alert('Appointment cancelled successfully.'); window.location.href = 'my_appointments.php';</script>";
} else {
    echo "<script>alert('Failed to cancel appointment.'); window.location.href = 'my_appointments.php';</script>";
}
?>
