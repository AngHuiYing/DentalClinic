<?php
session_start();
date_default_timezone_set("Asia/Kuala_Lumpur");
include "../db.php";

// 验证用户身份
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: ../patient/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$doctor_id = isset($_GET['doctor']) ? intval($_GET['doctor']) : 0;

// 获取医生信息
$stmt = $conn->prepare("SELECT * FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    echo "Doctor not found!";
    exit();
}

// 获取已预约和不可预约时间段（格式：Y-m-d + 空格 + 时间段字符串）
$disabled_slots = [];

// 获取所有预约（排除被拒绝的）
$appointments_sql = "SELECT appointment_date, appointment_time FROM appointments WHERE doctor_id = ? AND status != 'rejected'";
$appointments_stmt = $conn->prepare($appointments_sql);
$appointments_stmt->bind_param("i", $doctor_id);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();
$booked_slots = [];

while ($row = $appointments_result->fetch_assoc()) {
    $booked_slots[] = $row['appointment_date'] . ' ' . $row['appointment_time'];
}

// 将已预约的时间段传递到前端
$booked_slots_json = json_encode($booked_slots);

// 获取医生不可用时间段
$unavailable_sql = "SELECT date, from_time, to_time FROM unavailable_slots WHERE doctor_id = ?";
$unavailable_stmt = $conn->prepare($unavailable_sql);
$unavailable_stmt->bind_param("i", $doctor_id);
$unavailable_stmt->execute();
$unavailable_result = $unavailable_stmt->get_result();
while ($row = $unavailable_result->fetch_assoc()) {
    $start_time = strtotime($row['from_time']);
    $end_time = strtotime($row['to_time']);
    while ($start_time < $end_time) {
        $disabled_slots[] = $row['date'] . ' ' . date('H:i', $start_time) . ' - ' . date('H:i', $start_time + 3600);
        $start_time += 3600;
    }
}

