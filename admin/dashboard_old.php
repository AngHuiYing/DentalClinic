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

// 获取统计数据
// 1. 总患者数 (用户表中角色为patient的用户)
$total_patients_query = "SELECT COUNT(*) as count FROM users WHERE role = 'patient'";
$total_patients_result = $conn->query($total_patients_query);
$total_patients = $total_patients_result->fetch_assoc()['count'];

// 2. 今日预约数
$today_appointments_query = "SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()";
$today_appointments_result = $conn->query($today_appointments_query);
$today_appointments = $today_appointments_result->fetch_assoc()['count'];

// 3. 活跃医生数 (用户表中角色为doctor的用户)
$active_doctors_query = "SELECT COUNT(*) as count FROM users WHERE role = 'doctor'";
$active_doctors_result = $conn->query($active_doctors_query);
$active_doctors = $active_doctors_result->fetch_assoc()['count'];

// 4. 本月收入 (从billing表获取本月的总收入)
$monthly_revenue_query = "SELECT COALESCE(SUM(amount), 0) as total FROM billing WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
$monthly_revenue_result = $conn->query($monthly_revenue_query);
$monthly_revenue = $monthly_revenue_result->fetch_assoc()['total'];

// 格式化收入显示
$formatted_revenue = 'RM ' . number_format($monthly_revenue, 0);
if ($monthly_revenue >= 1000) {
    $formatted_revenue = 'RM ' . number_format($monthly_revenue/1000, 1) . 'k';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Green Life Dental Clinic</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #10b981;
            --accent-color: #f59e0b;
            --danger-color: #ef4444;
            --success-color: #22c55e;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f8fafc;
            min-height: 100vh;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 80px; /* Account for fixed navbar */
        }

        /* Mega Menu Navbar */
        .mega-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #1e40af 100%);
            z-index: 1000;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
            position: relative;
        }

        .navbar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 80px;
            padding: 0 1rem;
        }

        /* Brand Logo */
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .navbar-brand:hover {
            color: white;
            text-decoration: none;
        }

        .brand-icon {
            background: white;
            color: var(--primary-color);
            padding: 10px;
            border-radius: 12px;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .brand-main {
            font-size: 1.2rem;
            color: white;
        }

        .brand-sub {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Mega Menu */
        .mega-menu {
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2px;
            margin: 0;
            padding: 0;
            flex: 1;
        }

        .mega-item {
            position: relative;
        }

        .mega-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .mega-link:hover,
        .mega-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
        }

        .mega-link i {
            font-size: 1rem;
        }

        /* Mega Dropdown */
        .mega-dropdown {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            min-width: 300px;
            max-width: 500px;
            z-index: 1001;
            border: 1px solid rgba(37, 99, 235, 0.1);
        }

        .mega-item:hover .mega-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(8px);
        }

        .mega-dropdown-content {
            padding: 1.5rem;
        }

        .mega-section {
            margin-bottom: 1.5rem;
        }

        .mega-section:last-child {
            margin-bottom: 0;
        }

        .mega-section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .mega-section-title i {
            font-size: 1.1rem;
        }

        .mega-section-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .mega-section-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            color: #374151;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .mega-section-link:hover {
            background: #f3f4f6;
            color: var(--primary-color);
            text-decoration: none;
            transform: translateX(4px);
        }

        .mega-section-link i {
            font-size: 0.9rem;
            color: #6b7280;
            transition: color 0.2s ease;
        }

        .mega-section-link:hover i {
            color: var(--primary-color);
        }

        /* User Section */
        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.2s ease;
            color: white;
        }

        .user-info:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-role {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        .logout-btn {
            padding: 8px 16px;
            background: #ef4444;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .logout-btn:hover {
            background: #dc2626;
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 8px;
        }

        /* Main Container */
        .main-container {
            background: white;
            margin: 20px;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
            z-index: 1;
        }

        .header-section {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 2rem;
            position: relative;
        }

        .welcome-text {
            position: relative;
            z-index: 2;
        }

        .welcome-text h1 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .welcome-text p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        /* Content Section */
        .content-section {
            padding: 2rem;
        }

        /* Stats Row */
        .stats-row {
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            border: 1px solid #e5e7eb;
            border-top: 4px solid var(--card-color);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--card-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-weight: 500;
        }

        .stat-patients { --card-color: #3b82f6; }
        .stat-appointments { --card-color: #10b981; }
        .stat-doctors { --card-color: #f59e0b; }
        .stat-revenue { --card-color: #ef4444; }

        /* Dashboard Cards */
        .dashboard-cards {
            margin-top: 2rem;
        }

        .dashboard-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            display: block;
            transition: transform 0.2s ease;
            box-shadow: var(--card-shadow);
            border: none;
            height: 100%;
            position: relative;
            border-top: 4px solid var(--card-color);
        }

        .dashboard-card:hover {
            transform: translateY(-3px);
            text-decoration: none;
            color: inherit;
        }

        .card-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.4rem;
            color: white;
            background: var(--card-color);
            position: relative;
            z-index: 2;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .card-description {
            color: #6b7280;
            font-size: 0.9rem;
            line-height: 1.5;
            position: relative;
            z-index: 2;
        }

        /* Color variations for cards */
        .card-users { --card-color: #3b82f6; }
        .card-doctors { --card-color: #10b981; }
        .card-appointments { --card-color: #f59e0b; }
        .card-billing { --card-color: #ef4444; }
        .card-reports { --card-color: #8b5cf6; }
        .card-settings { --card-color: #06b6d4; }

        /* Responsive Design */
        @media (max-width: 768px) {
            .mega-menu {
                display: none;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .main-container {
                margin: 10px;
            }

            .header-section {
                padding: 1.5rem;
            }

            .content-section {
                padding: 1rem;
            }

            .dashboard-card {
                padding: 1.2rem;
                margin-bottom: 1rem;
            }

            .welcome-text h1 {
                font-size: 1.4rem;
            }

            .stat-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .stat-number {
                font-size: 1.8rem;
            }

            .user-info {
                gap: 8px;
                padding: 6px 12px;
            }

            .user-details .user-name {
                font-size: 0.8rem;
            }

            .user-details .user-role {
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mega Menu Navigation -->
    <nav class="mega-navbar">
        <div class="navbar-container">
            <div class="navbar-header">
                <!-- Brand Logo -->
                <a href="../index.php" class="navbar-brand">
                    <div class="brand-icon">
                        <i class="fas fa-tooth"></i>
                    </div>
                    <div class="brand-text">
                        <span class="brand-main">Green Life</span>
                        <span class="brand-sub">Dental Clinic</span>
                    </div>
                </a>

                <!-- Mega Menu -->
                <ul class="mega-menu" id="megaMenu">
                    <!-- Dashboard -->
                    <li class="mega-item">
                        <a href="dashboard.php" class="mega-link active">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </li>

                    <!-- Patients Menu -->
                    <li class="mega-item">
                        <a href="#" class="mega-link">
                            <i class="fas fa-users"></i>
                            Patients
                            <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
                        </a>
                        <div class="mega-dropdown">
                            <div class="mega-dropdown-content">
                                <div class="mega-section">
                                    <div class="mega-section-title">
                                        <i class="fas fa-user-friends"></i>
                                        Patient Management
                                    </div>
                                    <div class="mega-section-links">
                                        <a href="manage_users.php" class="mega-section-link">
                                            <i class="fas fa-users"></i>
                                            Manage All Patients
                                        </a>
                                        <a href="patient_records.php" class="mega-section-link">
                                            <i class="fas fa-file-medical"></i>
                                            Patient Records
                                        </a>
                                        <a href="patient_history.php" class="mega-section-link">
                                            <i class="fas fa-history"></i>
                                            Medical History
                                        </a>
                                    </div>
                                </div>
                                <div class="mega-section">
                                    <div class="mega-section-title">
                                        <i class="fas fa-plus"></i>
                                        Add New
                                    </div>
                                    <div class="mega-section-links">
                                        <a href="add_user.php" class="mega-section-link">
                                            <i class="fas fa-user-plus"></i>
                                            Register Patient
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>

                    <!-- Doctors Menu -->
                    <li class="mega-item">
                        <a href="#" class="mega-link">
                            <i class="fas fa-user-md"></i>
                            Doctors
                            <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
                        </a>
                        <div class="mega-dropdown">
                            <div class="mega-dropdown-content">
                                <div class="mega-section">
                                    <div class="mega-section-title">
                                        <i class="fas fa-stethoscope"></i>
                                        Doctor Management
                                    </div>
                                    <div class="mega-section-links">
                                        <a href="manage_doctors.php" class="mega-section-link">
                                            <i class="fas fa-user-md"></i>
                                            Manage Doctors
                                        </a>
                                        <a href="add_doctor_profile.php" class="mega-section-link">
                                            <i class="fas fa-plus-circle"></i>
                                            Add Doctor
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>

                    <!-- Appointments Menu -->
                    <li class="mega-item">
                        <a href="#" class="mega-link">
                            <i class="fas fa-calendar-check"></i>
                            Appointments
                            <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
                        </a>
                        <div class="mega-dropdown">
                            <div class="mega-dropdown-content">
                                <div class="mega-section">
                                    <div class="mega-section-title">
                                        <i class="fas fa-calendar-alt"></i>
                                        Schedule Management
                                    </div>
                                    <div class="mega-section-links">
                                        <a href="manage_appointments.php" class="mega-section-link">
                                            <i class="fas fa-calendar-check"></i>
                                            View Appointments
                                        </a>
                                        <a href="add_appointment.php" class="mega-section-link">
                                            <i class="fas fa-calendar-plus"></i>
                                            Add Appointment
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>

                    <!-- Services & Billing Menu -->
                    <li class="mega-item">
                        <a href="#" class="mega-link">
                            <i class="fas fa-cogs"></i>
                            Services
                            <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
                        </a>
                        <div class="mega-dropdown">
                            <div class="mega-dropdown-content">
                                <div class="mega-section">
                                    <div class="mega-section-title">
                                        <i class="fas fa-tools"></i>
                                        Service Management
                                    </div>
                                    <div class="mega-section-links">
                                        <a href="manage_service.php" class="mega-section-link">
                                            <i class="fas fa-cogs"></i>
                                            Manage Services
                                        </a>
                                        <a href="billing.php" class="mega-section-link">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                            Billing
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>

                    <!-- System Menu -->
                    <li class="mega-item">
                        <a href="#" class="mega-link">
                            <i class="fas fa-cog"></i>
                            System
                            <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
                        </a>
                        <div class="mega-dropdown">
                            <div class="mega-dropdown-content">
                                <div class="mega-section">
                                    <div class="mega-section-title">
                                        <i class="fas fa-tools"></i>
                                        System Tools
                                    </div>
                                    <div class="mega-section-links">
                                        <a href="reports.php" class="mega-section-link">
                                            <i class="fas fa-chart-bar"></i>
                                            Reports
                                        </a>
                                        <a href="system_logs.php" class="mega-section-link">
                                            <i class="fas fa-list-alt"></i>
                                            System Logs
                                        </a>
                                        <a href="messages.php" class="mega-section-link">
                                            <i class="fas fa-envelope"></i>
                                            Messages
                                        </a>
                                        <a href="clinic_settings.php" class="mega-section-link">
                                            <i class="fas fa-hospital"></i>
                                            Clinic Settings
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>

                <!-- Mobile Menu Button -->
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>

                <!-- User Section -->
                <div class="user-section">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($admin_name ?? 'A', 0, 2)); ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($admin_name ?? 'Admin'); ?></span>
                            <span class="user-role">Administrator</span>
                        </div>
                    </div>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>
            color: inherit;
            display: block;
            transition: transform 0.2s ease;
            box-shadow: var(--card-shadow);
            border: none;
            height: 100%;
            position: relative;
            border-top: 4px solid var(--card-color);
        }

        .dashboard-card:hover {
            transform: translateY(-3px);
            text-decoration: none;
            color: inherit;
        }

        .card-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.4rem;
            color: white;
            background: var(--card-color);
            position: relative;
            z-index: 2;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .card-description {
            color: #6b7280;
            font-size: 0.9rem;
            line-height: 1.5;
            position: relative;
            z-index: 2;
        }

        /* Color variations for cards */
        .card-users { --card-color: #3b82f6; }
        .card-doctors { --card-color: #10b981; }
        .card-appointments { --card-color: #f59e0b; }
        .card-scheduling { --card-color: #8b5cf6; }
        .card-records { --card-color: #ef4444; }
        .card-services { --card-color: #14b8a6; }
        .card-history { --card-color: #06b6d4; }
        .card-billing { --card-color: #84cc16; }
        .card-reports { --card-color: #f97316; }
        .card-messages { --card-color: #ec4899; }
        .card-logs { --card-color: #6366f1; }
        .card-reviews { --card-color: #fbbf24; }

        .stats-row {
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            border-left: 4px solid var(--stat-color);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            transition: transform 0.2s ease;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--stat-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-patients { --stat-color: #3b82f6; }
        .stat-appointments { --stat-color: #10b981; }
        .stat-doctors { --stat-color: #f59e0b; }
        .stat-revenue { --stat-color: #ef4444; }

        /* Enhanced Responsive Design */
        @media (max-width: 768px) {
            .main-container {
                margin: 90px 10px 10px 10px;
                border-radius: 1rem;
            }
            
            .header-section {
                padding: 1.5rem;
                text-align: center;
            }
            
            .dashboard-cards {
                padding: 1rem;
            }
            
            .dashboard-card {
                padding: 1.2rem;
                margin-bottom: 1rem;
            }
            
            .welcome-text h1 {
                font-size: 1.4rem;
            }
            
            .stat-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .stat-number {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 576px) {
            .main-container {
                margin: 85px 5px 5px 5px;
            }
            
            .header-section {
                padding: 1rem;
            }
            
            .dashboard-cards {
                padding: 0.8rem;
            }
            
            .card-icon {
                width: 2.5rem;
                height: 2.5rem;
                font-size: 1.2rem;
            }
            
            .welcome-text h1 {
                font-size: 1.2rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
        }

        /* Additional enhancements */
        .dashboard-card:active {
            transform: translateY(-2px) !important;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="main-container">
        <div class="header-section">
            <div class="welcome-text">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <i class="fas fa-hospital" style="font-size: 3rem;"></i>
                    </div>
                    <div>
                        <h1>Welcome back, <?php echo htmlspecialchars($admin_name); ?>!</h1>
                        <p>Manage your dental clinic with ease and efficiency</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="content-section">
            <!-- Quick Stats Row -->
            <div class="row stats-row">
                <div class="col-6 col-md-3">
                    <div class="stat-card stat-patients">
                        <div class="stat-number"><?php echo $total_patients; ?></div>
                        <div class="stat-label">Total Patients</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card stat-appointments">
                        <div class="stat-number"><?php echo $today_appointments; ?></div>
                        <div class="stat-label">Today's Appointments</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card stat-doctors">
                        <div class="stat-number"><?php echo $active_doctors; ?></div>
                        <div class="stat-label">Active Doctors</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card stat-revenue">
                        <div class="stat-number"><?php echo $formatted_revenue; ?></div>
                        <div class="stat-label">Monthly Revenue</div>
                    </div>
                </div>
            </div>

            <!-- Management Cards -->
            <div class="dashboard-cards">
                <div class="row">
                    <!-- Manage Users -->
                    <div class="col-12 col-sm-6 col-xl-4 mb-4">
                        <a href="./manage_users.php" class="dashboard-card card-users">
                            <div class="card-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5 class="card-title">Manage Patients</h5>
                            <p class="card-description">Add, edit, and manage patient accounts and profiles</p>
                        </a>
                    </div>

                    <!-- Manage Doctors -->
                    <div class="col-12 col-sm-6 col-xl-4 mb-4">
                        <a href="./manage_doctors.php" class="dashboard-card card-doctors">
                            <div class="card-icon">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <h5 class="card-title">Manage Doctors</h5>
                            <p class="card-description">Oversee doctor profiles and clinic staff</p>
                        </a>
                    </div>

                    <!-- Manage Appointments -->
                    <div class="col-12 col-sm-6 col-xl-4 mb-4">
                        <a href="./manage_appointments.php" class="dashboard-card card-appointments">
                            <div class="card-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h5 class="card-title">Appointments</h5>
                            <p class="card-description">Schedule and manage patient appointments</p>
                        </a>
                    </div>

                    <!-- Patient Records -->
                    <div class="col-12 col-sm-6 col-xl-4 mb-4">
                        <a href="./patient_records.php" class="dashboard-card card-billing">
                            <div class="card-icon">
                                <i class="fas fa-file-medical"></i>
                            </div>
                            <h5 class="card-title">Patient Records</h5>
                            <p class="card-description">Access and manage patient medical records</p>
                        </a>
                    </div>

                    <!-- Billing -->
                    <div class="col-12 col-sm-6 col-xl-4 mb-4">
                        <a href="./billing.php" class="dashboard-card card-billing">
                            <div class="card-icon">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <h5 class="card-title">Billing & Finance</h5>
                            <p class="card-description">Manage payments, invoices and financial records</p>
                        </a>
                    </div>

                    <!-- Reports -->
                    <div class="col-12 col-sm-6 col-xl-4 mb-4">
                        <a href="./reports.php" class="dashboard-card card-reports">
                            <div class="card-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h5 class="card-title">Reports & Analytics</h5>
                            <p class="card-description">View clinic performance and generate reports</p>
                        </a>
                    </div>

                    <!-- Services -->
                    <div class="col-12 col-sm-6 col-xl-4 mb-4">
                        <a href="./manage_service.php" class="dashboard-card card-settings">
                            <div class="card-icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <h5 class="card-title">Services</h5>
                            <p class="card-description">Manage clinic services and treatment options</p>
                        </a>
                    </div>

                    <!-- System Logs -->
                    <div class="col-12 col-sm-6 col-xl-4 mb-4">
                        <a href="./system_logs.php" class="dashboard-card card-settings">
                            <div class="card-icon">
                                <i class="fas fa-list-alt"></i>
                            </div>
                            <h5 class="card-title">System Logs</h5>
                            <p class="card-description">Monitor system activity and user actions</p>
                        </a>
                    </div>

                    <!-- Messages -->
                    <div class="col-12 col-sm-6 col-xl-4 mb-4">
                        <a href="./messages.php" class="dashboard-card card-settings">
                            <div class="card-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <h5 class="card-title">Messages</h5>
                            <p class="card-description">Communicate with patients and staff</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Bootstrap CSS for grid system -->
    <style>
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-left: -0.75rem;
            margin-right: -0.75rem;
        }

        .col-12 {
            flex: 0 0 100%;
            max-width: 100%;
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }

        .col-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }

        .mb-4 {
            margin-bottom: 1.5rem;
        }

        @media (min-width: 576px) {
            .col-sm-6 {
                flex: 0 0 50%;
                max-width: 50%;
            }
        }

        @media (min-width: 768px) {
            .col-md-3 {
                flex: 0 0 25%;
                max-width: 25%;
            }
        }

        @media (min-width: 1200px) {
            .col-xl-4 {
                flex: 0 0 33.333333%;
                max-width: 33.333333%;
            }
        }
    </style>
</body>
</html>
                    <div class="card-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="card-title">Manage Users</div>
                    <div class="card-description">Add, edit, or remove system users and manage their roles and permissions.</div>
                </a>
            </div>

            <!-- Manage Dentists -->
            <div class="col-12 col-sm-6 col-xl-4">
                <a href="./manage_doctors.php" class="dashboard-card card-doctors">
                    <div class="card-icon">
                        <i class="bi bi-person-badge-fill"></i>
                    </div>
                    <div class="card-title">Manage Dentists</div>
                    <div class="card-description">View and manage dentist profiles, specialties, and schedules.</div>
                </a>
            </div>

            <!-- Manage Appointments -->
            <div class="col-12 col-sm-6 col-xl-4">
                <a href="./manage_appointments.php" class="dashboard-card card-appointments">
                    <div class="card-icon">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>
                    <div class="card-title">Manage Appointments</div>
                    <div class="card-description">Schedule, modify, and track all patient appointments efficiently.</div>
                </a>
            </div>

            <!-- Dentist's Booking Time -->
            <div class="col-12 col-sm-6 col-xl-4">
                <a href="./admin_set_unavailable.php" class="dashboard-card card-scheduling">
                    <div class="card-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="card-title">Dentist Availability</div>
                    <div class="card-description">Manage dentist schedules and set unavailable time slots.</div>
                </a>
            </div>

            <!-- Patient Records -->
            <div class="col-12 col-sm-6 col-xl-4">
                <a href="./patient_records.php" class="dashboard-card card-records">
                    <div class="card-icon">
                        <i class="bi bi-journal-medical"></i>
                    </div>
                    <div class="card-title">Patient Records</div>
                    <div class="card-description">Access and manage comprehensive patient medical records.</div>
                </a>
            </div>

            <!-- Manage Services -->
            <div class="col-12 col-sm-6 col-xl-4">
                <a href="./manage_service.php" class="dashboard-card card-services">
                    <div class="card-icon">
                        <i class="bi bi-capsule"></i>
                    </div>
                    <div class="card-title">Manage Services</div>
                    <div class="card-description">Access and manage dental services offered by the clinic.</div>
                </a>
            </div>

            <!-- Medical History -->
            <div class="col-12 col-sm-6 col-xl-4">
                <a href="./patient_history.php" class="dashboard-card card-history">
                    <div class="card-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="card-title">Patient's Medical Record History</div>
                    <div class="card-description">Review patient treatment history and medical documentation.</div>
                </a>
            </div>

            <!-- Billing -->
            <div class="col-12 col-sm-6 col-xl-4">
                <a href="./billing.php" class="dashboard-card card-billing">
                    <div class="card-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div class="card-title">Billing & Payments</div>
                    <div class="card-description">Manage patient billing, payments, and financial transactions.</div>
                </a>
            </div>

            <!-- Reports -->
            <div class="col-12 col-sm-6 col-xl-4">
                <a href="./reports.php" class="dashboard-card card-reports">
                    <div class="card-icon">
                        <i class="bi bi-bar-chart-fill"></i>
                    </div>
                    <div class="card-title">Reports & Analytics</div>
                    <div class="card-description">View detailed reports on clinic performance and analytics.</div>
                </a>
            </div>

            <!-- Messages -->
            <div class="col-12 col-sm-6 col-xl-4">
                <a href="./messages.php" class="dashboard-card card-messages">
                    <div class="card-icon">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>
                    <div class="card-title">Patient Messages</div>
                    <div class="card-description">Respond to patient inquiries and manage communications.</div>
                </a>
            </div>

            <!-- System Logs -->
            <div class="col-12 col-sm-6 col-xl-4">
                <a href="./system_logs.php" class="dashboard-card card-logs">
                    <div class="card-icon">
                        <i class="bi bi-clipboard-data-fill"></i>
                    </div>
                    <div class="card-title">System Activity</div>
                    <div class="card-description">Monitor system logs and administrative activities.</div>
                </a>
            </div>

            <!-- Manage Reviews -->
            <div class="col-12 col-sm-6 col-xl-4">
                <a href="./manage_reviews.php" class="dashboard-card card-reviews">
                    <div class="card-icon">
                        <i class="bi bi-star-fill"></i>
                    </div>
                    <div class="card-title">Manage Reviews</div>
                    <div class="card-description">Review and moderate patient feedback for doctors.</div>
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Performance monitoring
    const loadStart = performance.now();
    
    // Simple time display
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        if (!document.getElementById('current-time')) {
            const timeDiv = document.createElement('div');
            timeDiv.id = 'current-time';
            timeDiv.className = 'mt-2 opacity-75';
            timeDiv.style.fontSize = '0.9rem';
            timeDiv.innerHTML = `<i class="bi bi-clock me-2"></i>${timeString}`;
            document.querySelector('.welcome-text').appendChild(timeDiv);
        } else {
            document.getElementById('current-time').innerHTML = `<i class="bi bi-clock me-2"></i>${timeString}`;
        }
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        const loadEnd = performance.now();
        console.log(`Dashboard loaded in ${(loadEnd - loadStart).toFixed(2)}ms`);
        updateTime();
    });

    // Update time every minute
    setInterval(updateTime, 60000);
</script>
</body>
</html>