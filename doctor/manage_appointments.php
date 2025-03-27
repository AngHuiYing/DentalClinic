<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../doctor/login.php");
    exit;
}

$user_id = $_SESSION['user_id']; // 这是 users 表里的 ID

// **获取医生的 doctor_id**
$sql = "SELECT id FROM doctors WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    die("Doctor record not found!");
}

$doctor_id = $doctor['id']; // 这是 doctors 表里的 ID

// **处理状态更新**
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status = $_POST['status'];
    
    $update_sql = "UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sii", $new_status, $appointment_id, $doctor_id);
    
    if ($stmt->execute()) {
        // **如果状态更新为 "approved"，发送通知**
        if ($new_status == 'approved') {
            $get_patient_sql = "SELECT patient_id FROM appointments WHERE id = ?";
            $stmt = $conn->prepare($get_patient_sql);
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $patient_id = $result->fetch_assoc()['patient_id'];
            
            $notification = "Your appointment has been approved by the doctor.";
            $insert_notif_sql = "INSERT INTO notifications (user_id, message) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_notif_sql);
            $stmt->bind_param("is", $patient_id, $notification);
            $stmt->execute();
        }
        
        echo "<script>alert('Appointment status updated successfully!'); window.location.href = 'manage_appointments.php';</script>";
    } else {
        echo "<script>alert('Failed to update appointment status.');</script>";
    }
}

// **获取医生的所有预约**
$sql = "SELECT a.id, u.name AS patient, a.appointment_date, a.appointment_time, a.status
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        WHERE a.doctor_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$appointments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Appointments</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-5" style="padding-top: 70px;">
    <h2>Manage Your Appointments</h2>
    
    <?php if ($appointments->num_rows > 0) { ?>
        <table class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $appointments->fetch_assoc()) { ?>
                    <tr>
                        <td><?= htmlspecialchars($row['patient']); ?></td>
                        <td><?= htmlspecialchars($row['appointment_date']); ?></td>
                        <td><?= htmlspecialchars($row['appointment_time']); ?></td>
                        <td>
                            <span class="badge <?= 
                                $row['status'] == 'approved' ? 'bg-success' : 
                                ($row['status'] == 'cancelled' ? 'bg-danger' : 'bg-warning'); 
                            ?>">
                                <?= ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" class="d-flex">
                                <input type="hidden" name="appointment_id" value="<?= $row['id']; ?>">
                                <select name="status" class="form-select me-2">
                                    <option value="pending" <?= $row['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?= $row['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="cancelled" <?= $row['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } else { ?>
        <div class="alert alert-info mt-3">You have no appointments.</div>
    <?php } ?>
</div>
</body>
</html>
