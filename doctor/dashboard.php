<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../doctor/login.php");
    exit;
}

$user_id = $_SESSION['user_id']; // 登录用户的 user_id

// **先找到 doctors.id**
$sql = "SELECT id FROM doctors WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    die("Doctor record not found!");
}

$doctor_id = $doctor['id']; // 这是 doctors 表里的 id

// **再查询 appointments**
$sql = "SELECT appointments.id, users.name AS patient, users.id AS patient_id, 
               appointment_date, appointment_time, status 
        FROM appointments 
        JOIN users ON appointments.patient_id = users.id 
        WHERE appointments.doctor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$appointments = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Doctor Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-5" style="padding-top: 70px;">
    <h2>Welcome, Dr. <?= isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Doctor'; ?>!</h2>
    <hr>

    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Patient Records</h5>
                    <p class="card-text">View and manage patient records.</p>
                    <a href="patient_records.php" class="btn btn-primary">View Records</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Medical Records</h5>
                    <p class="card-text">Manage patient medical history.</p>
                    <a href="patient_history.php" class="btn btn-primary">View History</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Appointments</h5>
                    <p class="card-text">Manage your upcoming appointments.</p>
                    <a href="manage_appointments.php" class="btn btn-primary">Manage</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Messages</h5>
                    <p class="card-text">Reply message from users.</p>
                    <a href="messages.php" class="btn btn-primary">Reply Message</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Chat with Patient</h5>
                    <p class="card-text">Answer patient's questions or explain things to them.</p>
                    <a href="chat_system.php" class="btn btn-primary">Chat</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                <h5 class="card-title">My Profile</h5>
                <p class="card-text">View and update your profile.</p>
                <a href="doctor_profile.php" class="btn btn-primary">View Profile</a>
            </div>
        </div>
</div>

    </div>

    <h3 class="mt-5">Your Upcoming Appointments</h3>

    <?php if ($appointments->num_rows > 0) { ?>  
        <table class="table table-bordered">
            <tr>
                <th>Patient</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php while ($row = $appointments->fetch_assoc()) { ?>
                <tr>
                    <td><?= htmlspecialchars($row['patient']); ?></td>
                    <td><?= htmlspecialchars($row['appointment_date']); ?></td>
                    <td><?= htmlspecialchars($row['appointment_time']); ?></td>
                    <td><?= htmlspecialchars($row['status']); ?></td>
                    <td>
                        <a href="patient_history.php?patient_id=<?= htmlspecialchars($row['patient_id']); ?>" class="btn btn-info">View Patient History</a>
                    </td>
                </tr>
            <?php } ?>
        </table>
    <?php } else { ?>
        <p class="text-muted">No upcoming appointments.</p>
    <?php } ?>

</div>
</body>
</html>
