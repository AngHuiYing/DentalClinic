<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

// Get patient information and statistics
include_once('../includes/db.php');

$patient_id = $_SESSION['user_id'];
$patient_name = $_SESSION['user_name'];

// Get patient email
$email_sql = "SELECT email FROM users WHERE id = ?";
$email_stmt = $conn->prepare($email_sql);
$email_stmt->bind_param("i", $patient_id);
$email_stmt->execute();
$email_result = $email_stmt->get_result();
$user_data = $email_result->fetch_assoc();
$patient_email = $user_data['email'];

// Check if user profile information is complete
$profile_sql = "SELECT gender, date_of_birth FROM users WHERE id = ?";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("i", $patient_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$profile_data = $profile_result->fetch_assoc();
$profile_incomplete = (is_null($profile_data['gender']) || is_null($profile_data['date_of_birth']));

// Get upcoming appointments count (today and next 5 days)
$upcoming_appointments = 0;
$sql = "SELECT COUNT(*) as count FROM appointments WHERE (patient_id = ? OR patient_email = ?) AND appointment_date >= CURDATE() AND appointment_date <= DATE_ADD(CURDATE(), INTERVAL 5 DAY) AND status != 'cancelled'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $patient_id, $patient_email);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $upcoming_appointments = $row['count'];
}

// Get total appointments count
$total_appointments = 0;
$sql = "SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? OR patient_email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $patient_id, $patient_email);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $total_appointments = $row['count'];
}

// Get completed appointments count
$completed_appointments = 0;
$sql = "SELECT COUNT(*) as count FROM appointments WHERE (patient_id = ? OR patient_email = ?) AND status = 'completed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $patient_id, $patient_email);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $completed_appointments = $row['count'];
}

// Get next appointment (today and next 5 days)
$next_appointment = null;
$sql = "SELECT a.*, d.name as doctor_name FROM appointments a 
        LEFT JOIN doctors d ON a.doctor_id = d.id 
        WHERE (a.patient_id = ? OR a.patient_email = ?) AND a.appointment_date >= CURDATE() AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 5 DAY) AND a.status != 'cancelled'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC 
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $patient_id, $patient_email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $next_appointment = $result->fetch_assoc();
}

