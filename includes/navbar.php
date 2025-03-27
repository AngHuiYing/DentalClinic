<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/db.php');

// 获取当前脚本的目录深度，以便正确设置相对路径
$root_path = "";
$current_dir = dirname($_SERVER['PHP_SELF']);
$depth = substr_count($current_dir, '/');
if (strpos($current_dir, '/admin') !== false || 
    strpos($current_dir, '/doctor') !== false || 
    strpos($current_dir, '/patient') !== false) {
    $root_path = "../";
}

// 获取当前页面的完整URL
$current_url = $_SERVER['PHP_SELF'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic System</title>
    <style>
        /* 全局样式 */
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            padding-top: 70px;
        }

        /* 导航栏样式 */
        .navbar {
            background: #ffffff;
            padding: 15px 20px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            z-index: 1000;
        }

        /* 导航列表 */
        .navbar ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* 导航项 */
        .navbar ul li {
            margin: 0 15px;
        }

        /* 导航链接 */
        .navbar ul li a {
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            color: #333;
            padding: 10px 15px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        /* 活动链接样式 */
        .navbar ul li a.active {
            background: #007bff;
            color: #ffffff;
        }

        /* 悬停效果 */
        .navbar ul li a:hover {
            background: #e0e0e0;
            color: #007bff;
            transform: scale(1.1);
        }

        /* 响应式设计 */
        /* 适配小屏幕 */
        @media (max-width: 768px) {
            .navbar ul {
                display: none; /* 默认隐藏 */
                flex-direction: column;
                align-items: center;
                position: absolute;
                width: 100%;
                background: white;
                top: 50px;
                left: 0;
                box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            }

            .navbar ul.show {
                display: flex !important;
            }

            .menu-toggle {
                display: block;
                cursor: pointer;
                font-size: 24px;
                position: absolute;
                right: 15px;
                top: 10px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
    <span class="menu-toggle">&#9776;</span>
        <ul>
            <?php if (isset($_SESSION['admin_id']) && $_SESSION['user_role'] === 'admin') { ?>
                <!-- 管理员导航菜单 -->
                <li><a href="/Hospital_Management_System/admin/dashboard.php" <?php echo (strpos($current_url, 'dashboard.php') !== false) ? 'class="active"' : ''; ?>>Dashboard</a></li>
                <li><a href="/Hospital_Management_System/admin/manage_users.php" <?php echo (strpos($current_url, 'manage_users.php') !== false) ? 'class="active"' : ''; ?>>Manage Users</a></li>
                <li><a href="/Hospital_Management_System/admin/manage_doctors.php" <?php echo (strpos($current_url, 'manage_doctors.php') !== false) ? 'class="active"' : ''; ?>>Manage Doctor Profile</a></li>
                <li><a href="/Hospital_Management_System/admin/manage_appointments.php" <?php echo (strpos($current_url, 'manage_appointments.php') !== false) ? 'class="active"' : ''; ?>>Manage Appointments</a></li>
                <li><a href="/Hospital_Management_System/admin/messages.php" <?php echo (strpos($current_url, 'messages.php') !== false) ? 'class="active"' : ''; ?>>Message from Users</a></li>
                <li><a href="/Hospital_Management_System/admin/system_logs.php" <?php echo (strpos($current_url, 'system_logs.php') !== false) ? 'class="active"' : ''; ?>>System Logs</a></li>
                <li><a href="/Hospital_Management_System/admin/logout.php">Logout</a></li>
            <?php } elseif (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'doctor') { ?>
                <!-- 医生导航菜单 -->
                <li><a href="/Hospital_Management_System/doctor/dashboard.php">Dashboard</a></li>
                <li><a href="/Hospital_Management_System/doctor/patient_records.php">Patient Records</a></li>
                <li><a href="/Hospital_Management_System/doctor/patient_history.php">Medical Records</a></li>
                <li><a href="/Hospital_Management_System/doctor/manage_appointments.php">Manage Appointments</a></li>
                <li><a href="/Hospital_Management_System/doctor/messages.php">Message from Users</a></li>
                <li><a href="/Hospital_Management_System/doctor/doctor_profile.php">My Profile</a></li>
                <li><a href="/Hospital_Management_System/doctor/logout.php">Logout</a></li>
            <?php } elseif (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'patient') { ?>
                <!-- 病人导航菜单 -->
                <li><a href="/Hospital_Management_System/patient/dashboard.php">Dashboard</a></li>
                <li><a href="/Hospital_Management_System/all_doctors.php">Book Appointment</a></li>
                <li><a href="/Hospital_Management_System/patient/my_appointments.php">My Appointments</a></li>
                <li><a href="/Hospital_Management_System/patient/patient_history.php">My Medical History</a></li>
                <li><a href="/Hospital_Management_System/patient/message.php">Send Message</a></li>
                <li><a href="/Hospital_Management_System/patient/logout.php">Logout</a></li>
            <?php } elseif (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) { ?>
                <!-- 未登录用户导航菜单 -->
                <li><a href="/Hospital_Management_System/index.php">Home</a></li>
            <?php } ?>
        </ul>
    </nav>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.navbar ul').classList.toggle('show');
        });
    });
    </script>
</body>
</html> 