<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Get admin details
$admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Administrator';

// Get statistics
$total_patients = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='patient'")->fetch_assoc()['count'];
$today_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetch_assoc()['count'];
$total_doctors = $conn->query("SELECT COUNT(*) as count FROM doctors")->fetch_assoc()['count'];

// Get monthly revenue
$current_month = date('Y-m');
$revenue_result = $conn->query("SELECT SUM(amount) as total FROM billing WHERE DATE_FORMAT(created_at, '%Y-%m') = '$current_month'");
$monthly_revenue = $revenue_result->fetch_assoc()['total'] ?? 0;
$formatted_revenue = '$' . number_format($monthly_revenue, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Dental Clinic</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8fafc;
            color: #1f2937;
        }

        /* Navigation Bar */
        .navbar {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            padding: 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            height: 70px;
        }

        /* Brand */
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            font-weight: 700;
        }

        .brand-icon {
            background: white;
            color: #4f46e5;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .brand-main {
            font-size: 18px;
            color: white;
            font-weight: 700;
        }

        .brand-sub {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Navigation Menu */
        .nav-menu {
            display: flex;
            align-items: center;
            gap: 8px;
            list-style: none;
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            text-decoration: none;
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .nav-link i {
            font-size: 16px;
        }

        /* Dropdown */
        .dropdown {
            position: relative;
        }

        .dropdown-toggle::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            margin-left: 8px;
            font-size: 12px;
            transition: transform 0.2s ease;
        }

        .dropdown:hover .dropdown-toggle::after {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            min-width: 200px;
            padding: 8px 0;
            margin-top: 8px;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: block;
            padding: 10px 16px;
            color: #374151;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: #f3f4f6;
            color: #4f46e5;
            text-decoration: none;
        }

        .dropdown-item i {
            width: 16px;
            margin-right: 8px;
            color: #9ca3af;
        }

        .dropdown-item:hover i {
            color: #4f46e5;
        }

        /* User Profile */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 50px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: white;
            color: #4f46e5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .user-name {
            color: white;
            font-weight: 600;
            font-size: 13px;
        }

        .user-role {
            color: rgba(255, 255, 255, 0.8);
            font-size: 11px;
        }

        .logout-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            margin-left: 8px;
        }

        .logout-btn:hover {
            background: #dc2626;
            color: white;
            text-decoration: none;
        }

        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #6b7280;
            font-size: 16px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .stat-icon.patients { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .stat-icon.appointments { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-icon.doctors { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-icon.revenue { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .dashboard-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            color: inherit;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1rem;
        }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .card-icon.users { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .card-icon.doctors { background: linear-gradient(135deg, #10b981, #059669); }
        .card-icon.appointments { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .card-icon.records { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .card-icon.billing { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .card-icon.reports { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .card-icon.services { background: linear-gradient(135deg, #84cc16, #65a30d); }
        .card-icon.logs { background: linear-gradient(135deg, #6b7280, #4b5563); }
        .card-icon.messages { background: linear-gradient(135deg, #f97316, #ea580c); }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .card-description {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.4;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }
            
            .navbar-container {
                padding: 0 1rem;
            }
            
            .brand-text {
                display: none;
            }
            
            .user-info {
                display: none;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Brand -->
            <a href="dashboard.php" class="navbar-brand">
                <div class="brand-icon">
                    <i class="fas fa-tooth"></i>
                </div>
                <div class="brand-text">
                    <div class="brand-main">DentalCare</div>
                    <div class="brand-sub">Admin Panel</div>
                </div>
            </a>

            <!-- Navigation Menu -->
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>
                
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <i class="fas fa-users"></i>
                        Patients
                    </a>
                    <div class="dropdown-menu">
                        <a href="manage_users.php" class="dropdown-item">
                            <i class="fas fa-list"></i>
                            All Patients
                        </a>
                        <a href="add_user.php" class="dropdown-item">
                            <i class="fas fa-plus"></i>
                            Add Patient
                        </a>
                        <a href="patient_records.php" class="dropdown-item">
                            <i class="fas fa-file-medical"></i>
                            Medical Records
                        </a>
                    </div>
                </li>

                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <i class="fas fa-user-md"></i>
                        Doctors
                    </a>
                    <div class="dropdown-menu">
                        <a href="manage_doctors.php" class="dropdown-item">
                            <i class="fas fa-list"></i>
                            All Doctors
                        </a>
                        <a href="add_doctor_profile.php" class="dropdown-item">
                            <i class="fas fa-plus"></i>
                            Add Doctor
                        </a>
                    </div>
                </li>

                <li class="nav-item">
                    <a href="manage_appointments.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        Appointments
                    </a>
                </li>

                <li class="nav-item">
                    <a href="patient_history.php" class="nav-link">
                        <i class="fas fa-history"></i>
                        History
                    </a>
                </li>

                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <i class="fas fa-cog"></i>
                        System
                    </a>
                    <div class="dropdown-menu">
                        <a href="manage_service.php" class="dropdown-item">
                            <i class="fas fa-tools"></i>
                            Services
                        </a>
                        <a href="reports.php" class="dropdown-item">
                            <i class="fas fa-chart-bar"></i>
                            Reports
                        </a>
                        <a href="billing.php" class="dropdown-item">
                            <i class="fas fa-dollar-sign"></i>
                            Billing
                        </a>
                        <a href="system_logs.php" class="dropdown-item">
                            <i class="fas fa-history"></i>
                            System Logs
                        </a>
                    </div>
                </li>
            </ul>

            <!-- User Profile -->
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($admin_name); ?></div>
                    <div class="user-role">Administrator</div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($admin_name); ?>! Here's what's happening at your clinic today.</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon patients">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $total_patients; ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon appointments">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $today_appointments; ?></div>
                <div class="stat-label">Today's Appointments</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon doctors">
                        <i class="fas fa-user-md"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $total_doctors; ?></div>
                <div class="stat-label">Total Doctors</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon revenue">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $formatted_revenue; ?></div>
                <div class="stat-label">Monthly Revenue</div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <a href="manage_users.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="card-title">Manage Patients</div>
                        <div class="card-description">View, add, edit, and manage patient information</div>
                    </div>
                </div>
            </a>

            <a href="manage_doctors.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon doctors">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div>
                        <div class="card-title">Manage Doctors</div>
                        <div class="card-description">View and manage doctor profiles and schedules</div>
                    </div>
                </div>
            </a>

            <a href="manage_appointments.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon appointments">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <div class="card-title">Appointments</div>
                        <div class="card-description">Schedule and manage patient appointments</div>
                    </div>
                </div>
            </a>

            <a href="patient_records.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon records">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <div>
                        <div class="card-title">Medical Records</div>
                        <div class="card-description">Access and manage patient medical history</div>
                    </div>
                </div>
            </a>

            <a href="billing.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon billing">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div>
                        <div class="card-title">Billing</div>
                        <div class="card-description">Manage billing and payment records</div>
                    </div>
                </div>
            </a>

            <a href="reports.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon reports">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div>
                        <div class="card-title">Reports</div>
                        <div class="card-description">View detailed reports and analytics</div>
                    </div>
                </div>
            </a>

            <a href="manage_service.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon services">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div>
                        <div class="card-title">Services</div>
                        <div class="card-description">Manage clinic services and pricing</div>
                    </div>
                </div>
            </a>

            <a href="system_logs.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon logs">
                        <i class="fas fa-history"></i>
                    </div>
                    <div>
                        <div class="card-title">System Logs</div>
                        <div class="card-description">Monitor system activity and logs</div>
                    </div>
                </div>
            </a>

            <a href="messages.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon messages">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div>
                        <div class="card-title">Messages</div>
                        <div class="card-description">View and respond to patient messages</div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</body>
</html>