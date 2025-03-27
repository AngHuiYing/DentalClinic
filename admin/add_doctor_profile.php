<?php
session_start();
include '../includes/db.php';

// 检查是否为管理员
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $specialty = trim($_POST['specialty']);
    $bio = trim($_POST['bio']);
    $experience = trim($_POST['experience']);
    $location = trim($_POST['location']);
    $department = trim($_POST['department']);
    $user_id = trim($_POST['user_id']);
    $image = "";

    // 确保所有字段都填写
    if (empty($name) || empty($specialty) || empty($bio) || empty($experience) || empty($location) || empty($department) || empty($user_id)) {
        echo "<script>alert('All fields are required!');</script>";
    } else {
        // 处理文件上传
        if (!empty($_FILES["image"]["name"])) {
            $target_dir = "../uploads/doctors/";
            $image = basename($_FILES["image"]["name"]);
            $target_file = $target_dir . time() . "_" . $image; // 避免重复文件名
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            // 允许的文件格式
            $allowed_types = array("jpg", "jpeg", "png");
            if (!in_array($imageFileType, $allowed_types)) {
                echo "<script>alert('Only JPG, JPEG, PNG files are allowed.');</script>";
                exit;
            }

            // 移动上传的文件
            if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                echo "<script>alert('Failed to upload image.');</script>";
                exit;
            }
        }

        // 插入医生信息到数据库
        $stmt = $conn->prepare("INSERT INTO doctors (user_id, name, image, specialty, bio, experience, location, department) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $user_id, $name, $target_file, $specialty, $bio, $experience, $location, $department);

        if ($stmt->execute()) {
            echo "<script>alert('Doctor profile added successfully!'); window.location.href='dashboard.php';</script>";
        } else {
            echo "<script>alert('Failed to add doctor profile.');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Doctor Profile - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-5" style="padding-top: 70px;">
    <h2>Add Doctor Profile</h2>
    <form method="POST" class="mt-4" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Doctor Name</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Profile Image</label>
            <input type="file" name="image" class="form-control" accept="image/*">
        </div>
        <div class="mb-3">
            <label class="form-label">Specialty</label>
            <input type="text" name="specialty" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Bio</label>
            <textarea name="bio" class="form-control" rows="4" required></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Experience (Years)</label>
            <input type="number" name="experience" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Department</label>
            <input type="text" name="department" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Doctor User ID</label>
            <input type="number" name="user_id" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-success">Add Doctor</button>
        <a href="manage_doctors.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
