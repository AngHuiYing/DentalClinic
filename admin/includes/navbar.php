<?php
// Get admin details safely
$admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Panel'; ?> - Dental Clinic</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #3730a3;
            --secondary-color: #7c3aed;
            --accent-color: #f59e0b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f97316;
            --info-color: #06b6d4;
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
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --border-radius-lg: 12px;
            --border-radius-xl: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.6;
        }

        /* Navigation Bar */
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
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
            color: var(--white);
            text-decoration: none;
            font-weight: 700;
            transition: var(--transition);
        }

        .navbar-brand:hover {
            transform: translateY(-1px);
            color: var(--white);
            text-decoration: none;
        }

        .brand-icon {
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
            width: 44px;
            height: 44px;
            border-radius: var(--border-radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .brand-main {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.25px;
        }

        .brand-sub {
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        /* Mega Menu */
        .mega-menu {
            display: flex;
            align-items: center;
            gap: 2rem;
            list-style: none;
            margin: 0 3rem 0 2rem;
        }

        .mega-item {
            position: relative;
        }

        .mega-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            border-radius: 10px;
            transition: var(--transition);
            white-space: nowrap;
            position: relative;
        }

        .mega-link:hover,
        .mega-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: var(--white);
            text-decoration: none;
            transform: translateY(-1px);
            backdrop-filter: blur(10px);
        }

        .mega-link i {
            font-size: 16px;
        }

        /* Dropdown Arrow */
        .mega-link.has-dropdown::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            margin-left: 8px;
            font-size: 12px;
            transition: transform 0.2s ease;
        }

        .mega-item:hover .mega-link.has-dropdown::after {
            transform: rotate(180deg);
        }

        /* Mega Dropdown */
        .mega-dropdown {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--white);
            border-radius: var(--border-radius-xl);
            box-shadow: var(--shadow-xl);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            margin-top: 8px;
            border: 1px solid var(--gray-200);
            min-width: 800px;
        }

        .mega-item:hover .mega-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }

        /* Single column dropdown */
        .mega-dropdown.single-column {
            min-width: 250px;
        }

        .mega-dropdown-content {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            padding: 2rem;
        }

        .mega-dropdown.single-column .mega-dropdown-content {
            grid-template-columns: 1fr;
            padding: 1rem;
        }

        .mega-section {
            display: flex;
            flex-direction: column;
        }

        .mega-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: var(--gray-800);
            font-size: 14px;
            padding: 0 12px 12px;
            border-bottom: 2px solid var(--gray-100);
            margin-bottom: 8px;
        }

        .mega-section-title i {
            color: var(--primary-color);
            font-size: 16px;
        }

        .mega-section-links {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .mega-section-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            text-decoration: none;
            color: var(--gray-600);
            font-size: 13px;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: 400;
        }

        .mega-section-link:hover {
            background: var(--gray-50);
            color: var(--primary-color);
            text-decoration: none;
            transform: translateX(4px);
        }

        .mega-section-link i {
            font-size: 14px;
            width: 16px;
            text-align: center;
            color: var(--gray-400);
        }

        .mega-section-link:hover i {
            color: var(--primary-color);
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
            background: var(--white);
            color: var(--primary-color);
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
            color: var(--white);
            font-weight: 600;
            font-size: 13px;
        }

        .user-role {
            color: rgba(255, 255, 255, 0.8);
            font-size: 11px;
        }

        .logout-btn {
            background: var(--danger-color);
            color: var(--white);
            border: none;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
            margin-left: 8px;
        }

        .logout-btn:hover {
            background: #dc2626;
            color: var(--white);
            text-decoration: none;
            transform: translateY(-1px);
        }

        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .page-header {
            margin-bottom: 2rem;
            text-align: center;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .page-subtitle {
            color: var(--gray-600);
            font-size: 1.1rem;
            font-weight: 400;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .mega-menu {
                gap: 1rem;
                margin: 0 1rem;
            }

            .mega-dropdown {
                min-width: 600px;
            }

            .mega-dropdown-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .mega-menu {
                display: none;
            }

            .navbar-container {
                justify-content: space-between;
            }

            .mobile-menu-toggle {
                display: block;
                background: none;
                border: none;
                color: var(--white);
                font-size: 1.5rem;
                cursor: pointer;
                padding: 8px;
                border-radius: var(--border-radius);
                transition: var(--transition);
            }

            .mobile-menu-toggle:hover {
                background: rgba(255, 255, 255, 0.15);
            }
        }

        @media (max-width: 768px) {
            .navbar-container {
                padding: 0 1rem;
                height: 60px;
            }

            .brand-text {
                display: flex !important;
                flex-direction: column;
                margin-left: 8px;
            }
            .brand-main {
                font-size: 16px;
            }
            .brand-sub {
                font-size: 10px;
            }

            .user-info {
                display: none;
            }

            .user-profile {
                padding: 6px 12px;
                gap: 8px;
            }

            .main-content {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .page-subtitle {
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .brand-icon {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }

            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }

            .logout-btn {
                padding: 6px 12px;
                font-size: 11px;
            }
        }

        /* Mobile Menu (Hidden by default) */
        .mobile-menu-toggle {
            display: none;
        }
        @media (max-width: 992px) {
            .mobile-menu-toggle {
                display: block;
            }
        }

        .mobile-menu {
            display: none;
            position: fixed;
            top: 70px;
            left: 0;
            right: 0;
            background: var(--white);
            box-shadow: var(--shadow-lg);
            z-index: 999;
            max-height: calc(100vh - 70px);
            overflow-y: auto;
            border-radius: 0 0 18px 18px;
            padding-bottom: 1rem;
        }

        .mobile-menu.active {
            display: block;
        }
            /* Mobile Mega Menu Overlay */
            #mobileMegaMenuOverlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(79,70,229,0.12);
                z-index: 3000;
                overflow-y: auto;
                padding: 0 0.5rem;
            }
            .mobile-mega-menu-content {
                background: var(--white);
                border-radius: 18px;
                max-width: 420px;
                width: 100%;
                margin: 64px auto 24px auto;
                box-shadow: 0 8px 32px rgba(79,70,229,0.18);
                padding: 1.2rem 1rem 2rem 1rem;
                position: relative;
                min-height: 60vh;
                box-sizing: border-box;
                overflow: visible;
                z-index: 4000;
            }
            .mobile-mega-menu-close {
                position: absolute;
                top: 12px;
                right: 12px;
                background: var(--danger-color);
                color: var(--white);
                border: none;
                border-radius: 50%;
                width: 36px;
                height: 36px;
                font-size: 1.2rem;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 8px rgba(239,68,68,0.18);
                cursor: pointer;
                z-index: 10;
            }
            .mega-menu-mobile {
                display: block;
                margin: 0;
                padding: 0;
            }
            .mega-menu-mobile .mega-item {
                margin-bottom: 1.2rem;
            }
            .mega-menu-mobile .mega-dropdown-content {
                grid-template-columns: 1fr !important;
                padding: 0.5rem !important;
            }
            .mega-menu-mobile .mega-section {
                margin-bottom: 1.2rem;
                background: var(--gray-50);
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(79,70,229,0.04);
                padding: 0.5rem 0.5rem 0.5rem 0.5rem;
            }
            .mega-menu-mobile .mega-section-title {
                font-size: 1.08rem;
                font-weight: 700;
                color: var(--primary-color);
                background: linear-gradient(90deg, var(--gray-100) 60%, var(--gray-50) 100%);
                border-radius: 10px;
                box-shadow: 0 2px 8px rgba(79,70,229,0.07);
                padding: 0.5rem 0.75rem;
                margin-bottom: 0.5rem;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .mega-menu-mobile .mega-section-title i {
                font-size: 18px;
                color: var(--primary-color);
            }
            .mega-menu-mobile .mega-section-link {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 0.7rem 0.75rem;
                color: var(--gray-700);
                text-decoration: none;
                font-weight: 500;
                border-radius: 8px;
                transition: var(--transition);
            }
            .mega-menu-mobile .mega-section-link:hover {
                background: linear-gradient(90deg, var(--primary-color) 0%, var(--secondary-color) 100%);
                color: var(--white);
            }
            .mega-menu-mobile .mega-section-link i {
                font-size: 16px;
                width: 20px;
                color: var(--primary-color);
                transition: color 0.2s;
            }
            .mega-menu-mobile .mega-section-link:hover i {
                color: var(--white);
            }

        .mobile-menu-item {
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
            border-radius: 12px;
            margin: 0.5rem 0.75rem;
            box-shadow: 0 2px 8px rgba(79,70,229,0.04);
            transition: box-shadow 0.2s;
        }
        .mobile-menu-item:last-child {
            border-bottom: none;
        }

        .mobile-menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1rem 1.5rem;
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 500;
            border-radius: 10px;
            transition: var(--transition);
        }
        .mobile-menu-link:hover {
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: var(--white);
            text-decoration: none;
        }
        .mobile-menu-link i {
            font-size: 18px;
            width: 24px;
            color: var(--primary-color);
            transition: color 0.2s;
        }
        .mobile-menu-link:hover i {
            color: var(--white);
        }
        .mobile-menu-group {
            margin: 1.2rem 0 0.5rem 0;
        }
        .mobile-menu-group-title {
            font-weight: 700;
            font-size: 1.08rem;
            color: var(--primary-color);
            background: linear-gradient(90deg, var(--gray-100) 60%, var(--gray-50) 100%);
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(79,70,229,0.07);
            padding: 0.85rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            user-select: none;
            margin: 0 0.75rem;
        }
        .mobile-menu-group-title i {
            font-size: 20px;
            color: var(--primary-color);
        }
        .mobile-menu-arrow {
            margin-left: auto;
            font-size: 1.1rem;
            color: var(--gray-400);
        }
        .mobile-menu-group-list {
            margin-top: 0.2rem;
        }
    </style>
    <?php if (isset($additional_styles)) echo $additional_styles; ?>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Mobile Menu Toggle (left of brand icon on mobile) -->
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
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

            <!-- Mega Menu -->
            <ul class="mega-menu">
                <!-- Dashboard -->
                <li class="mega-item">
                    <a href="dashboard.php" class="mega-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>

                <!-- Patients Menu -->
                <li class="mega-item">
                    <a href="#" class="mega-link has-dropdown">
                        <i class="fas fa-users"></i>
                        Patients
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
                                        <i class="fas fa-users-cog"></i>
                                        Manage Patients
                                    </a>
                                    <a href="add_user.php" class="mega-section-link">
                                        <i class="fas fa-user-plus"></i>
                                        Add New Patient
                                    </a>
                                    <a href="patient_records.php" class="mega-section-link">
                                        <i class="fas fa-file-medical"></i>
                                        Medical Records
                                    </a>
                                </div>
                            </div>
                            <div class="mega-section">
                                <div class="mega-section-title">
                                    <i class="fas fa-history"></i>
                                    Patient History
                                </div>
                                <div class="mega-section-links">
                                    <a href="patient_history.php" class="mega-section-link">
                                        <i class="fas fa-clipboard-list"></i>
                                        Medical History
                                    </a>
                                    <a href="billing.php#billing-records" class="mega-section-link">
                                        <i class="fas fa-receipt"></i>
                                        Billing History
                                    </a>
                                </div>
                            </div>
                            <div class="mega-section">
                                <div class="mega-section-title">
                                    <i class="fas fa-comments"></i>
                                    Communication
                                </div>
                                <div class="mega-section-links">
                                    <a href="messages.php" class="mega-section-link">
                                        <i class="fas fa-envelope"></i>
                                        Patient Messages
                                    </a>
                                    <a href="manage_reviews.php" class="mega-section-link">
                                        <i class="fas fa-star"></i>
                                        Patient Reviews
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>

                <!-- Doctors Menu -->
                <li class="mega-item">
                    <a href="#" class="mega-link has-dropdown">
                        <i class="fas fa-user-md"></i>
                        Doctors
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
                                        <i class="fas fa-user-cog"></i>
                                        Manage Doctors
                                    </a>
                                    <a href="add_doctor_profile.php" class="mega-section-link">
                                        <i class="fas fa-user-plus"></i>
                                        Add Doctor
                                    </a>
                                    <a href="admin_set_unavailable.php" class="mega-section-link">
                                        <i class="fas fa-calendar-times"></i>
                                        Set Availability
                                    </a>
                                </div>
                            </div>
                            <div class="mega-section">
                                <div class="mega-section-title">
                                    <i class="fas fa-chart-bar"></i>
                                    Performance
                                </div>
                                <div class="mega-section-links">
                                    <a href="reports.php?type=doctor" class="mega-section-link">
                                        <i class="fas fa-file-medical"></i>
                                        Doctor Reports
                                    </a>
                                    <a href="manage_reviews.php?filter=doctor" class="mega-section-link">
                                        <i class="fas fa-star"></i>
                                        Doctor Reviews
                                    </a>
                                </div>
                            </div>
                            <div class="mega-section">
                                <div class="mega-section-title">
                                    <i class="fas fa-calendar-check"></i>
                                    Scheduling
                                </div>
                                <div class="mega-section-links">
                                    <a href="manage_appointments.php?view=doctor" class="mega-section-link">
                                        <i class="fas fa-calendar-alt"></i>
                                        Doctor Schedules
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>

                <!-- Appointments Menu -->
                <li class="mega-item">
                    <a href="#" class="mega-link has-dropdown">
                        <i class="fas fa-calendar-check"></i>
                        Appointments
                    </a>
                    <div class="mega-dropdown">
                        <div class="mega-dropdown-content">
                            <div class="mega-section">
                                <div class="mega-section-title">
                                    <i class="fas fa-calendar-plus"></i>
                                    Appointment Management
                                </div>
                                <div class="mega-section-links">
                                    <a href="manage_appointments.php" class="mega-section-link">
                                        <i class="fas fa-list"></i>
                                        All Appointments
                                    </a>
                                    <a href="add_appointment.php" class="mega-section-link">
                                        <i class="fas fa-plus"></i>
                                        Schedule Appointment
                                    </a>
                                </div>
                            </div>
                            <div class="mega-section">
                                <div class="mega-section-title">
                                    <i class="fas fa-calendar-day"></i>
                                    Today's Schedule
                                </div>
                                <div class="mega-section-links">
                                    <a href="manage_appointments.php?date=today" class="mega-section-link">
                                        <i class="fas fa-calendar-day"></i>
                                        Today's Appointments
                                    </a>
                                    <a href="manage_appointments.php?status=confirmed" class="mega-section-link">
                                        <i class="fas fa-check-circle"></i>
                                        Confirmed Appointments
                                    </a>
                                </div>
                            </div>
                            <div class="mega-section">
                                <div class="mega-section-title">
                                    <i class="fas fa-chart-line"></i>
                                    Analytics
                                </div>
                                <div class="mega-section-links">
                                    <a href="reports.php?type=appointments" class="mega-section-link">
                                        <i class="fas fa-chart-line"></i>
                                        Appointment Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>

                <!-- System Menu -->
                <li class="mega-item">
                    <a href="#" class="mega-link has-dropdown">
                        <i class="fas fa-cog"></i>
                        System
                    </a>
                    <div class="mega-dropdown single-column">
                        <div class="mega-dropdown-content">
                            <div class="mega-section">
                                <div class="mega-section-title">
                                    <i class="fas fa-database"></i>
                                    System Management
                                </div>
                                <div class="mega-section-links">
                                    <a href="manage_service.php" class="mega-section-link">
                                        <i class="fas fa-tools"></i>
                                        Services
                                    </a>
                                    <a href="billing.php" class="mega-section-link">
                                        <i class="fas fa-dollar-sign"></i>
                                        Billing
                                    </a>
                                    <a href="reports.php" class="mega-section-link">
                                        <i class="fas fa-chart-bar"></i>
                                        Reports & Analytics
                                    </a>
                                    <a href="system_logs.php" class="mega-section-link">
                                        <i class="fas fa-clipboard-list"></i>
                                        System Logs
                                    </a>
                                    <!-- <a href="clinic_settings.php" class="mega-section-link">
                                        <i class="fas fa-cog"></i>
                                        Clinic Settings
                                    </a> -->
                                </div>
                            </div>
                        </div>
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

        <!-- Mobile Mega Menu Overlay -->
        <div id="mobileMegaMenuOverlay" style="display:none;">
            <div class="mobile-mega-menu-content">
                <button class="mobile-mega-menu-close" onclick="closeMobileMegaMenu()"><i class="fas fa-times"></i></button>
                    <div class="mega-menu-mobile">
                        <!-- Dashboard -->
                        <div class="mega-section">
                            <div class="mega-section-title"><i class="fas fa-home"></i> Dashboard</div>
                            <div class="mega-section-links">
                                <a href="dashboard.php" class="mega-section-link"><i class="fas fa-home"></i> Dashboard</a>
                            </div>
                        </div>
                        <!-- Patients -->
                        <div class="mega-section">
                            <div class="mega-section-title"><i class="fas fa-users"></i> Patients</div>
                            <div class="mega-section-links">
                                <a href="manage_users.php" class="mega-section-link"><i class="fas fa-users-cog"></i> Manage Patients</a>
                                <a href="add_user.php" class="mega-section-link"><i class="fas fa-user-plus"></i> Add New Patient</a>
                                <a href="patient_records.php" class="mega-section-link"><i class="fas fa-file-medical"></i> Medical Records</a>
                                <a href="patient_history.php" class="mega-section-link"><i class="fas fa-clipboard-list"></i> Medical History</a>
                                <a href="billing.php#billing-records" class="mega-section-link"><i class="fas fa-receipt"></i> Billing History</a>
                                <a href="messages.php" class="mega-section-link"><i class="fas fa-envelope"></i> Patient Messages</a>
                                <a href="manage_reviews.php" class="mega-section-link"><i class="fas fa-star"></i> Patient Reviews</a>
                            </div>
                        </div>
                        <!-- Doctors -->
                        <div class="mega-section">
                            <div class="mega-section-title"><i class="fas fa-user-md"></i> Doctors</div>
                            <div class="mega-section-links">
                                <a href="manage_doctors.php" class="mega-section-link"><i class="fas fa-user-cog"></i> Manage Doctors</a>
                                <a href="add_doctor_profile.php" class="mega-section-link"><i class="fas fa-user-plus"></i> Add Doctor</a>
                                <a href="admin_set_unavailable.php" class="mega-section-link"><i class="fas fa-calendar-times"></i> Set Availability</a>
                                <a href="reports.php?type=doctor" class="mega-section-link"><i class="fas fa-file-medical"></i> Doctor Reports</a>
                                <a href="manage_reviews.php?filter=doctor" class="mega-section-link"><i class="fas fa-star"></i> Doctor Reviews</a>
                                <a href="manage_appointments.php?view=doctor" class="mega-section-link"><i class="fas fa-calendar-alt"></i> Doctor Schedules</a>
                            </div>
                        </div>
                        <!-- Appointments -->
                        <div class="mega-section">
                            <div class="mega-section-title"><i class="fas fa-calendar-check"></i> Appointments</div>
                            <div class="mega-section-links">
                                <a href="manage_appointments.php" class="mega-section-link"><i class="fas fa-list"></i> All Appointments</a>
                                <a href="add_appointment.php" class="mega-section-link"><i class="fas fa-plus"></i> Schedule Appointment</a>
                                <a href="manage_appointments.php?date=today" class="mega-section-link"><i class="fas fa-calendar-day"></i> Today's Appointments</a>
                                <a href="manage_appointments.php?status=confirmed" class="mega-section-link"><i class="fas fa-check-circle"></i> Confirmed Appointments</a>
                                <a href="reports.php?type=appointments" class="mega-section-link"><i class="fas fa-chart-line"></i> Appointment Reports</a>
                            </div>
                        </div>
                        <!-- System -->
                        <div class="mega-section">
                            <div class="mega-section-title"><i class="fas fa-cog"></i> System</div>
                            <div class="mega-section-links">
                                <a href="manage_service.php" class="mega-section-link"><i class="fas fa-tools"></i> Services</a>
                                <a href="billing.php" class="mega-section-link"><i class="fas fa-dollar-sign"></i> Billing</a>
                                <a href="reports.php" class="mega-section-link"><i class="fas fa-chart-bar"></i> Reports & Analytics</a>
                                <a href="system_logs.php" class="mega-section-link"><i class="fas fa-clipboard-list"></i> System Logs</a>
                                <!-- <a href="clinic_settings.php" class="mega-section-link"><i class="fas fa-cog"></i> Clinic Settings</a> -->
                            </div>
                        </div>
                    </div>
            </div>
        </div>

    <!-- Mobile Menu -->
    <!-- Mobile Menu removed for overlay mega menu -->
    <script>
        function toggleMobileMenu() {
            // Show overlay mega menu for mobile
            if (window.innerWidth <= 992) {
                document.getElementById('mobileMegaMenuOverlay').style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }
        function closeMobileMegaMenu() {
            document.getElementById('mobileMegaMenuOverlay').style.display = 'none';
            document.body.style.overflow = '';
        }
        // Close overlay when clicking background
        document.addEventListener('click', function(event) {
            var overlay = document.getElementById('mobileMegaMenuOverlay');
            if (overlay.style.display === 'block' && event.target === overlay) {
                closeMobileMegaMenu();
            }
        });
        // Hide overlay on desktop resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                closeMobileMegaMenu();
            }
        });
    </script>