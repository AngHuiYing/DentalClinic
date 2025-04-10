<?php
session_start();
include "../db.php";

// 验证管理员身份
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 获取所有医生
$doctors_sql = "SELECT id, name FROM doctors";
$doctors_result = $conn->query($doctors_sql);

// 处理删除功能
if (isset($_GET['delete_id']) && isset($_GET['doctor_id'])) {
    $delete_id = $_GET['delete_id'];
    $doctor_id = $_GET['doctor_id'];
    $delete_sql = "DELETE FROM unavailable_slots WHERE id = ? AND doctor_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $delete_id, $doctor_id);
    $delete_stmt->execute();
    header("Location: admin_set_unavailable.php?doctor_id=" . $doctor_id); // 防止刷新重复提交
    exit;
}

// 处理设置不可用时间段
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['doctor_id'])) {
    $doctor_id = $_POST['doctor_id'];
    $date = $_POST['date'];
    $from_time = $_POST['from_time'];
    $to_time = $_POST['to_time'];

    // 只允许设置未来时间
    if (strtotime($date . ' ' . $to_time) <= time()) {
        echo "<script>alert('Please select a future time slot.');</script>";
    } elseif ($from_time >= $to_time) {
        echo "<script>alert('End time must be later than start time.');</script>";
    } else {
        // 检查是否存在重叠
        $check_sql = "SELECT * FROM unavailable_slots 
                      WHERE doctor_id = ? AND date = ? 
                      AND ((from_time < ? AND to_time > ?) 
                      OR (from_time < ? AND to_time > ?) 
                      OR (from_time >= ? AND to_time <= ?))";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("isssssss", $doctor_id, $date, $to_time, $to_time, $from_time, $from_time, $from_time, $to_time);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            echo "<script>alert('This time slot overlaps with an existing one.');</script>";
        } else {
            $sql = "INSERT INTO unavailable_slots (doctor_id, date, from_time, to_time) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $doctor_id, $date, $from_time, $to_time);
            if ($stmt->execute()) {
                echo "<script>alert('Unavailable time set successfully!');</script>";
            } else {
                echo "<script>alert('Failed to set unavailable time.');</script>";
            }
        }
    }
}

// 获取所选医生的不可用时间段
if (isset($_GET['doctor_id'])) {
    $doctor_id = $_GET['doctor_id'];
    $unavailable_sql = "SELECT * FROM unavailable_slots WHERE doctor_id = ? ORDER BY date, from_time";
    $unavailable_stmt = $conn->prepare($unavailable_sql);
    $unavailable_stmt->bind_param("i", $doctor_id);
    $unavailable_stmt->execute();
    $unavailable_result = $unavailable_stmt->get_result();
    $unavailable_slots = [];
    while ($row = $unavailable_result->fetch_assoc()) {
        $unavailable_slots[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Set Unavailable Time</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .container {
            margin: 30px auto;
            max-width: 600px;
        }
        form {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input[type="text"], input[type="time"], select {
            width: 100%;
            padding: 8px;
        }
        button {
            margin-top: 15px;
            padding: 10px 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: center;
        }
        .delete-btn {
            color: red;
            text-decoration: none;
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container">
    <h2>Set Unavailable Time for Doctors</h2>
    
    <!-- 医生选择下拉框 -->
    <form method="GET">
        <label for="doctor_id">Select Doctor:</label>
        <select name="doctor_id" id="doctor_id" required>
            <option value="">-- Choose a Doctor --</option>
            <?php while ($doctor = $doctors_result->fetch_assoc()): ?>
                <option value="<?= $doctor['id'] ?>" <?= isset($_GET['doctor_id']) && $_GET['doctor_id'] == $doctor['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($doctor['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit">Select Doctor</button>
    </form>

    <?php if (isset($_GET['doctor_id'])): ?>
        <!-- 设置不可用时间段表单 -->
        <h3>Set Unavailable Time for Doctor</h3>
        <form method="POST">
            <input type="hidden" name="doctor_id" value="<?= $_GET['doctor_id'] ?>">
            <label for="date">Date:</label>
            <input type="text" id="date" name="date" placeholder="YYYY-MM-DD" required>

            <label for="from_time">From Time:</label>
            <input type="time" id="from_time" name="from_time" required>

            <label for="to_time">To Time:</label>
            <input type="time" id="to_time" name="to_time" required>

            <button type="submit">Set Unavailable</button>
        </form>

        <!-- 显示所选医生的不可用时间段 -->
        <h3>Existing Unavailable Time Slots for Doctor</h3>
        <input type="text" id="searchInput" placeholder="Search by date or time...(Must enter the symbol ' - ' or ' : ' in between)" style="width: 100%; padding: 8px; margin-bottom: 10px;">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($unavailable_slots as $slot): ?>
                <tr>
                    <td><?= htmlspecialchars($slot['date']) ?></td>
                    <td><?= htmlspecialchars($slot['from_time']) ?></td>
                    <td><?= htmlspecialchars($slot['to_time']) ?></td>
                    <td><a class="delete-btn" href="?delete_id=<?= $slot['id'] ?>&doctor_id=<?= $_GET['doctor_id'] ?>" onclick="return confirm('Are you sure you want to delete this slot?');">Delete</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    flatpickr("#date", {
        dateFormat: "Y-m-d",
        minDate: "today"
    });

    document.getElementById("searchInput").addEventListener("keyup", function () {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll("table tbody tr");

    rows.forEach(row => {
        const date = row.cells[0].textContent.toLowerCase();
        const from = row.cells[1].textContent.toLowerCase();
        const to = row.cells[2].textContent.toLowerCase();
        
        if (date.includes(filter) || from.includes(filter) || to.includes(filter)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
});
</script>
</body>
</html>
