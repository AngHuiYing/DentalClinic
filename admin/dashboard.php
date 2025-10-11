<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Page-specific variables
$page_title = "Dashboard";

// Get statistics
$total_patients = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='patient'")->fetch_assoc()['count'];
$today_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetch_assoc()['count'];
$total_doctors = $conn->query("SELECT COUNT(*) as count FROM doctors")->fetch_assoc()['count'];

// Get monthly revenue
$current_month = date('Y-m');
$revenue_result = $conn->query("SELECT SUM(amount) as total FROM billing WHERE DATE_FORMAT(created_at, '%Y-%m') = '$current_month'");
$monthly_revenue = $revenue_result->fetch_assoc()['total'] ?? 0;
$formatted_revenue = 'RM ' . number_format($monthly_revenue, 2);

// Additional styles for dashboard
$additional_styles = "
<style>
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--white);
        border-radius: var(--border-radius-xl);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--stat-color, var(--primary-color));
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
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
        border-radius: var(--border-radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: var(--white);
        background: var(--stat-color, var(--primary-color));
    }

    .stat-value {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--gray-900);
        margin-bottom: 0.25rem;
        line-height: 1;
    }

    .stat-label {
        color: var(--gray-600);
        font-size: 14px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Dashboard Grid */
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .dashboard-card {
        background: var(--white);
        border-radius: var(--border-radius-xl);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        transition: var(--transition);
        text-decoration: none;
        color: inherit;
        position: relative;
        overflow: hidden;
    }

    .dashboard-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--card-color, var(--primary-color));
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    .dashboard-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--shadow-xl);
        text-decoration: none;
        color: inherit;
    }

    .dashboard-card:hover::before {
        transform: scaleX(1);
    }

    .card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 1rem;
    }

    .card-icon {
        width: 60px;
        height: 60px;
        border-radius: var(--border-radius-xl);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: var(--white);
        background: var(--card-color, var(--primary-color));
        transition: var(--transition);
    }

    .dashboard-card:hover .card-icon {
        transform: scale(1.1) rotate(5deg);
    }

    .card-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 0.5rem;
        line-height: 1.3;
    }

    .card-description {
        color: var(--gray-600);
        font-size: 0.9rem;
        line-height: 1.5;
    }

    /* Color variations */
    .stat-patients { --stat-color: #3b82f6; }
    .stat-appointments { --stat-color: #f59e0b; }
    .stat-doctors { --stat-color: #10b981; }
    .stat-revenue { --stat-color: #8b5cf6; }

    .card-users { --card-color: #3b82f6; }
    .card-doctors { --card-color: #10b981; }
    .card-appointments { --card-color: #f59e0b; }
    .card-records { --card-color: #ef4444; }
    .card-billing { --card-color: #8b5cf6; }
    .card-reports { --card-color: #06b6d4; }
    .card-services { --card-color: #84cc16; }
    .card-logs { --card-color: #6b7280; }
    .card-messages { --card-color: #f97316; }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .dashboard-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .stat-card,
        .dashboard-card {
            padding: 1rem;
        }

        .stat-value {
            font-size: 2rem;
        }

        .card-icon {
            width: 48px;
            height: 48px;
            font-size: 20px;
        }
    }
</style>
";

// Include navbar
include 'includes/navbar.php';
?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($admin_name); ?>! Here's what's happening at your clinic today.</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card stat-patients">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $total_patients; ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
            
            <div class="stat-card stat-appointments">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $today_appointments; ?></div>
                <div class="stat-label">Today's Appointments</div>
            </div>
            
            <div class="stat-card stat-doctors">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $total_doctors; ?></div>
                <div class="stat-label">Total Doctors</div>
            </div>
            
            <div class="stat-card stat-revenue">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $formatted_revenue; ?></div>
                <div class="stat-label">Monthly Revenue</div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <a href="manage_users.php" class="dashboard-card card-users">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="card-title">Manage Patients</div>
                        <div class="card-description">View, add, edit, and manage patient information and records</div>
                    </div>
                </div>
            </a>

            <a href="manage_doctors.php" class="dashboard-card card-doctors">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div>
                        <div class="card-title">Manage Doctors</div>
                        <div class="card-description">View and manage doctor profiles, schedules, and availability</div>
                    </div>
                </div>
            </a>

            <a href="manage_appointments.php" class="dashboard-card card-appointments">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <div class="card-title">Appointments</div>
                        <div class="card-description">Schedule and manage patient appointments and bookings</div>
                    </div>
                </div>
            </a>

            <a href="patient_records.php" class="dashboard-card card-records">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <div>
                        <div class="card-title">Medical Records</div>
                        <div class="card-description">Access and manage patient medical history and treatments</div>
                    </div>
                </div>
            </a>

            <a href="billing.php" class="dashboard-card card-billing">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div>
                        <div class="card-title">Billing</div>
                        <div class="card-description">Manage billing, payments, and financial records</div>
                    </div>
                </div>
            </a>

            <a href="reports.php" class="dashboard-card card-reports">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div>
                        <div class="card-title">Reports</div>
                        <div class="card-description">View detailed reports and analytics for clinic performance</div>
                    </div>
                </div>
            </a>

            <a href="manage_service.php" class="dashboard-card card-services">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div>
                        <div class="card-title">Services</div>
                        <div class="card-description">Manage clinic services, treatments, and pricing</div>
                    </div>
                </div>
            </a>

            <a href="system_logs.php" class="dashboard-card card-logs">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div>
                        <div class="card-title">System Logs</div>
                        <div class="card-description">Monitor system activity, errors, and security logs</div>
                    </div>
                </div>
            </a>

            <a href="messages.php" class="dashboard-card card-messages">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div>
                        <div class="card-title">Messages</div>
                        <div class="card-description">View and respond to patient messages and inquiries</div>
                    </div>
                </div>
            </a>
        </div>

<?php include 'includes/footer.php'; ?>