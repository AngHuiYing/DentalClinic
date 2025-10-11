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

// **只查詢 status = confirmed 且日期未過期的 appointments**
$sql = "SELECT a.id, 
               a.appointment_date, 
               a.appointment_time, 
               a.status,
               u.name AS patient_user, 
               u.email AS user_email,
               a.patient_name, 
               a.patient_email, 
               a.patient_phone,
               a.message
        FROM appointments a
        LEFT JOIN users u ON a.patient_id = u.id
        WHERE a.doctor_id = ? 
          AND a.status = 'confirmed'
          AND a.appointment_date >= CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC";
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

    <!-- 這裡是功能卡片區域（保持不變） -->
        
        

        <div class="row g-4">
            <div class="col-12 col-sm-6 col-lg-4 d-flex align-items-stretch">
                <div class="card shadow-sm w-100 border-0 rounded-4">
                    <div class="card-body text-center">
                        <span class="d-block mb-2" style="font-size:2rem;color:#0d6efd;"><i class="bi bi-person-circle"></i></span>
                        <h5 class="card-title">My Profile</h5>
                        <p class="card-text small">View and edit your profile.</p>
                        <a href="doctor_profile.php" class="btn btn-outline-primary btn-sm rounded-pill">Profile</a>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4 d-flex align-items-stretch">
                <div class="card shadow-sm w-100 border-0 rounded-4">
                    <div class="card-body text-center">
                        <span class="d-block mb-2" style="font-size:2rem;color:#0d6efd;"><i class="bi bi-journal-medical"></i></span>
                        <h5 class="card-title">Patient Records</h5>
                        <p class="card-text small">View patient records.</p>
                        <a href="patient_records.php" class="btn btn-outline-primary btn-sm rounded-pill">Records</a>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4 d-flex align-items-stretch">
                <div class="card shadow-sm w-100 border-0 rounded-4">
                    <div class="card-body text-center">
                        <span class="d-block mb-2" style="font-size:2rem;color:#0d6efd;"><i class="bi bi-calendar-check"></i></span>
                        <h5 class="card-title">Appointments</h5>
                        <p class="card-text small">View appointments.</p>
                        <a href="manage_appointments.php" class="btn btn-outline-primary btn-sm rounded-pill">Appointments</a>
                    </div>
                </div>
            </div>
        </div>
        <style>
            .card-title { font-weight: 600; }
            .card { transition: box-shadow .2s; min-height: 180px; display: flex; flex-direction: column; justify-content: center; }
            .card:hover { box-shadow: 0 0 0.75rem #0d6efd33; }
            .card-body { padding: 1.2rem 0.7rem; }
            @media (max-width: 991px) {
                .card-body { padding: 1rem 0.5rem; }
                .card-title { font-size: 1rem; }
            }
            @media (max-width: 575px) {
                .card-title { font-size: 0.95rem; }
                .card-text { font-size: 0.92rem; }
                .card { min-height: 160px; }
            }
        </style>
        <!-- Bootstrap Icons CDN -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

        <!-- <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Booking Time</h5>
                    <p class="card-text">Manage your booking time.</p>
                    <a href="doctor_setunavailable.php" class="btn btn-primary">Manage</a>
                </div>
            </div>
        </div> -->
    </div>

    <h3 class="mt-5 text-center">Today Appointments</h3>
    
    <?php if ($appointments->num_rows > 0) { ?>
        <div class="table-responsive mt-4 mb-5 px-2 py-3 rounded shadow-sm">
            <div style="padding-left: 1.5rem; padding-right: 1.5rem;">
                <table class="table table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Message</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $appointments->fetch_assoc()) { ?>
                        <tr>
                            <td><?= htmlspecialchars($row['patient_user'] ?? $row['patient_name'] ?? 'Anonymous'); ?></td>
                            <td><?= htmlspecialchars($row['user_email'] ?? $row['patient_email'] ?? 'N/A'); ?></td>
                            <td><?= htmlspecialchars($row['patient_phone'] ?? 'N/A'); ?></td>
                            <td><?= htmlspecialchars($row['appointment_date']); ?></td>
                            <td><?= htmlspecialchars($row['appointment_time']); ?></td>
                            <td><?= !empty($row['message']) ? htmlspecialchars($row['message']) : '<span class="text-muted">N/A</span>'; ?></td>
                            <td>
                                <span class="badge bg-success">Confirmed</span>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
                </table>
            </div>
        </div>
    <?php } else { ?>
        <div class="alert alert-info mt-3 text-center">You have no appointments for today.</div>
    <?php } ?>
</div>
</body>
</html>