<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['user_name'] ?? 'Doctor';

// Get doctor ID from doctors table
$sql = "SELECT id FROM doctors WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    die("Doctor record not found!");
}

$doctor_id = $doctor['id'];

// Get statistics
// Total patients
$total_patients = 0;
$sql = "SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE doctor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $total_patients = $row['count'];
}

// Today's appointments
$today_appointments = 0;
$sql = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE() AND status != 'cancelled'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $today_appointments = $row['count'];
}

// Upcoming appointments (next 7 days)
$upcoming_appointments = 0;
$sql = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status != 'cancelled'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $upcoming_appointments = $row['count'];
}

// Completed appointments this month
$completed_appointments = 0;
$sql = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'completed' AND MONTH(appointment_date) = MONTH(CURDATE()) AND YEAR(appointment_date) = YEAR(CURDATE())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $completed_appointments = $row['count'];
}

// Get today's appointments with details
$todays_schedule = [];
$sql = "SELECT a.*, 
               COALESCE(u.name, a.patient_name) as patient_display_name,
               COALESCE(u.email, a.patient_email) as patient_display_email
        FROM appointments a
        LEFT JOIN users u ON a.patient_id = u.id
        WHERE a.doctor_id = ? 
          AND a.appointment_date = CURDATE()
          AND a.status != 'cancelled'
        ORDER BY a.appointment_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $todays_schedule[] = $row;
}

// Get recent appointments
$recent_appointments = [];
$sql = "SELECT a.*, 
               COALESCE(u.name, a.patient_name) as patient_display_name,
               COALESCE(u.email, a.patient_email) as patient_display_email
        FROM appointments a
        LEFT JOIN users u ON a.patient_id = u.id
        WHERE a.doctor_id = ? 
          AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
          AND a.status != 'cancelled'
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_appointments[] = $row;
}

