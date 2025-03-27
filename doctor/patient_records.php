<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../doctor/login.php");
    exit;
}

// 确保 $search 变量被正确初始化
$search = isset($_GET['search']) ? trim($_GET['search']) : "";

// 获取医生 ID
$user_id = $_SESSION['user_id'];
$sql = "SELECT id FROM doctors WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    die("Doctor record not found!");
}

$doctor_id = $doctor['id']; // 确保获取的是 doctors 表的 ID

// 查询病人数据
$sql = "
    SELECT DISTINCT u.id, u.name, u.email 
    FROM users u
    JOIN appointments a ON u.id = a.patient_id
    WHERE a.doctor_id = ? AND u.role = 'patient'
";

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
}

$stmt = $conn->prepare($sql);

if (!empty($search)) {
    $searchParam = "%$search%";
    $stmt->bind_param("iss", $doctor_id, $searchParam, $searchParam);
} else {
    $stmt->bind_param("i", $doctor_id);
}

$stmt->execute();
$patients = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Patient Records</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-5" style="padding-top: 70px;">
    <h2>My Patients</h2>

    <!-- 搜索框 -->
    <form method="GET" class="mb-3">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="<?= htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form>

    <?php if ($patients->num_rows > 0) { ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $patients->fetch_assoc()) { ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']); ?></td>
                        <td><?= htmlspecialchars($row['email']); ?></td>
                        <td>
                            <a href="patient_history.php?patient_id=<?= htmlspecialchars($row['id']); ?>" class="btn btn-info">View Medical History</a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } else { ?>
        <div class="alert alert-warning">No patients found.</div>
    <?php } ?>
</div>
</body>
</html>
