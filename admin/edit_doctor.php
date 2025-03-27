<?php
session_start();
include '../includes/db.php';

// 确保管理员已登录
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 获取医生 ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_doctors.php");
    exit;
}

$doctor_id = $_GET['id'];

// 获取医生数据
$stmt = $conn->prepare("SELECT * FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: manage_doctors.php");
    exit;
}

$doctor = $result->fetch_assoc();

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $specialty = trim($_POST['specialty']);
    $bio = trim($_POST['bio']);
    $experience = trim($_POST['experience']);
    $location = trim($_POST['location']);
    $department = trim($_POST['department']);
    $user_id = trim($_POST['user_id']);
    $image = $doctor['image']; // 旧头像

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
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                // 删除旧头像（如果存在）
                if (!empty($doctor['image']) && file_exists($doctor['image'])) {
                    unlink($doctor['image']);
                }
                $image = $target_file; // 更新新头像路径
            } else {
                echo "<script>alert('Failed to upload image.');</script>";
                exit;
            }
        }

        // 更新医生信息
        $stmt = $conn->prepare("UPDATE doctors SET user_id=?, name=?, image=?, specialty=?, bio=?, experience=?, location=?, department=? WHERE id=?");
        $stmt->bind_param("isssssssi", $user_id, $name, $image, $specialty, $bio, $experience, $location, $department, $doctor_id);

        if ($stmt->execute()) {
            echo "<script>alert('Doctor profile updated successfully!'); window.location.href='manage_doctors.php';</script>";
        } else {
            echo "<script>alert('Failed to update doctor profile.');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Doctor Profile - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-5" style="padding-top: 70px;">
    <h2>Edit Doctor Profile</h2>
    <form method="POST" class="mt-4" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Doctor Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($doctor['name']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Current Profile Image</label><br>
            <?php if (!empty($doctor['image'])): ?>
                <img src="<?= $doctor['image'] ?>" alt="Doctor Image" style="width: 150px; height: 150px; object-fit: cover; border-radius: 10px;">
            <?php else: ?>
                <p>No image uploaded</p>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <label class="form-label">New Profile Image (Optional)</label>
            <input type="file" name="image" class="form-control" accept="image/*">
        </div>
        <div class="mb-3">
            <label class="form-label">Specialty</label>
            <input type="text" name="specialty" class="form-control" value="<?= htmlspecialchars($doctor['specialty']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Bio</label>
            <textarea name="bio" class="form-control" rows="4" required><?= htmlspecialchars($doctor['bio']) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Experience (Years)</label>
            <input type="number" name="experience" class="form-control" value="<?= htmlspecialchars($doctor['experience']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($doctor['location']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Department</label>
            <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($doctor['department']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Doctor User ID</label>
            <input type="number" name="user_id" class="form-control" value="<?= htmlspecialchars($doctor['user_id']) ?>" required>
        </div>

        <button type="submit" class="btn btn-primary">Update Doctor</button>
        <a href="manage_doctors.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
