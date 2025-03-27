<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../doctor/login.php");
    exit;
}

$user_id = $_SESSION['user_id']; // 登录医生的 user_id

// 获取医生信息
$sql = "SELECT d.id, u.name, u.email, d.image, d.specialty, d.bio, d.experience, d.location, d.department
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    die("Doctor record not found!");
}

// 处理医生资料更新
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $specialty = $_POST['specialty'];
    $bio = $_POST['bio'];
    $experience = $_POST['experience'];
    $location = $_POST['location'];
    $department = $_POST['department'];

    // **头像上传**
    if (!empty($_FILES['image']['name'])) {
        $image_name = time() . "_" . basename($_FILES["image"]["name"]);
        $image_path = "../uploads/" . $image_name; // 确保有 uploads 文件夹
        move_uploaded_file($_FILES["image"]["tmp_name"], $image_path);

        // 更新数据库中的头像
        $sql = "UPDATE doctors SET image = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $image_name, $user_id);
        $stmt->execute();
    }

    // 更新 users 表中的姓名和邮箱
    $sql = "UPDATE users SET name = ?, email = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $name, $email, $user_id);
    $stmt->execute();

    // 更新 doctors 表的其余信息
    $sql = "UPDATE doctors SET specialty = ?, bio = ?, experience = ?, location = ?, department = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $specialty, $bio, $experience, $location, $department, $user_id);
    $stmt->execute();

    echo "<script>alert('Profile updated successfully!'); window.location.href='doctor_profile.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Doctor Profile</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-5" style="padding-top: 70px;">
    <h2>Doctor Profile</h2>
    <hr>

    <form method="POST" enctype="multipart/form-data">
        <!-- 头像 -->
        <div class="mb-3">
            <label class="form-label">Profile Picture:</label>
            <br>
            <img src="../uploads/<?= htmlspecialchars($doctor['image']); ?>" alt="Doctor Image" class="img-thumbnail" width="150">
            <input type="file" name="image" class="form-control mt-2">
        </div>

        <!-- 基本信息 -->
        <div class="mb-3">
            <label class="form-label">Full Name:</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($doctor['name']); ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email:</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($doctor['email']); ?>" required>
        </div>

        <!-- 专业信息 -->
        <div class="mb-3">
            <label class="form-label">Specialty:</label>
            <input type="text" name="specialty" class="form-control" value="<?= htmlspecialchars($doctor['specialty']); ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Department:</label>
            <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($doctor['department']); ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Experience (years):</label>
            <input type="text" name="experience" class="form-control" value="<?= htmlspecialchars($doctor['experience']); ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Location:</label>
            <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($doctor['location']); ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Bio:</label>
            <textarea name="bio" class="form-control" rows="3"><?= htmlspecialchars($doctor['bio']); ?></textarea>
        </div>

        <button type="submit" class="btn btn-success">Update Profile</button>
    </form>
</div>
</body>
</html>