// 提交预约处理
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $datetime = $appointment_date . ' ' . $appointment_time;

    if (in_array($datetime, $disabled_slots)) {
        echo "<script>alert('This time is already booked or unavailable.');</script>";
    } else {
        $check_sql = "SELECT * FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            echo "<script>alert('This time slot is already taken. Please choose another one.');</script>";
        } else {
            $sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status) 
                    VALUES (?, ?, ?, ?, 'Pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiss", $user_id, $doctor_id, $appointment_date, $appointment_time);
            if ($stmt->execute()) {
                echo "<script>alert('Appointment booked successfully!'); window.location.href = 'my_appointments.php';</script>";
            } else {
                echo "<script>alert('Booking failed.');</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Appointment</title>
    <style>
        table.calendar { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .calendar td { width: 14.28%; padding: 10px; text-align: center; cursor: pointer; }
        .calendar th { background-color: #f1f1f1; padding: 10px; }
        .calendar td.available { background-color: #c8f7c5; }
        .calendar td.partial { background-color: #ffeaa7; }
        .calendar td.booked { background-color: #fab1a0; pointer-events: none; }
        .calendar td.unavailable { background-color: #dfe6e9; pointer-events: none; }
        .calendar td:hover { background-color: grey; }
        button { padding: 10px 15px; margin: 5px; cursor: pointer; border: 1px solid #ccc; border-radius: 5px; }
        button.selected { background-color: #3498db; color: white; }
        button.booked-time { background-color: #e74c3c; color: white; pointer-events: none; }
        .legend span { margin-right: 10px; padding: 5px; border-radius: 3px; }
        .calendar td.booked-time {
    background-color: #d3d3d3; /* 灰色 */
    pointer-events: none; /* 禁用点击 */
}

button.booked-time {
    background-color: #d3d3d3; /* 灰色 */
    color: #a0a0a0; /* 灰色文本 */
    pointer-events: none; /* 禁用点击 */
}
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container">
    <h2>Book Appointment with Dr. <?= htmlspecialchars($doctor['name']) ?></h2>

    <form method="POST" onsubmit="return validateSelection();">
        <input type="hidden" name="appointment_date" id="appointment_date">
        <input type="hidden" name="appointment_time" id="appointment_time">

        <div id="calendar-nav"></div>
        <div id="calendar"></div>

        <div class="legend">
            <span style="background:#c8f7c5;">Available</span>
            <span style="background:#ffeaa7;">Partial</span>
            <span style="background:#fab1a0;">Booked</span>
            <span style="background:#dfe6e9;">Unavailable</span>
        </div>

        <div id="times"></div>

        <button type="submit">Confirm Appointment</button>
    </form>
</div>

<script>
    const today = new Date();
    let currentYear = today.getFullYear();
    let currentMonth = today.getMonth();

    const allSlots = [
        "09:00 - 10:00", "10:00 - 11:00", "11:00 - 12:00",
        "13:00 - 14:00", "14:00 - 15:00", "15:00 - 16:00"
    ];

    const disabledSlotsFromServer = <?= json_encode($disabled_slots); ?>;
    const calendarData = {};

    // 将禁用的时间段添加到 calendarData 中
disabledSlotsFromServer.forEach(entry => {
    const [date, ...timeArr] = entry.split(" ");
    const time = timeArr.join(" ");
    if (!calendarData[date]) calendarData[date] = [];
    calendarData[date].push(time);
});

const bookedSlotsFromServer = <?= $booked_slots_json ?>;

    // 将禁用的时间段添加到 calendarData 中
    bookedSlotsFromServer.forEach(entry => {
        const [date, time] = entry.split(" ");
        if (!calendarData[date]) calendarData[date] = [];
        calendarData[date].push(time);
    });

    // 渲染日历
    function selectDate(dateStr) {
    document.getElementById("appointment_date").value = dateStr;
    const timeContainer = document.getElementById("times");
    timeContainer.innerHTML = `<h4>Available Time Slots for ${dateStr}</h4>`;

    const currentTime = new Date();
    const currentDateStr = `${currentTime.getFullYear()}-${(currentTime.getMonth() + 1).toString().padStart(2, '0')}-${currentTime.getDate().toString().padStart(2, '0')}`;

    // 清除时间段按钮
    allSlots.forEach(slot => {
        const btn = document.createElement("button");
        btn.textContent = slot;

        // 禁用过去的时间段
        if (dateStr === currentDateStr && isPastTimeSlot(slot, currentTime)) {
            btn.classList.add("booked-time");
            btn.disabled = true;
        } else {
            const isDisabled = (calendarData[dateStr] || []).includes(slot);

            if (isDisabled) {
                btn.classList.add("booked-time");
                btn.disabled = true;
            } else {
                btn.onclick = () => {
                    document.querySelectorAll("#times button").forEach(b => b.classList.remove("selected"));
                    btn.classList.add("selected");
                    document.getElementById("appointment_time").value = slot;
                };
            }
        }

        timeContainer.appendChild(btn);
    });
}

// 判断时间段是否已经过去
function isPastTimeSlot(slot, currentTime) {
    const slotTime = slot.split(" - ");
    const startTime = new Date(currentTime);
    const [startHour, startMinute] = slotTime[0].split(":");
    startTime.setHours(startHour, startMinute, 0, 0);
    
    return currentTime > startTime;
}

function renderCalendar(year, month) {
    const calendarEl = document.getElementById("calendar");
    const navEl = document.getElementById("calendar-nav");
    calendarEl.innerHTML = "";
    navEl.innerHTML = "";

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDay = firstDay.getDay();

    const prevBtn = document.createElement("button");
    prevBtn.textContent = "<";
    prevBtn.onclick = () => renderCalendar(year, month - 1);
    navEl.appendChild(prevBtn);

    const monthLabel = document.createElement("span");
    monthLabel.textContent = `${firstDay.toLocaleString('default', { month: 'long' })} ${year}`;
    monthLabel.style.margin = "0 15px";
    navEl.appendChild(monthLabel);

    const nextBtn = document.createElement("button");
    nextBtn.textContent = ">";
    nextBtn.onclick = () => renderCalendar(year, month + 1);
    navEl.appendChild(nextBtn);

    const table = document.createElement("table");
    table.classList.add("calendar");

    const days = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    const thead = document.createElement("thead");
    const headerRow = document.createElement("tr");
    days.forEach(day => {
        const th = document.createElement("th");
        th.textContent = day;
        headerRow.appendChild(th);
    });
    thead.appendChild(headerRow);
    table.appendChild(thead);

    const tbody = document.createElement("tbody");
    let row = document.createElement("tr");

    for (let i = 0; i < startDay; i++) row.appendChild(document.createElement("td"));

    const currentTime = new Date();
    for (let date = 1; date <= lastDay.getDate(); date++) {
        const cellDate = new Date(year, month, date);
        const cellDateStr = `${cellDate.getFullYear()}-${(cellDate.getMonth() + 1).toString().padStart(2, '0')}-${cellDate.getDate().toString().padStart(2, '0')}`;
        const td = document.createElement("td");
        td.textContent = date;

        // 禁用过去的日期
        if (cellDate < currentTime.setHours(0, 0, 0, 0)) {
            td.classList.add("unavailable");
        } else {
            const bookedTimes = calendarData[cellDateStr] || [];
            if (bookedTimes.length >= allSlots.length) td.classList.add("booked");
            else if (bookedTimes.length > 0) td.classList.add("partial");
            else td.classList.add("available");

            td.onclick = () => selectDate(cellDateStr);
        }

        row.appendChild(td);
        if ((startDay + date) % 7 === 0) {
            tbody.appendChild(row);
            row = document.createElement("tr");
        }
    }
    tbody.appendChild(row);
    table.appendChild(tbody);
    calendarEl.appendChild(table);
}

    // 表单验证
    function validateSelection() {
        const date = document.getElementById("appointment_date").value;
        const time = document.getElementById("appointment_time").value;
        if (!date || !time) {
            alert("Please select both date and time.");
            return false;
        }
        return true;
    }

    // 初始化
    renderCalendar(currentYear, currentMonth);
</script>
</body>
</html>
