<?php
session_start();
include "../db.php"; // 连接数据库

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['user_id'];
$search_query = "";

// 处理搜索功能
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

// 查询当前用户的预约记录
$sql = "SELECT a.id, d.name AS doctor_name, d.specialty, a.appointment_date, a.appointment_time, a.status 
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.patient_id = ?
        AND (d.name LIKE ? OR a.appointment_date LIKE ? OR a.status LIKE ?)
        ORDER BY a.appointment_date DESC";

$stmt = $conn->prepare($sql);
$search_param = "%" . $search_query . "%";
$stmt->bind_param("isss", $patient_id, $search_param, $search_param, $search_param);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments</title>
    <link rel="stylesheet" href="styles.css"> <!-- 你的 CSS 文件 -->
</head>
<style>
    /* 全局样式 */
body {
    font-family: Arial, sans-serif;
    background-color: #f4f4f4;
    margin: 0;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* 标题样式 */
h2 {
    color: #333;
    margin-bottom: 20px;
}

/* 搜索框样式 */
.search-container {
    margin-bottom: 20px;
    text-align: center;
}

.search-container input {
    padding: 8px;
    width: 300px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

.search-container button {
    padding: 8px 12px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.search-container button:hover {
    background-color: #0056b3;
}

/* 表格样式 */
table {
    width: 80%;
    border-collapse: collapse;
    background-color: white;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    overflow: hidden;
}

th, td {
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #ddd;
}

th {
    background-color: #005a8d;
    color: white;
}

tr:hover {
    background-color: #f1f1f1;
}

/* 取消按钮样式 */
a {
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 5px;
    font-weight: bold;
}

a:hover {
    opacity: 0.8;
}

a[href*="cancel_appointment"] {
    background-color: #dc3545;
    color: white;
}

a[href*="cancel_appointment"]:hover {
    background-color: #c82333;
}

/* 没有预约记录时的提示 */
p {
    font-size: 18px;
    color: #555;
    margin-top: 20px;
}

</style>
<body>
<?php include '../includes/navbar.php'; ?>

    <h2>My Appointments</h2>

    <!-- 搜索框 -->
    <div class="search-container">
        <form method="GET">
            <input type="text" name="search" placeholder="Search by Doctor Name, Date, or Status" value="<?= htmlspecialchars($search_query) ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <?php if ($result->num_rows > 0): ?>
        <table border="1">
            <thead>
                <tr>
                    <th>Doctor Name</th>
                    <th>Specialty</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>Dr. <?= htmlspecialchars($row['doctor_name']) ?></td>
                        <td><?= htmlspecialchars($row['specialty']) ?></td>
                        <td><?= htmlspecialchars($row['appointment_date']) ?></td>
                        <td><?= htmlspecialchars($row['appointment_time']) ?></td>
                        <td><?= ucfirst($row['status']) ?></td>
                        <td>
                            <?php if ($row['status'] == 'pending'): ?>
                                <a href="cancel_appointment.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to cancel this appointment?');">Cancel</a>
                            <?php else: ?>
                                <span>N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No appointments found.</p>
    <?php endif; ?>

</body>
</html>
