<?php
session_start();
include '../includes/db.php';

// 确保管理员已登录
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 获取搜索关键字
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// 获取医生列表（支持搜索）
$sql = "SELECT d.id, d.user_id, d.name, d.image, d.specialty, d.experience, d.department, u.email 
        FROM doctors d 
        LEFT JOIN users u ON d.user_id = u.id";

if (!empty($search)) {
    $sql .= " WHERE d.name LIKE '%$search%'
              OR d.specialty LIKE '%$search%'
              OR d.department LIKE '%$search%'
              OR d.user_id LIKE '%$search%'
              OR u.email LIKE '%$search%'";
}

$sql .= " ORDER BY d.id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-5" style="padding-top: 70px;">
    <h2>Manage Doctors</h2>
    
    <!-- 搜索框 -->
    <form method="GET" class="mb-3">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search by Name, Specialty, Department, Doctor's User ID, or Email"
                   value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form>

    <a href="add_doctor_profile.php" class="btn btn-success mb-3">Add New Doctor Profile</a>
    
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Name</th>
                <th>Specialty</th>
                <th>Experience</th>
                <th>Department</th>
                <th>Email</th>
                <th>Doctor's User ID</th> <!-- 添加 Doctor's User ID -->
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td>
                    <?php if (!empty($row['image'])): ?>
                        <img src="<?= $row['image'] ?>" alt="Doctor Image" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                    <?php else: ?>
                        No Image
                    <?php endif; ?>
                </td>
                <td>Dr. <?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['specialty']) ?></td>
                <td><?= htmlspecialchars($row['experience']) ?> years</td>
                <td><?= htmlspecialchars($row['department']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= $row['user_id'] ?></td> <!-- 显示 Doctor's User ID -->
                <td>
                    <a href="edit_doctor.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                    <a href="delete_doctor.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?');">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
