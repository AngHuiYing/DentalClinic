<?php
session_start();
include '../includes/db.php';

// 检查管理员是否登录
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 获取管理员信息
$admin_name = $_SESSION['admin_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-5" style="padding-top: 70px;">
    <h2>Welcome, Admin <?php echo htmlspecialchars($admin_name); ?>!</h2>
    <hr>

    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Manage Users</h5>
                    <p class="card-text">Add, edit, or remove system users.</p>
                    <a href="./manage_users.php" class="btn btn-primary">Manage</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Manage Doctors</h5>
                    <p class="card-text">View and manage all appointments.</p>
                    <a href="./manage_doctors.php" class="btn btn-primary">Manage</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Manage Appointments</h5>
                    <p class="card-text">View and manage all appointments.</p>
                    <a href="./manage_appointments.php" class="btn btn-primary">Manage</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Doctor's Booking Time</h5>
                    <p class="card-text">Manage doctors booking time.</p>
                    <a href="./admin_set_unavailable.php" class="btn btn-primary">Manage</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Message from Users</h5>
                    <p class="card-text">Reply message from users</p>
                    <a href="./messages.php" class="btn btn-primary">Reply</a>
                </div>
            </div>
        </div>
        
        <!-- <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Clinic Settings</h5>
                    <p class="card-text">Configure clinic settings.</p>
                    <a href="./clinic_settings.php" class="btn btn-primary">Configure</a>
                </div>
            </div>
        </div> -->
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">System Logs</h5>
                    <p class="card-text">View system activity logs.</p>
                    <a href="./system_logs.php" class="btn btn-primary">View Logs</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 