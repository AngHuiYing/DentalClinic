<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Get admin details
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Administrator';

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
    <title>Admin Dashboard - Green Life Dental Clinic</title>
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
            --gray-900: #0f172a;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            line-height: 1.6;
            color: var(--gray-800);
        }

        /* Main Container */
        .main-container {
            min-height: 100vh;
            background: var(--white);
            margin: 20px;
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
        }

        /* Simple Navigation Bar - matching user's screenshot */
        .simple-navbar {
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-sm);
        }

        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            height: 60px;
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 18px;
        }

        .brand-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--gray-900);
        }

        /* Horizontal Navigation Menu */
        .nav-menu {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            list-style: none;
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            color: var(--gray-600);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            border-radius: 8px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .nav-link:hover {
            background: var(--gray-100);
            color: var(--primary-color);
        }

        .nav-link.active {
            background: var(--primary-color);
            color: var(--white);
        }

        .nav-link i {
            font-size: 16px;
        }

        /* User Profile */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--white);
            font-size: 14px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--gray-900);
        }

        .user-role {
            font-size: 12px;
            color: var(--gray-500);
        }

        .logout-btn {
            background: var(--danger-color);
            color: var(--white);
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .logout-btn:hover {
            background: #dc2626;
            color: var(--white);
        }

        .navbar-header {
            display: flex;
            align-items: center;
            width: 100%;
            justify-content: space-between;
        }

        /* Brand Styles */
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--white);
            font-weight: 700;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: translateY(-1px);
            color: var(--white);
        }

        .brand-icon {
            background: rgba(255, 255, 255, 0.2);
            padding: 10px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }

        .brand-icon i {
            font-size: 24px;
            color: var(--white);
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .brand-main {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .brand-sub {
            font-size: 12px;
            opacity: 0.9;
            font-weight: 400;
        }

        /* Mega Menu Styles */
        .mega-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
            margin-left: 3rem;
        }

        .mega-item {
            position: relative;
        }

        .mega-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            color: var(--white);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            border-radius: 10px;
            transition: all 0.3s ease;
            position: relative;
            white-space: nowrap;
        }

        .mega-link:hover,
        .mega-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: var(--white);
            transform: translateY(-1px);
            backdrop-filter: blur(10px);
        }

        .mega-link i {
            font-size: 16px;
        }

        /* Dropdown Styles */
        .mega-dropdown {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow-xl);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            margin-top: 8px;
            min-width: 800px;
            border: 1px solid var(--gray-200);
        }

        .mega-item:hover .mega-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }

        .mega-dropdown-content {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            padding: 2rem;
        }

        /* Single column dropdown for System menu */
        .mega-dropdown-single {
            min-width: 300px;
        }

        .mega-dropdown-single .mega-dropdown-content {
            grid-template-columns: 1fr;
            padding: 1.5rem;
        }

        .mega-section {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .mega-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: var(--gray-800);
            font-size: 14px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--gray-100);
            margin-bottom: 4px;
        }

        .mega-section-title i {
            color: var(--primary-color);
            font-size: 16px;
        }

        .mega-section-links {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .mega-section-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            text-decoration: none;
            color: var(--gray-600);
            font-size: 13px;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-weight: 400;
        }

        .mega-section-link:hover {
            background: var(--gray-50);
            color: var(--primary-color);
            transform: translateX(4px);
        }

        .mega-section-link i {
            font-size: 14px;
            width: 16px;
            text-align: center;
        }

        /* User Profile Styles */
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 14px;
        }

        .user-details {
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
            font-weight: 400;
        }

        .logout-btn {
            background: var(--danger-color);
            color: var(--white);
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-1px);
            color: var(--white);
        }

        /* Header Section */
        .header-section {
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
            padding: 2.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .welcome-text h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .welcome-text p {
            font-size: 1.1rem;
            color: var(--gray-600);
            font-weight: 400;
        }

        /* Content Section */
        .content-section {
            padding: 2rem;
        }

        /* Stats Cards */
        .stats-row {
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
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
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gray-600);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Dashboard Cards */
        .dashboard-cards {
            margin-top: 2rem;
        }

        .dashboard-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 16px;
            padding: 2rem;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: block;
            height: 100%;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
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
            border-color: var(--card-color, var(--primary-color));
            color: inherit;
        }

        .dashboard-card:hover::before {
            transform: scaleX(1);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--card-color, var(--primary-color)), rgba(var(--card-color, var(--primary-color)), 0.8));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .card-icon i {
            font-size: 24px;
            color: var(--white);
        }

        .dashboard-card:hover .card-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
            line-height: 1.3;
        }

        .card-description {
            color: var(--gray-600);
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

        /* Additional enhancements */
        .dashboard-card:active {
            transform: translateY(-2px) !important;
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
                    <?php echo strtoupper(substr($_SESSION['admin_username'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo $_SESSION['admin_username']; ?></div>
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
            <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>! Here's what's happening at your clinic today.</p>
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
                                        <a href="reports.php?type=patient" class="mega-section-link">
                                            <i class="fas fa-chart-line"></i>
                                            Patient Reports
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
                                            <i class="fas fa-analytics"></i>
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
                        <a href="#" class="mega-link">
                            <i class="fas fa-calendar-check"></i>
                            Appointments
                            <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
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
                                            <i class="fas fa-plus-circle"></i>
                                            Schedule Appointment
                                        </a>
                                        <a href="manage_appointments.php?status=pending" class="mega-section-link">
                                            <i class="fas fa-clock"></i>
                                            Pending Appointments
                                        </a>
                                    </div>
                                </div>
                                <div class="mega-section">
                                    <div class="mega-section-title">
                                        <i class="fas fa-calendar-day"></i>
                                        Schedule Views
                                    </div>
                                    <div class="mega-section-links">
                                        <a href="manage_appointments.php?view=today" class="mega-section-link">
                                            <i class="fas fa-calendar-day"></i>
                                            Today's Schedule
                                        </a>
                                        <a href="manage_appointments.php?view=week" class="mega-section-link">
                                            <i class="fas fa-calendar-week"></i>
                                            This Week
                                        </a>
                                        <a href="manage_appointments.php?view=month" class="mega-section-link">
                                            <i class="fas fa-calendar"></i>
                                            Monthly View
                                        </a>
                                    </div>
                                </div>
                                <div class="mega-section">
                                    <div class="mega-section-title">
                                        <i class="fas fa-chart-pie"></i>
                                        Reports
                                    </div>
                                    <div class="mega-section-links">
                                        <a href="reports.php?type=appointment" class="mega-section-link">
                                            <i class="fas fa-chart-line"></i>
                                            Appointment Analytics
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>

                    <!-- Services Menu -->
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
                                            <i class="fas fa-list-ul"></i>
                                            Manage Services
                                        </a>
                                        <a href="billing.php" class="mega-section-link">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                            Billing & Payments
                                        </a>
                                    </div>
                                </div>
                                <div class="mega-section">
                                    <div class="mega-section-title">
                                        <i class="fas fa-chart-bar"></i>
                                        Analytics
                                    </div>
                                    <div class="mega-section-links">
                                        <a href="reports.php?type=service" class="mega-section-link">
                                            <i class="fas fa-chart-pie"></i>
                                            Service Reports
                                        </a>
                                        <a href="view_billing_report.php" class="mega-section-link">
                                            <i class="fas fa-receipt"></i>
                                            Revenue Reports
                                        </a>
                                    </div>
                                </div>
                                <div class="mega-section">
                                    <div class="mega-section-title">
                                        <i class="fas fa-cog"></i>
                                        Settings
                                    </div>
                                    <div class="mega-section-links">
                                        <a href="clinic_settings.php" class="mega-section-link">
                                            <i class="fas fa-hospital"></i>
                                            Clinic Settings
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
                        <div class="mega-dropdown mega-dropdown-single">
                            <div class="mega-dropdown-content">
                                <div class="mega-section">
                                    <div class="mega-section-title">
                                        <i class="fas fa-database"></i>
                                        System Management
                                    </div>
                                    <div class="mega-section-links">
                                        <a href="system_logs.php" class="mega-section-link">
                                            <i class="fas fa-clipboard-list"></i>
                                            System Logs
                                        </a>
                                        <a href="reports.php" class="mega-section-link">
                                            <i class="fas fa-chart-bar"></i>
                                            Reports & Analytics
                                        </a>
                                        <a href="clinic_settings.php" class="mega-section-link">
                                            <i class="fas fa-cog"></i>
                                            Clinic Settings
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>

                <!-- User Profile -->
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($admin_name); ?></div>
                            <div class="user-role">Administrator</div>
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

    <!-- Header Section -->
    <div class="main-container">
        <div class="header-section">
            <div class="welcome-text">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <i class="fas fa-hospital" style="font-size: 3rem; color: var(--primary-color);"></i>
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