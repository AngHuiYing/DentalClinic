<?php
session_start();
include "../db.php"; // 连接数据库

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$doctor_id = isset($_GET['doctor']) ? intval($_GET['doctor']) : 0;

// 获取医生信息
$sql = "SELECT * FROM doctors WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    echo "Doctor not found!";
    exit();
}

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];

    $sql = "INSERT INTO appointments (user_id, doctor_id, appointment_date, appointment_time, status) 
            VALUES (?, ?, ?, ?, 'Pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $user_id, $doctor_id, $appointment_date, $appointment_time);

    if ($stmt->execute()) {
        echo "<script>alert('Appointment booked successfully!'); window.location.href = 'appointments.php';</script>";
    } else {
        echo "<script>alert('Failed to book appointment. Please try again.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment</title>
</head>
<style>
    body {
    font-family: Arial, sans-serif;
    background-color: #f4f4f4;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
}

h2 {
    color: #333;
    margin-top: 20px;
}

.container {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    width: 50%;
    text-align: center;
}

form {
    margin-top: 20px;
}

label {
    font-weight: bold;
    display: block;
    margin: 10px 0 5px;
}

input[type="date"],
input[type="time"] {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

button {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 10px 15px;
    font-size: 16px;
    border-radius: 5px;
    cursor: pointer;
    width: 100%;
}

button:hover {
    background-color: #0056b3;
}

</style>
<body>
<?php include '../includes/navbar.php'; ?>

    <h2>Book Appointment with Dr. <?= htmlspecialchars($doctor['name']) ?></h2>
    
    <form method="POST">
        <label>Date:</label>
        <input type="date" name="appointment_date" required><br><br>

        <label>Time:</label>
        <input type="time" name="appointment_time" required><br><br>

        <button type="submit">Confirm Appointment</button>
    </form>

</body>
</html>