// Get recent appointments (past 5 days, excluding today)
$recent_appointments = [];
$sql = "SELECT a.*, d.name as doctor_name FROM appointments a 
        LEFT JOIN doctors d ON a.doctor_id = d.id 
        WHERE (a.patient_id = ? OR a.patient_email = ?) AND a.appointment_date < CURDATE() AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)
        ORDER BY a.appointment_date DESC, a.appointment_time DESC 
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $patient_id, $patient_email);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_appointments[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - Dental Care Clinic</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #7dd3fc;
            --secondary: #06d6a0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --purple: #8b5cf6;
            --dark: #1e293b;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1);
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-2xl: 2rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Header Styles */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem 0;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='4'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
            z-index: 1;
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .welcome-text h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .welcome-text p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn-logout {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-2px);
        }

        /* Main Content */
        .main-content {
            padding: 3rem 0;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .stat-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-info h3 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }

        .stat-info p {
            color: var(--gray-600);
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
        }

        .stat-icon {
            width: 4rem;
            height: 4rem;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
        .stat-icon.success { background: linear-gradient(135deg, var(--success), #059669); }
        .stat-icon.warning { background: linear-gradient(135deg, var(--warning), #d97706); }

        /* Quick Actions Grid */
        .quick-actions {
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .section-subtitle {
            color: var(--gray-600);
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            color: inherit;
            text-decoration: none;
        }

        .action-card:hover .action-icon {
            transform: scale(1.1);
        }

        .action-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .action-icon {
            width: 3rem;
            height: 3rem;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            transition: all 0.3s ease;
        }

        .action-icon.primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
        .action-icon.success { background: linear-gradient(135deg, var(--success), #059669); }
        .action-icon.info { background: linear-gradient(135deg, var(--info), #2563eb); }
        .action-icon.warning { background: linear-gradient(135deg, var(--warning), #d97706); }
        .action-icon.danger { background: linear-gradient(135deg, var(--danger), #dc2626); }
        .action-icon.secondary { background: linear-gradient(135deg, var(--secondary), #059669); }
        .action-icon.purple { background: linear-gradient(135deg, var(--purple), #7c3aed); }
        .action-icon.teal { background: linear-gradient(135deg, #14b8a6, #0d9488); }

        .action-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }

        .action-description {
            color: var(--gray-600);
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 0;
        }

        /* Appointment Cards */
        .appointment-section {
            margin-bottom: 3rem;
        }

        .appointment-card {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .appointment-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .appointment-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .appointment-status {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-lg);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .appointment-body {
            padding: 2rem;
        }

        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .detail-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: var(--gray-100);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1rem;
        }

        .detail-text {
            font-weight: 500;
            color: var(--gray-700);
        }

        .detail-label {
            font-size: 0.85rem;
            color: var(--gray-500);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        .empty-icon {
            width: 5rem;
            height: 5rem;
            background: var(--gray-100);
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--gray-400);
            font-size: 2rem;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .empty-description {
            color: var(--gray-600);
            font-size: 1rem;
            margin-bottom: 2rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                padding: 1.5rem 0;
            }
            
            .welcome-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .welcome-text h1 {
                font-size: 2rem;
            }
            
            .main-content {
                padding: 2rem 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .appointment-details {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .action-card {
                padding: 1.5rem;
            }
            
            .appointment-body {
                padding: 1.5rem;
            }
            
            .table-responsive {
                margin: 0 -1rem;
                border-radius: 0;
            }
            
            .table {
                font-size: 0.85rem;
                min-width: 600px;
            }
            
            .table th,
            .table td {
                padding: 0.8rem 0.6rem;
            }
        }

        /* Animation */
        .fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Table Styles for Appointments */
        .table-responsive {
            border-radius: var(--radius-lg);
            overflow: hidden;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Custom scrollbar for webkit browsers */
        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        .table {
            margin-bottom: 0;
            font-size: 0.95rem;
            min-width: 700px;
            width: 100%;
        }

        .table thead th {
            background: var(--gray-50);
            border: none;
            font-weight: 600;
            color: var(--gray-700);
            padding: 1rem 1.2rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 1.2rem;
            border-color: var(--gray-100);
            vertical-align: middle;
            white-space: nowrap;
        }

        /* Table column widths */
        .table th:nth-child(1), .table td:nth-child(1) { min-width: 120px; } /* Date column */
        .table th:nth-child(2), .table td:nth-child(2) { min-width: 100px; } /* Time column */
        .table th:nth-child(3), .table td:nth-child(3) { min-width: 150px; } /* Doctor column */
        .table th:nth-child(4), .table td:nth-child(4) { min-width: 200px; } /* Reason column */
        .table th:nth-child(5), .table td:nth-child(5) { min-width: 100px; } /* Status column */

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: var(--gray-50);
            transform: translateX(4px);
        }

        .table .badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <!-- Header Section -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="welcome-section">
                    <div class="welcome-text">
                        <h1>Welcome back, <?php echo htmlspecialchars($patient_name); ?>!</h1>
                        <p>Manage your dental health journey with ease</p>
                    </div>
                    <div class="header-actions">
                        <a href="logout.php" class="btn-logout">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Statistics Section -->
            <section class="fade-in">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $upcoming_appointments; ?></h3>
                                <p>Upcoming (Next 5 Days)</p>
                            </div>
                            <div class="stat-icon primary">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $completed_appointments; ?></h3>
                                <p>Completed Visits</p>
                            </div>
                            <div class="stat-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $total_appointments; ?></h3>
                                <p>Total Appointments</p>
                            </div>
                            <div class="stat-icon warning">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Quick Actions Section -->
            <section class="quick-actions fade-in">
                <h2 class="section-title">Quick Actions</h2>
                <p class="section-subtitle">Access your most used features quickly</p>
                
                <div class="actions-grid">
                    <a href="book_appointment.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon primary">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <h3 class="action-title">Book Appointment</h3>
                        </div>
                        <p class="action-description">Schedule your next dental visit with our experienced team</p>
                    </a>

                    <a href="my_appointments.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon success">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h3 class="action-title">My Appointments</h3>
                        </div>
                        <p class="action-description">View, reschedule, or cancel your upcoming appointments</p>
                    </a>

                    <a href="my_reviews.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon teal">
                                <i class="fas fa-star"></i>
                            </div>
                            <h3 class="action-title">My Reviews</h3>
                        </div>
                        <p class="action-description">View and manage your doctor reviews and ratings</p>
                    </a>

                    <a href="patient_history.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon info">
                                <i class="fas fa-file-medical"></i>
                            </div>
                            <h3 class="action-title">Medical Records</h3>
                        </div>
                        <p class="action-description">Access your complete dental treatment history and records</p>
                    </a>

                    <a href="billing.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon warning">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <h3 class="action-title">Billing & Payments</h3>
                        </div>
                        <p class="action-description">View invoices, payment history, and outstanding balances</p>
                    </a>

                    <a href="message.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon secondary">
                                <i class="fas fa-comments"></i>
                            </div>
                            <h3 class="action-title">Messages</h3>
                        </div>
                        <p class="action-description">Communicate directly with your healthcare providers</p>
                    </a>

                    <a href="my_profile.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon purple">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <h3 class="action-title">My Profile</h3>
                        </div>
                        <p class="action-description">Manage your personal information and view statistics</p>
                    </a>

                    <a href="../all_doctors.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon danger">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <h3 class="action-title">Find Doctors</h3>
                        </div>
                        <p class="action-description">Browse our team of experienced dental specialists</p>
                    </a>
                </div>
            </section>

            <!-- Upcoming Appointments Section -->
            <section class="appointment-section fade-in">
                <h2 class="section-title">Upcoming Appointments</h2>
                <p class="section-subtitle">Your scheduled visits (today and next 5 days)</p>
                
                <?php 
                // Get all upcoming appointments for table display
                $upcoming_appointments_list = [];
                $sql = "SELECT a.*, d.name as doctor_name FROM appointments a 
                        LEFT JOIN doctors d ON a.doctor_id = d.id 
                        WHERE (a.patient_id = ? OR a.patient_email = ?) AND a.appointment_date >= CURDATE() AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 5 DAY) AND a.status != 'cancelled'
                        ORDER BY a.appointment_date ASC, a.appointment_time ASC";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("is", $patient_id, $patient_email);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $upcoming_appointments_list[] = $row;
                }
                ?>
                
                <?php if (!empty($upcoming_appointments_list)): ?>
                <div class="appointment-card">
                    <div class="appointment-header">
                        <h3 class="appointment-title">
                            <i class="fas fa-clock"></i>
                            Upcoming Visits
                        </h3>
                        <span class="appointment-status">
                            <?php echo count($upcoming_appointments_list); ?> Appointments
                        </span>
                    </div>
                    <div class="appointment-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-calendar me-2"></i>Date</th>
                                        <th><i class="fas fa-clock me-2"></i>Time</th>
                                        <th><i class="fas fa-user-md me-2"></i>Doctor</th>
                                        <th><i class="fas fa-notes-medical me-2"></i>Reason</th>
                                        <th><i class="fas fa-info-circle me-2"></i>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_appointments_list as $appointment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></strong><br>
                                            <small class="text-muted"><?php echo date('l', strtotime($appointment['appointment_date'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($appointment['doctor_name']): ?>
                                                Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                            <?php else: ?>
                                                <em class="text-warning">To be assigned</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($appointment['message'] ?? '-'); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $appointment['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <h3 class="empty-title">No Upcoming Appointments</h3>
                    <p class="empty-description">No appointments scheduled for today and the next 5 days</p>
                    <a href="book_appointment.php" class="btn-primary">
                        <i class="fas fa-calendar-plus me-2"></i>
                        Book Your Next Appointment
                    </a>
                </div>
                <?php endif; ?>
            </section>

            <!-- Recent Visits Section -->
            <?php if (!empty($recent_appointments)): ?>
            <section class="appointment-section fade-in">
                <h2 class="section-title">Recent Visits</h2>
                <p class="section-subtitle">Your appointments from the past 5 days</p>
                
                <div class="appointment-card">
                    <div class="appointment-header">
                        <h3 class="appointment-title">
                            <i class="fas fa-history"></i>
                            Recent Visits
                        </h3>
                        <span class="appointment-status">
                            <?php echo count($recent_appointments); ?> Visits
                        </span>
                    </div>
                    <div class="appointment-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-calendar me-2"></i>Date</th>
                                        <th><i class="fas fa-clock me-2"></i>Time</th>
                                        <th><i class="fas fa-user-md me-2"></i>Doctor</th>
                                        <th><i class="fas fa-notes-medical me-2"></i>Reason</th>
                                        <th><i class="fas fa-info-circle me-2"></i>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_appointments as $appointment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></strong><br>
                                            <small class="text-muted"><?php echo date('l', strtotime($appointment['appointment_date'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($appointment['doctor_name']): ?>
                                                Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                            <?php else: ?>
                                                <em class="text-warning">Not assigned</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($appointment['message'] ?? '-'); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $appointment['status'] === 'completed' ? 'success' : 
                                                     ($appointment['status'] === 'confirmed' ? 'primary' : 
                                                      ($appointment['status'] === 'pending' ? 'warning' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </main>

    <!-- Profile Completion Modal (Cannot be dismissed) -->
    <?php if ($profile_incomplete): ?>
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="profileModalLabel">
                        <i class="fas fa-user-edit me-2"></i>
                        Complete Your Profile
                    </h5>
                    <!-- No close button to prevent dismissal -->
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Profile Completion Required</strong><br>
                        To ensure the best healthcare experience, please complete your profile information.
                    </div>
                    
                    <form id="profileForm">
                        <div class="mb-3">
                            <label for="gender" class="form-label">
                                <i class="fas fa-venus-mars me-2"></i>
                                Gender <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select your gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="date_of_birth" class="form-label">
                                <i class="fas fa-birthday-cake me-2"></i>
                                Date of Birth <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary" id="updateBtn">
                                <i class="fas fa-save me-2"></i>
                                Update Profile
                            </button>
                            <a href="logout.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Logout Instead
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Profile completion modal handling
        <?php if ($profile_incomplete): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const profileModal = new bootstrap.Modal(document.getElementById('profileModal'), {
                backdrop: 'static',
                keyboard: false
            });
            profileModal.show();
            
            // Handle form submission
            document.getElementById('profileForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const updateBtn = document.getElementById('updateBtn');
                const originalText = updateBtn.innerHTML;
                
                // Show loading state
                updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
                updateBtn.disabled = true;
                
                const formData = new FormData(this);
                
                fetch('update_profile_info.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-success alert-dismissible fade show mt-3';
                        alertDiv.innerHTML = `
                            <i class="fas fa-check-circle me-2"></i>
                            ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        document.querySelector('.modal-body').appendChild(alertDiv);
                        
                        // Close modal and reload page after a short delay
                        setTimeout(() => {
                            profileModal.hide();
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Show error message
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-danger alert-dismissible fade show mt-3';
                        alertDiv.innerHTML = `
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        document.querySelector('.modal-body').appendChild(alertDiv);
                        
                        // Restore button state
                        updateBtn.innerHTML = originalText;
                        updateBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    // Show error message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show mt-3';
                    alertDiv.innerHTML = `
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        An error occurred. Please try again.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('.modal-body').appendChild(alertDiv);
                    
                    // Restore button state
                    updateBtn.innerHTML = originalText;
                    updateBtn.disabled = false;
                });
            });
        });
        <?php endif; ?>
        
        // Add loading states for navigation
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('click', function(e) {
                const icon = this.querySelector('.action-icon i');
                const originalClass = icon.className;
                
                // Show loading state
                icon.className = 'fas fa-spinner fa-spin';
                
                // Restore original icon after navigation
                setTimeout(() => {
                    icon.className = originalClass;
                }, 1000);
            });
        });

        // Add fade-in animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationDelay = '0.2s';
                    entry.target.classList.add('fade-in');
                }
            });
        }, observerOptions);

        // Observe all sections
        document.querySelectorAll('section').forEach(section => {
            observer.observe(section);
        });

        // Smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';

        // Add hover effects for stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>