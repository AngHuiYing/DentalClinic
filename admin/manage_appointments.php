<?php
session_start();
include '../includes/db.php';

// 检查管理员是否登录
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 处理状态更新
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status = $_POST['status'];
    
    $update_sql = "UPDATE appointments SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $new_status, $appointment_id);
    
    if ($stmt->execute()) {
        // 记录管理操作
        $admin_id = $_SESSION['admin_id'];
        $action = "Updated appointment ID: $appointment_id to status: $new_status";
        $conn->query("INSERT INTO user_logs (admin_id, action) VALUES ('$admin_id', '$action')");
        
        echo "<script>alert('Appointment status updated successfully!');</script>";
    } else {
        echo "<script>alert('Failed to update appointment status.');</script>";
    }
}

// 处理删除预约
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    
    $delete_sql = "DELETE FROM appointments WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $appointment_id);
    
    if ($stmt->execute()) {
        // 记录管理操作
        $admin_id = $_SESSION['admin_id'];
        $action = "Deleted appointment ID: $appointment_id";
        $conn->query("INSERT INTO user_logs (admin_id, action) VALUES ('$admin_id', '$action')");
        
        echo "<script>alert('Appointment deleted successfully!'); window.location.href='manage_appointments.php';</script>";
    } else {
        echo "<script>alert('Failed to delete appointment.');</script>";
    }
}

// 处理搜索
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$sql = "
    SELECT a.id, 
           p.name AS patient, 
           d.name AS doctor, 
           a.appointment_date, 
           a.appointment_time, 
           a.status, 
           a.created_at
    FROM appointments a
    LEFT JOIN users p ON a.patient_id = p.id AND p.role = 'patient'
    LEFT JOIN doctors d ON a.doctor_id = d.id
    WHERE p.name LIKE ? OR d.name LIKE ? OR a.status LIKE ?
    ORDER BY a.appointment_date DESC, a.appointment_time ASC
";

$stmt = $conn->prepare($sql);
$search_param = "%$search%";
$stmt->bind_param("sss", $search_param, $search_param, $search_param);
$stmt->execute();
$appointments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage All Appointments</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-5" style="padding-top: 70px;">
    <h2>Manage All Appointments</h2>

    <!-- 搜索表单 -->
    <form method="GET" class="mb-4">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search by Patient, Doctor, or Status" value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="manage_appointments.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <?php if ($appointments && $appointments->num_rows > 0) { ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $appointments->fetch_assoc()) { ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id']); ?></td>
                            <td><?= htmlspecialchars($row['patient']); ?></td>
                            <td><?= htmlspecialchars($row['doctor']); ?></td>
                            <td><?= htmlspecialchars($row['appointment_date']); ?></td>
                            <td><?= htmlspecialchars($row['appointment_time']); ?></td>
                            <td>
                                <span class="badge <?= 
                                    $row['status'] == 'approved' ? 'bg-success' : 
                                    ($row['status'] == 'cancelled' ? 'bg-danger' : 'bg-warning'); 
                                ?>">
                                    <?= ucfirst(htmlspecialchars($row['status'])); ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['created_at']); ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <form method="POST" class="me-2">
                                        <input type="hidden" name="appointment_id" value="<?= $row['id']; ?>">
                                        <select name="status" class="form-select form-select-sm" style="width: 100px;">
                                            <option value="pending" <?= $row['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?= $row['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="cancelled" <?= $row['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-primary btn-sm mt-1">Update</button>
                                    </form>
                                    
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this appointment?');">
                                        <input type="hidden" name="appointment_id" value="<?= $row['id']; ?>">
                                        <button type="submit" name="delete_appointment" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <div class="alert alert-info">No appointments found in the system.</div>
    <?php } ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
