<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: ../login.php");
    exit;
}

$patient_id = $_SESSION['user_id'];
$search_query = "";

// 获取患者信息
$patient_query = $conn->prepare("SELECT name FROM users WHERE id = ?");
$patient_query->bind_param("i", $patient_id);
$patient_query->execute();
$patient_result = $patient_query->get_result();
$patient_name = $patient_result->fetch_assoc()['name'];

// 处理搜索功能
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

// 查询医疗记录（支持搜索）
$sql = "
    SELECT mr.*, u.name as doctor_name 
    FROM medical_records mr
    JOIN users u ON mr.doctor_id = u.id
    WHERE mr.patient_id = ?
    AND (u.name LIKE ? OR mr.visit_date LIKE ? OR mr.diagnosis LIKE ?)
    ORDER BY mr.visit_date DESC
";
$stmt = $conn->prepare($sql);
$search_param = "%" . $search_query . "%";
$stmt->bind_param("isss", $patient_id, $search_param, $search_param, $search_param);
$stmt->execute();
$records_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Medical History</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<style>
    /* 搜索框样式 */
    .search-container {
        text-align: center;
        margin-bottom: 20px;
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
</style>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container mt-5" style="padding-top: 70px;">
    <h2>My Medical History</h2>
    <h4 class="mb-4">Patient: <?= htmlspecialchars($patient_name) ?></h4>

    <!-- 搜索框 -->
    <div class="search-container">
        <form method="GET">
            <input type="text" name="search" placeholder="Search by Doctor Name, Date, or Diagnosis" value="<?= htmlspecialchars($search_query) ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <?php if ($records_result->num_rows > 0) { ?>
        <table class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Doctor</th>
                    <th>Diagnosis</th>
                    <th>Prescription</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $records_result->fetch_assoc()) { ?>
                    <tr>
                        <td><?= htmlspecialchars($row['visit_date']); ?></td>
                        <td><?= htmlspecialchars($row['doctor_name']); ?></td>
                        <td><?= htmlspecialchars($row['diagnosis']); ?></td>
                        <td><?= htmlspecialchars($row['prescription']); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } else { ?>
        <div class="alert alert-info mt-3">No medical records found.</div>
    <?php } ?>
</div>
</body>
</html>
