<?php
session_start();
include "db.php"; // 连接数据库

// 检查用户是否登录
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: ../Hospital_Management_System/patient/login.php");
    exit;
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

    // 检查是否已有这个医生在该时间的预约
    $check_sql = "SELECT * FROM appointments 
                  WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'rejected'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        echo "<script>alert('This time slot is already booked. Please choose another one.');</script>";
    } else {
        // 没有冲突，可以插入
        $sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status) 
        VALUES (?, ?, ?, ?, 'Pending')";
        $patient_id = $_SESSION['user_id'];
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $patient_id, $doctor_id, $appointment_date, $appointment_time);

        if ($stmt->execute()) {
            echo "<script>alert('Appointment booked successfully!'); window.location.href = 'appointments.php';</script>";
        } else {
            echo "<script>alert('Failed to book appointment. Please try again.');</script>";
        }
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
<?php include '../Hospital_Management_System/includes/navbar.php'; ?>

    <h2>Book Appointment with Dr. <?= htmlspecialchars($doctor['name']) ?></h2>
    
    <form method="POST">
    <label>Date:</label>
<input type="date" name="appointment_date" id="appointment_date" required><br><br>

<label>Time:</label>
<select name="appointment_time" id="appointment_time" required>
    <option value="">Select time</option>
    <option value="09:00">09:00</option>
    <option value="09:30">09:30</option>
    <option value="10:00">10:00</option>
    <option value="10:30">10:30</option>
    <option value="11:00">11:00</option>
    <option value="11:30">11:30</option>
    <option value="14:00">14:00</option>
    <option value="14:30">14:30</option>
    <option value="15:00">15:00</option>
    <option value="15:30">15:30</option>
</select><br><br>

        <button type="submit">Confirm Appointment</button>
    </form>
    
    <script>
    function getMalaysiaTimeNow() {
    const now = new Date();
    const utc = now.getTime() + now.getTimezoneOffset() * 60000;
    return new Date(utc + (8 * 60 * 60000)); // GMT+8
}

const appointmentDate = document.getElementById("appointment_date");
const appointmentTime = document.getElementById("appointment_time");

function updateTimeOptions() {
    const selectedDate = new Date(appointmentDate.value);
    const malaysiaNow = getMalaysiaTimeNow();
    const options = appointmentTime.querySelectorAll("option");

    // 清除之前选择的时间
    appointmentTime.value = "";

    options.forEach(option => {
        if (option.value === "") return;

        const [hours, minutes] = option.value.split(":");
        const optionTime = new Date(selectedDate);
        optionTime.setHours(parseInt(hours));
        optionTime.setMinutes(parseInt(minutes));
        optionTime.setSeconds(0);

        if (selectedDate.toDateString() === malaysiaNow.toDateString()) {
            const nowPlus30 = new Date(malaysiaNow.getTime() + 30 * 60000);
            option.disabled = optionTime <= nowPlus30;
        } else {
            option.disabled = false;
        }
    });
}

window.addEventListener("DOMContentLoaded", () => {
    const today = getMalaysiaTimeNow().toISOString().split("T")[0];
    appointmentDate.setAttribute("min", today);
    appointmentDate.value = today;
    updateTimeOptions();
});

appointmentDate.addEventListener("change", updateTimeOptions);

</script>

</body>
</html>
