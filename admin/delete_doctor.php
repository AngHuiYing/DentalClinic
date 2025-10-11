<?php
session_start();
include '../includes/db.php';

// 确保管理员已登录
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 确保获取医生 ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_doctors.php");
    exit;
}

$doctor_id = $_GET['id'];

// 获取医生信息
$stmt = $conn->prepare("SELECT image FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: manage_doctors.php");
    exit;
}

$doctor = $result->fetch_assoc();

// 删除头像文件（如果存在）
if (!empty($doctor['image']) && file_exists("../" . $doctor['image'])) {
    unlink("../" . $doctor['image']);
}

// 删除医生记录
$stmt = $conn->prepare("DELETE FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);

if ($stmt->execute()) {
    echo "<script>alert('Doctor deleted successfully!'); window.location.href='manage_doctors.php';</script>";
} else {
    echo "<script>alert('Failed to delete doctor.'); window.location.href='manage_doctors.php';</script>";
}
?>
