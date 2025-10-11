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

// Get upcoming appointments count
$upcoming_appointments = 0;
$sql = "SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND appointment_date >= CURDATE() AND status != 'cancelled'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $upcoming_appointments = $row['count'];
}

// Get total appointments count
$total_appointments = 0;
$sql = "SELECT COUNT(*) as count FROM appointments WHERE patient_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $total_appointments = $row['count'];
}

// Get recent appointment
$recent_appointment = null;
$sql = "SELECT a.*, d.name as doctor_name FROM appointments a 
        LEFT JOIN users d ON a.doctor_id = d.id 
        WHERE a.patient_id = ? 
        ORDER BY a.appointment_date DESC, a.appointment_time DESC 
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $recent_appointment = $result->fetch_assoc();
}

// Get next appointment
$next_appointment = null;
$sql = "SELECT a.*, d.name as doctor_name FROM appointments a 
        LEFT JOIN users d ON a.doctor_id = d.id 
        WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status != 'cancelled'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC 
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $next_appointment = $result->fetch_assoc();
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Medical Theme Colors */
            --primary-color: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #7dd3fc;
            --secondary-color: #06d6a0;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            
            /* Neutrals */
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
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            
            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-2xl: 2rem;
            --radius-full: 9999px;
            
            /* Spacing */
            --space-xs: 0.5rem;
            --space-sm: 1rem;
            --space-md: 1.5rem;
            --space-lg: 2rem;
            --space-xl: 3rem;
            --space-2xl: 4rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--gray-800);
            line-height: 1.6;
        }

        /* Header Section */
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: var(--space-xl) 0;
            margin-bottom: var(--space-xl);
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 30%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(15deg);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0%, 100% { transform: rotate(15deg) translateX(-100%); }
            50% { transform: rotate(15deg) translateX(100%); }
        }

        .welcome-section {
            position: relative;
            z-index: 2;
        }

        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: var(--space-sm);
        }

        .welcome-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 400;
        }

        /* Quick Actions */
        .quick-actions {
            margin-bottom: var(--space-xl);
        }

        .quick-action-card {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: var(--space-lg);
            box-shadow: var(--shadow-md);
            border: none;
            transition: all 0.3s ease;
            height: 100%;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .quick-action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            text-decoration: none;
            color: inherit;
        }

        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: var(--space-md);
        }

        .icon-primary { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); }
        .icon-success { background: linear-gradient(135deg, var(--success-color), #059669); }
        .icon-warning { background: linear-gradient(135deg, var(--warning-color), #d97706); }
        .icon-info { background: linear-gradient(135deg, var(--info-color), #2563eb); }
        .icon-secondary { background: linear-gradient(135deg, var(--secondary-color), #05a082); }
        .icon-danger { background: linear-gradient(135deg, var(--danger-color), #dc2626); }

        .action-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: var(--space-xs);
            color: var(--gray-800);
        }

        .action-description {
            color: var(--gray-600);
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        /* Statistics Cards */
        .stat-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            box-shadow: var(--shadow-md);
            border: none;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: var(--space-xs);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.95rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Appointment Cards */
        .appointment-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            box-shadow: var(--shadow-md);
            border: none;
            margin-bottom: var(--space-lg);
        }

        .appointment-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--space-md);
        }

        .appointment-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }

        .appointment-status {
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-confirmed {
            background: #d1fae5;
            color: var(--success-color);
        }

        .status-pending {
            background: #fef3c7;
            color: var(--warning-color);
        }

        .status-cancelled {
            background: #fee2e2;
            color: var(--danger-color);
        }

        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-md);
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .detail-icon {
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .detail-text {
            color: var(--gray-700);
            font-size: 0.95rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: var(--space-2xl);
            color: var(--gray-500);
        }

        .empty-icon {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: var(--space-md);
        }

        .empty-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: var(--space-sm);
        }

        .empty-description {
            font-size: 0.95rem;
            margin-bottom: var(--space-lg);
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: between;
            margin-bottom: var(--space-lg);
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
        }

        .section-subtitle {
            color: var(--gray-600);
            font-size: 0.95rem;
            margin-top: var(--space-xs);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .welcome-title {
                font-size: 2rem;
            }
            
            .quick-action-card {
                padding: var(--space-md);
                margin-bottom: var(--space-md);
            }
            
            .appointment-details {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                padding: var(--space-lg) 0;
            }
        }

        /* Animation */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Navigation Styling */
        .navbar {
            background: var(--white);
            box-shadow: var(--shadow-sm);
            padding: var(--space-md) 0;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color);
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-tooth me-2"></i>
                Dental Care Clinic
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user-circle me-1"></i>
                    Welcome, <?php echo htmlspecialchars($patient_name); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-primary">
                    <i class="fas fa-sign-out-alt me-1"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="welcome-section">
                <h1 class="welcome-title">
                    <i class="fas fa-tachometer-alt me-3"></i>
                    Patient Dashboard
                </h1>
                <p class="welcome-subtitle">
                    Welcome back, <?php echo htmlspecialchars($patient_name); ?>! 
                    Manage your appointments and health records.
                </p>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Row -->
        <div class="row mb-4 fade-in">
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $upcoming_appointments; ?></div>
                    <div class="stat-label">Upcoming Appointments</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_appointments; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Support Available</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions fade-in">
            <div class="section-header mb-4">
                <div>
                    <h2 class="section-title">Quick Actions</h2>
                    <p class="section-subtitle">Access your most used features</p>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="book_appointment.php" class="quick-action-card">
                        <div class="action-icon icon-primary">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <h3 class="action-title">Book Appointment</h3>
                        <p class="action-description">Schedule a new appointment with our dental professionals</p>
                    </a>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="my_appointments.php" class="quick-action-card">
                        <div class="action-icon icon-success">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="action-title">My Appointments</h3>
                        <p class="action-description">View and manage your current and upcoming appointments</p>
                    </a>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="patient_history.php" class="quick-action-card">
                        <div class="action-icon icon-info">
                            <i class="fas fa-file-medical"></i>
                        </div>
                        <h3 class="action-title">Medical History</h3>
                        <p class="action-description">Access your complete dental treatment records</p>
                    </a>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="billing.php" class="quick-action-card">
                        <div class="action-icon icon-warning">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <h3 class="action-title">Billing & Payments</h3>
                        <p class="action-description">View bills, payment history and outstanding balances</p>
                    </a>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="message.php" class="quick-action-card">
                        <div class="action-icon icon-secondary">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3 class="action-title">Messages</h3>
                        <p class="action-description">Communicate with your healthcare providers</p>
                    </a>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="../find_doctors.php" class="quick-action-card">
                        <div class="action-icon icon-danger">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <h3 class="action-title">Find Doctors</h3>
                        <p class="action-description">Browse our team of experienced dental specialists</p>
                    </a>
                </div>
            </div>
        </div>

        <!-- Next Appointment -->
        <?php if ($next_appointment): ?>
        <div class="row fade-in">
            <div class="col-12">
                <div class="section-header mb-4">
                    <div>
                        <h2 class="section-title">Next Appointment</h2>
                        <p class="section-subtitle">Your upcoming scheduled visit</p>
                    </div>
                </div>

                <div class="appointment-card">
                    <div class="appointment-header">
                        <h3 class="appointment-title">
                            <i class="fas fa-clock me-2"></i>
                            Upcoming Visit
                        </h3>
                        <span class="appointment-status status-<?php echo strtolower($next_appointment['status']); ?>">
                            <?php echo ucfirst($next_appointment['status']); ?>
                        </span>
                    </div>
                    <div class="appointment-details">
                        <div class="detail-item">
                            <i class="fas fa-calendar detail-icon"></i>
                            <span class="detail-text">
                                <?php echo date('l, F j, Y', strtotime($next_appointment['appointment_date'])); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-clock detail-icon"></i>
                            <span class="detail-text">
                                <?php echo date('g:i A', strtotime($next_appointment['appointment_time'])); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-user-md detail-icon"></i>
                            <span class="detail-text">
                                Dr. <?php echo htmlspecialchars($next_appointment['doctor_name'] ?? 'TBD'); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-notes-medical detail-icon"></i>
                            <span class="detail-text">
                                <?php echo htmlspecialchars($next_appointment['reason'] ?? 'General Consultation'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="row fade-in">
            <div class="col-12">
                <div class="section-header mb-4">
                    <div>
                        <h2 class="section-title">Next Appointment</h2>
                        <p class="section-subtitle">Schedule your next visit</p>
                    </div>
                </div>

                <div class="appointment-card">
                    <div class="empty-state">
                        <i class="fas fa-calendar-plus empty-icon"></i>
                        <h3 class="empty-title">No Upcoming Appointments</h3>
                        <p class="empty-description">
                            You don't have any scheduled appointments. Book your next dental visit today!
                        </p>
                        <a href="book_appointment.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus me-2"></i>
                            Book Appointment
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <?php if ($recent_appointment): ?>
        <div class="row fade-in mt-4">
            <div class="col-12">
                <div class="section-header mb-4">
                    <div>
                        <h2 class="section-title">Recent Activity</h2>
                        <p class="section-subtitle">Your latest appointment</p>
                    </div>
                </div>

                <div class="appointment-card">
                    <div class="appointment-header">
                        <h3 class="appointment-title">
                            <i class="fas fa-history me-2"></i>
                            Latest Visit
                        </h3>
                        <span class="appointment-status status-<?php echo strtolower($recent_appointment['status']); ?>">
                            <?php echo ucfirst($recent_appointment['status']); ?>
                        </span>
                    </div>
                    <div class="appointment-details">
                        <div class="detail-item">
                            <i class="fas fa-calendar detail-icon"></i>
                            <span class="detail-text">
                                <?php echo date('l, F j, Y', strtotime($recent_appointment['appointment_date'])); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-clock detail-icon"></i>
                            <span class="detail-text">
                                <?php echo date('g:i A', strtotime($recent_appointment['appointment_time'])); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-user-md detail-icon"></i>
                            <span class="detail-text">
                                Dr. <?php echo htmlspecialchars($recent_appointment['doctor_name'] ?? 'TBD'); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-notes-medical detail-icon"></i>
                            <span class="detail-text">
                                <?php echo htmlspecialchars($recent_appointment['reason'] ?? 'General Consultation'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer Spacing -->
    <div style="height: 4rem;"></div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Add loading states for navigation
        document.querySelectorAll('.quick-action-card').forEach(card => {
            card.addEventListener('click', function() {
                const icon = this.querySelector('.action-icon i');
                const originalClass = icon.className;
                
                icon.className = 'fas fa-spinner fa-spin';
                
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
                    entry.target.style.animation = 'fadeIn 0.6s ease-out forwards';
                }
            });
        }, observerOptions);

        // Observe all elements with fade-in class
        document.querySelectorAll('.fade-in').forEach(el => {
            observer.observe(el);
        });

        // Real-time clock for better UX
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            const dateString = now.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            // Update any clock elements if they exist
            const clockElements = document.querySelectorAll('.current-time');
            clockElements.forEach(el => {
                el.textContent = timeString;
            });
        }

        // Update clock every second
        setInterval(updateClock, 1000);
        updateClock(); // Initial call

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
                    <a href="patient_history.php" class="btn btn-primary">View Historys</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>My Billing Record</h5>
                </div>
                <div class="card-body">
                    <p>View your billing records.</p>
                    <a href="billing.php" class="btn btn-primary">View Records</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Send Message</h5>
                </div>
                <div class="card-body">
                    <p>Send a message to admin if you have any question.</p>
                    <a href="message.php" class="btn btn-primary">Send Message</a>
                </div>
            </div>
        </div>
        <!-- <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Chat with Doctor</h5>
                </div>
                <div class="card-body">
                    <p>Chat with doctor if have any question.</p>
                    <a href="chat_system.php" class="btn btn-success">Chat</a>
                </div>
            </div>
        </div> -->
    </div>
</div>
</body>
</html> 