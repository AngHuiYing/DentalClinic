<?php
session_start();
include '../includes/db.php';

// 检查是否是管理员
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 获取现有的诊所设置信息
$sql = "SELECT * FROM clinic_settings WHERE id = 1";
$result = $conn->query($sql);
$settings = $result->fetch_assoc();

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $clinic_name = $_POST['clinic_name'];
    $clinic_address = $_POST['clinic_address'];
    $contact_email = $_POST['contact_email'];
    $contact_phone = $_POST['contact_phone'];

    $updateSql = "UPDATE clinic_settings SET clinic_name=?, clinic_address=?, contact_email=?, contact_phone=?, updated_at=NOW() WHERE id=1";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("ssss", $clinic_name, $clinic_address, $contact_email, $contact_phone);
    
    if ($stmt->execute()) {
        echo "<script>alert('Settings updated successfully!');</script>";
        echo "<script>window.location.href='clinic_settings.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Settings</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>Clinic Settings</h2>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Clinic Name</label>
            <input type="text" class="form-control" name="clinic_name" value="<?= htmlspecialchars($settings['clinic_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Clinic Address</label>
            <textarea class="form-control" name="clinic_address" required><?= htmlspecialchars($settings['clinic_address']) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Contact Email</label>
            <input type="email" class="form-control" name="contact_email" value="<?= htmlspecialchars($settings['contact_email']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Contact Phone</label>
            <input type="text" class="form-control" name="contact_phone" value="<?= htmlspecialchars($settings['contact_phone']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Settings</button>
    </form>
</div>
</body>
</html>