// Get review statistics
$review_stats = [];
$sql = "SELECT 
    COUNT(*) as total_reviews,
    AVG(rating) as avg_rating
    FROM doctor_reviews 
    WHERE doctor_id = ? AND is_approved = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $review_stats = $row;
}
$avg_rating = $review_stats['avg_rating'] ? round($review_stats['avg_rating'], 1) : 0;
$total_reviews = $review_stats['total_reviews'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Dental Care Clinic</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Medical Professional Color Palette */
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary: #059669;
            --accent: #7c3aed;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            
            /* Sophisticated Grays */
            --white: #ffffff;
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
            
            /* Professional Shadows */
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px rgba(0, 0, 0, 0.25);
            
            /* Modern Border Radius */
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-2xl: 2rem;
            --radius-full: 9999px;
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

        /* Navigation Bar */
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            box-shadow: var(--shadow-lg);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .navbar-brand i {
            font-size: 2rem;
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 0.75rem 1rem !important;
            border-radius: var(--radius-lg);
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }

        .dropdown-menu {
            border: none;
            box-shadow: var(--shadow-xl);
            border-radius: var(--radius-lg);
            padding: 0.5rem;
        }

        .dropdown-item {
            border-radius: var(--radius);
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: var(--gray-100);
            transform: translateX(4px);
        }

        /* Main Content */
        .main-content {
            padding: 2rem 0;
        }

        /* Welcome Section */
        .welcome-section {
            background: white;
            border-radius: var(--radius-2xl);
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .welcome-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome-text h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .welcome-text p {
            color: var(--gray-600);
            font-size: 1.1rem;
            margin: 0;
        }

        .welcome-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn-logout {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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

        .stat-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-info h3 {
            font-size: 3rem;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
            line-height: 1;
        }

        .stat-info p {
            color: var(--gray-600);
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
        }

        .stat-info small {
            color: var(--gray-500);
            font-size: 0.85rem;
            font-weight: 500;
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
        .stat-icon.info { background: linear-gradient(135deg, var(--info), #0891b2); }

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
        .action-icon.info { background: linear-gradient(135deg, var(--info), #0891b2); }
        .action-icon.warning { background: linear-gradient(135deg, var(--warning), #d97706); }
        .action-icon.secondary { background: linear-gradient(135deg, var(--secondary), #047857); }
        .action-icon.accent { background: linear-gradient(135deg, var(--accent), #6d28d9); }

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

        /* Schedule Section */
        .schedule-section {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin-bottom: 3rem;
        }

        .schedule-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: between;
        }

        .schedule-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .schedule-body {
            padding: 0;
        }

        .appointment-item {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }

        .appointment-item:hover {
            background: var(--gray-50);
        }

        .appointment-item:last-child {
            border-bottom: none;
        }

        .appointment-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .appointment-time {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .appointment-details h6 {
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
            font-size: 1rem;
        }

        .appointment-details p {
            color: var(--gray-600);
            margin: 0;
            font-size: 0.9rem;
        }

        .appointment-status {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-confirmed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .status-completed {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-500);
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem 0;
            }
            
            .welcome-section {
                padding: 2rem 1.5rem;
            }
            
            .welcome-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .welcome-text h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .action-card {
                padding: 1.5rem;
            }
            
            .schedule-header {
                padding: 1.5rem;
            }
            
            .appointment-item {
                padding: 1rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .appointment-info {
                width: 100%;
                justify-content: space-between;
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include '../includes/navbar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Welcome Section -->
            <section class="welcome-section fade-in">
                <div class="welcome-content">
                    <div class="welcome-text">
                        <h1>Welcome back, Dr. <?php echo htmlspecialchars($doctor_name); ?>!</h1>
                        <p>Manage your practice and provide excellent patient care</p>
                    </div>
                    <div class="welcome-actions">
                        <a href="logout.php" class="btn-logout">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </section>

            <!-- Statistics Section -->
            <section class="fade-in">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $today_appointments; ?></h3>
                                <p>Today's Appointments</p>
                                <small><?php echo date('F j, Y'); ?></small>
                            </div>
                            <div class="stat-icon primary">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $upcoming_appointments; ?></h3>
                                <p>This Week</p>
                                <small>Next 7 days</small>
                            </div>
                            <div class="stat-icon success">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $total_patients; ?></h3>
                                <p>Total Patients</p>
                                <small>All time</small>
                            </div>
                            <div class="stat-icon info">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $completed_appointments; ?></h3>
                                <p>This Month</p>
                                <small>Completed visits</small>
                            </div>
                            <div class="stat-icon warning">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $avg_rating; ?>/5</h3>
                                <p>Average Rating</p>
                                <small><?php echo $total_reviews; ?> review<?php echo $total_reviews != 1 ? 's' : ''; ?></small>
                            </div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #ffd700, #ffed4e);">
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Quick Actions Section -->
            <section class="quick-actions fade-in">
                <h2 class="section-title">Quick Actions</h2>
                <p class="section-subtitle">Access your most used professional tools</p>
                
                <div class="actions-grid">
                    <a href="manage_appointments.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon primary">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h3 class="action-title">Manage Appointments</h3>
                        </div>
                        <p class="action-description">View, update, and organize your appointment schedule</p>
                    </a>

                    <a href="patient_records.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon success">
                                <i class="bi bi-file-medical-fill"></i>
                            </div>
                            <h3 class="action-title">Patient Records</h3>
                        </div>
                        <p class="action-description">Access and update patient medical records and history</p>
                    </a>

                    <a href="patient_history.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon info">
                                <i class="fas fa-file-medical"></i>
                            </div>
                            <h3 class="action-title">Add Medical Records</h3>
                        </div>
                        <p class="action-description">Access and manage patient medical records</p>
                    </a>

                    <!-- <a href="messages.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon warning">
                                <i class="fas fa-comments"></i>
                            </div>
                            <h3 class="action-title">Messages</h3>
                        </div>
                        <p class="action-description">Communicate with patients and healthcare team</p>
                    </a> -->

                    <a href="doctor_profile.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon secondary">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <h3 class="action-title">My Profile</h3>
                        </div>
                        <p class="action-description">Update your professional information and credentials</p>
                    </a>

                    <a href="my_reviews.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon" style="background: linear-gradient(135deg, #ffd700, #ffed4e);">
                                <i class="fas fa-star"></i>
                            </div>
                            <h3 class="action-title">My Reviews</h3>
                        </div>
                        <p class="action-description">View patient feedback and ratings for your care</p>
                    </a>

                    <!-- <a href="doctor_setunavailable.php" class="action-card">
                        <div class="action-header">
                            <div class="action-icon accent">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="action-title">Schedule Management</h3>
                        </div>
                        <p class="action-description">Set availability and manage working hours</p>
                    </a> -->
                </div>
            </section>

            <!-- Today's Schedule Section -->
            <section class="fade-in">
                <h2 class="section-title">Today's Schedule</h2>
                <p class="section-subtitle">Your appointments for <?php echo date('F j, Y'); ?></p>
                
                <div class="schedule-section">
                    <div class="schedule-header">
                        <h3 class="schedule-title">
                            <i class="fas fa-calendar-day"></i>
                            Today's Appointments
                        </h3>
                    </div>
                    <div class="schedule-body">
                        <?php if (empty($todays_schedule)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <h4 class="empty-title">No Appointments Today</h4>
                            <p class="empty-description">You have a free day! Enjoy some well-deserved rest.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($todays_schedule as $appointment): ?>
                        <div class="appointment-item">
                            <div class="appointment-info">
                                <div class="appointment-time">
                                    <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                </div>
                                <div class="appointment-details">
                                    <h6><?php echo htmlspecialchars($appointment['patient_display_name']); ?></h6>
                                    <p><?php echo htmlspecialchars($appointment['patient_display_email']); ?></p>
                                    <?php if (!empty($appointment['message'])): ?>
                                    <p><i class="fas fa-comment me-1"></i><?php echo htmlspecialchars($appointment['message']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="appointment-status status-<?php echo strtolower($appointment['status']); ?>">
                                <?php echo ucfirst($appointment['status']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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

        // Add hover effects for stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Real-time clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            const clockElement = document.getElementById('currentTime');
            if (clockElement) {
                clockElement.textContent = timeString;
            }
        }

        // Update clock every second
        setInterval(updateClock, 1000);
        updateClock(); // Initial call
    </script>
</body>
</html>