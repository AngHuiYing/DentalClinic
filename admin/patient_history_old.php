<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 搜尋參數
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$patient_email = isset($_GET['patient_email']) ? $_GET['patient_email'] : "";

// Pagination variables
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($current_page - 1) * $limit;

// 取得所有已批准病人
$sql = "
    SELECT DISTINCT a.patient_email, a.patient_name, a.patient_phone
    FROM appointments a
    WHERE a.status = 'confirmed' AND a.patient_email IS NOT NULL
";
if (!empty($search)) {
    $sql .= " AND (a.patient_name LIKE ? OR a.patient_email LIKE ? OR a.patient_phone LIKE ?)";
    $stmt = $conn->prepare($sql);
    $searchParam = "%$search%";
    $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
    $stmt->execute();
    $patients = $stmt->get_result();
} else {
    $patients = $conn->query($sql);
}

// Admin can only view medical records - no add/delete functions
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Medical History - Green Life Dental Clinic</title>
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

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        /* Content Section */
        .content-section {
            padding: 2rem;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            border: 1px solid #e5e7eb;
        }

        .card-header {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            color: white;
            text-decoration: none;
        }

        /* Search Form */
        .search-form {
            display: flex;
            gap: 1rem;
            align-items: end;
        }

        .search-form .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        /* Patient Info */
        .patient-info {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .patient-info h4 {
            color: #0c5460;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
        }

        .patient-info p {
            margin: 0.25rem 0;
            color: #0c5460;
        }

        /* Medical Records */
        .medical-record {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-color);
        }

        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .record-title {
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }

        .record-meta {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .record-content h5 {
            color: #495057;
            margin: 1rem 0 0.5rem 0;
            font-size: 1rem;
        }

        .record-content p {
            margin-bottom: 0.75rem;
            color: #6c757d;
            line-height: 1.6;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

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

            .card {
                padding: 1.5rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .search-form {
                flex-direction: column;
            }

            .search-form .form-group {
                margin-bottom: 1rem;
            }

            .record-header {
                flex-direction: column;
                gap: 0.5rem;
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
                        <a href="dashboard.php" class="mega-link">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </li>

                    <!-- Patients Menu -->
                    <li class="mega-item">
                        <a href="#" class="mega-link active">
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
                            <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 2)); ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
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

<div class="main-container">
    <!-- Header Section -->
    <div class="header-section">
        <h1 class="page-title">📋 Patient Medical History</h1>
        <p class="page-subtitle">View comprehensive medical records - Read Only Access</p>
    </div>

    <!-- Main Content -->
    <div class="content-section">
        <!-- Patient Selection Card -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-injured"></i> Patient Selection
            </div>
            
            <form method="GET" action="">
                <div class="form-group">
                    <label for="patient_select" class="form-label">Select Patient:</label>
                    <select name="patient_email" id="patient_select" class="form-control" onchange="this.form.submit()">
                        <option value="">Choose a patient...</option>
                        <?php while ($patient = $patients->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($patient['patient_email']); ?>" 
                                    <?php echo ($patient_email === $patient['patient_email']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['patient_name']); ?> - 
                                <?php echo htmlspecialchars($patient['patient_email']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>

            <!-- Search Form -->
            <form method="GET" action="" class="search-form">
                <?php if (!empty($patient_email)): ?>
                    <input type="hidden" name="patient_email" value="<?php echo htmlspecialchars($patient_email); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="search" class="form-label">Search Patients:</label>
                    <input type="text" name="search" id="search" class="form-control" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name, email, or phone...">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>

        <?php if (!empty($patient_email)): ?>
            <?php
            // 取得選定病人的醫療記錄
            $stmt = $conn->prepare("
                SELECT pr.*, a.appointment_date, a.time_slot, d.name as doctor_name
                FROM patient_records pr
                LEFT JOIN appointments a ON pr.appointment_id = a.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                WHERE a.patient_email = ?
                ORDER BY pr.record_date DESC
            ");
            $stmt->bind_param("s", $patient_email);
            $stmt->execute();
            $records = $stmt->get_result();

            // 取得病人基本資訊
            $stmt2 = $conn->prepare("
                SELECT DISTINCT patient_name, patient_email, patient_phone
                FROM appointments
                WHERE patient_email = ?
                LIMIT 1
            ");
            $stmt2->bind_param("s", $patient_email);
            $stmt2->execute();
            $patient_info = $stmt2->get_result()->fetch_assoc();
            ?>

            <!-- Patient Information Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user"></i> Patient Information
                </div>
                
                <?php if ($patient_info): ?>
                    <div class="patient-info">
                        <h4><i class="fas fa-user"></i> <?php echo htmlspecialchars($patient_info['patient_name']); ?></h4>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($patient_info['patient_email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient_info['patient_phone']); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Medical Records Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-file-medical-alt"></i> Medical Records (<?php echo $records->num_rows; ?> records)
                </div>
                
                <?php if ($records->num_rows > 0): ?>
                    <?php while ($record = $records->fetch_assoc()): ?>
                        <div class="medical-record">
                            <div class="record-header">
                                <h3 class="record-title">
                                    <i class="fas fa-file-medical"></i>
                                    Record #<?php echo $record['id']; ?>
                                    <?php if ($record['doctor_name']): ?>
                                        - Dr. <?php echo htmlspecialchars($record['doctor_name']); ?>
                                    <?php endif; ?>
                                </h3>
                                <div class="record-meta">
                                    <i class="fas fa-calendar"></i>
                                    Record Date: <?php echo date('M d, Y', strtotime($record['record_date'])); ?>
                                    <?php if ($record['appointment_date']): ?>
                                        | Appointment: <?php echo date('M d, Y', strtotime($record['appointment_date'])); ?>
                                        <?php if ($record['time_slot']): ?>
                                            at <?php echo htmlspecialchars($record['time_slot']); ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="record-content">
                                <?php if ($record['diagnosis']): ?>
                                    <h5><i class="fas fa-stethoscope"></i> Diagnosis:</h5>
                                    <p><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                                <?php endif; ?>

                                <?php if ($record['treatment']): ?>
                                    <h5><i class="fas fa-procedures"></i> Treatment:</h5>
                                    <p><?php echo nl2br(htmlspecialchars($record['treatment'])); ?></p>
                                <?php endif; ?>

                                <?php if ($record['notes']): ?>
                                    <h5><i class="fas fa-sticky-note"></i> Additional Notes:</h5>
                                    <p><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                                <?php endif; ?>

                                <?php if ($record['prescription']): ?>
                                    <h5><i class="fas fa-prescription-bottle-alt"></i> Prescription:</h5>
                                    <p><?php echo nl2br(htmlspecialchars($record['prescription'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i>
                        No medical records found for this patient.
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- No Patient Selected -->
            <div class="card">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Please select a patient to view their medical history.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>