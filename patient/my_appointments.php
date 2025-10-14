<?php
session_start();
include "../db.php"; // 连接数据库
date_default_timezone_set("Asia/Kuala_Lumpur");

// 检查用户是否已登录
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: ../patient/login.php");
    exit;
}

$patient_id = $_SESSION['user_id'];

// 取出當前用戶的 email
$email_sql = "SELECT email FROM users WHERE id = ?";
$email_stmt = $conn->prepare($email_sql);
$email_stmt->bind_param("i", $patient_id);
$email_stmt->execute();
$email_result = $email_stmt->get_result();
$user_data = $email_result->fetch_assoc();
$patient_email = $user_data['email'];

// 分頁設定
$appointments_per_page = 10; // 每頁顯示預約數
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $appointments_per_page;

// 日期限制設定 (預設顯示最近12個月)
$show_all_records = isset($_GET['show_all']) && $_GET['show_all'] === '1';
$default_limit_months = 12;

$search_query = "";
$date_from = "";
$date_to = "";
$status_filter = "";

if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}
if (isset($_GET['date_from'])) {
    $date_from = trim($_GET['date_from']);
}
if (isset($_GET['date_to'])) {
    $date_to = trim($_GET['date_to']);
}
if (isset($_GET['status'])) {
    $status_filter = trim($_GET['status']);
}

// Build the WHERE clause dynamically
$where_conditions = ["(a.patient_id = ? OR a.patient_email = ?)"];
$params = [$patient_id, $patient_email];
$param_types = "is";

// 預設日期限制 (如果沒有指定顯示全部記錄且沒有自訂日期範圍)
if (!$show_all_records && empty($date_from) && empty($date_to)) {
    $default_from_date = date('Y-m-d', strtotime("-{$default_limit_months} months"));
    $where_conditions[] = "a.appointment_date >= ?";
    $params[] = $default_from_date;
    $param_types .= "s";
}

// Doctor name search
if (!empty($search_query)) {
    $where_conditions[] = "(d.name LIKE ? OR d.specialty LIKE ?)";
    $params[] = "%" . $search_query . "%";
    $params[] = "%" . $search_query . "%";
    $param_types .= "ss";
}

// Date range filter
if (!empty($date_from)) {
    $where_conditions[] = "a.appointment_date >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}
if (!empty($date_to)) {
    $where_conditions[] = "a.appointment_date <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

// Status filter
if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

// 先計算總記錄數
$count_sql = "SELECT COUNT(*) as total FROM appointments a
             LEFT JOIN doctors d ON a.doctor_id = d.id
             LEFT JOIN users u ON a.patient_id = u.id
             WHERE " . implode(" AND ", $where_conditions);
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_records = $total_result->fetch_assoc()['total'];
$count_stmt->close();

// 計算分頁
$total_pages = ceil($total_records / $appointments_per_page);
$start_record = ($current_page - 1) * $appointments_per_page + 1;
$end_record = min($current_page * $appointments_per_page, $total_records);

$sql = "SELECT a.id, d.name AS doctor_name, d.specialty, d.id AS doctor_id,
               a.appointment_date, a.appointment_time, a.status,
               a.patient_email, u.email AS user_email
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN users u ON a.patient_id = u.id
        WHERE " . implode(" AND ", $where_conditions) . "
        ORDER BY a.appointment_date DESC
        LIMIT ? OFFSET ?";

$params[] = $appointments_per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Green Life Dental Clinic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --clinic-primary: #2d5aa0;
            --clinic-secondary: #4a9396;
            --clinic-accent: #84c69b;
            --clinic-light: #f1f8e8;
            --clinic-warm: #f9f7ef;
            --clinic-text: #2c3e50;
            --clinic-muted: #7f8c8d;
            --clinic-success: #27ae60;
            --clinic-warning: #f39c12;
            --clinic-danger: #e74c3c;
            --clinic-white: #ffffff;
            --clinic-shadow: 0 2px 10px rgba(45, 90, 160, 0.1);
            --clinic-shadow-hover: 0 8px 25px rgba(45, 90, 160, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--clinic-light);
            color: var(--clinic-text);
            line-height: 1.6;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--clinic-primary) 0%, var(--clinic-secondary) 100%);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2rem;
            position: relative;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
                radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.1) 2px, transparent 2px);
            background-size: 40px 40px;
        }

        .page-header .container {
            position: relative;
            z-index: 1;
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-align: center;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .page-header p {
            font-size: 1rem;
            opacity: 0.95;
            text-align: center;
        }

        .appointments-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .search-card {
            background: var(--clinic-white);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--clinic-shadow);
            border: 1px solid rgba(45, 90, 160, 0.08);
        }

        .search-form {
            display: block;
        }

        .search-input, .form-select {
            height: 2.4rem;
            border: 2px solid #e8ecef;
            border-radius: 8px;
            padding: 0 0.75rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: var(--clinic-warm);
        }

        .search-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--clinic-primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(45, 90, 160, 0.1);
        }

        .search-btn {
            height: 2.4rem;
            padding: 0 1rem;
            background: var(--clinic-primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .search-btn i {
            font-size: 0.8rem;
        }

        .search-btn:hover {
            background: #234a87;
            transform: translateY(-1px);
        }

        .btn-outline-secondary {
            height: 2.4rem;
            padding: 0 1rem;
            border: 2px solid #6c757d;
            color: #6c757d;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
        }

        .quick-filter {
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .quick-filter:hover {
            transform: translateY(-1px);
        }

        .quick-filter.active {
            background: var(--clinic-primary);
            border-color: var(--clinic-primary);
            color: white;
        }

        .appointments-card {
            background: var(--clinic-white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--clinic-shadow);
            border: 1px solid rgba(45, 90, 160, 0.08);
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--clinic-secondary) 0%, var(--clinic-accent) 100%);
            color: white;
            padding: 1.2rem 1.5rem;
        }

        .card-header-custom h3 {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .appointments-grid {
            padding: 1.5rem;
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.2rem;
        }

        .appointment-item {
            background: var(--clinic-warm);
            border-radius: 10px;
            padding: 1.2rem;
            border-left: 4px solid var(--clinic-primary);
            transition: all 0.3s ease;
            position: relative;
            border: 1px solid rgba(45, 90, 160, 0.05);
        }

        .appointment-item:hover {
            transform: translateX(3px);
            box-shadow: var(--clinic-shadow-hover);
            background: white;
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.8rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .doctor-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--clinic-text);
            margin-bottom: 0.3rem;
        }

        .doctor-specialty {
            color: var(--clinic-secondary);
            font-weight: 500;
            font-size: 0.85rem;
            background: rgba(74, 147, 150, 0.1);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            display: inline-block;
        }

        .appointment-status {
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-confirmed {
            background: rgba(39, 174, 96, 0.1);
            color: var(--clinic-success);
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .status-pending {
            background: rgba(243, 156, 18, 0.1);
            color: var(--clinic-warning);
            border: 1px solid rgba(243, 156, 18, 0.2);
        }

        .status-cancelled {
            background: rgba(231, 76, 60, 0.1);
            color: var(--clinic-danger);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.8rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--clinic-muted);
            font-size: 0.9rem;
        }

        .detail-item i {
            color: var(--clinic-secondary);
            width: 14px;
            font-size: 0.85rem;
        }

        .appointment-actions {
            display: flex;
            gap: 0.8rem;
            justify-content: flex-end;
            margin-top: 0.8rem;
        }

        .btn-cancel {
            padding: 0.4rem 1rem;
            background: var(--clinic-danger);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-right: 0.5rem;
        }

        .btn-cancel:hover {
            background: #c0392b;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
            color: white;
            text-decoration: none;
        }

        .btn-rate {
            padding: 0.4rem 1rem;
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #333;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-right: 0.5rem;
            border: 1px solid #f1c40f;
        }

        .btn-rate:hover {
            background: linear-gradient(135deg, #f1c40f, #e6d03e);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(241, 196, 15, 0.4);
            color: #333;
            text-decoration: none;
        }

        .btn-reviewed {
            padding: 0.4rem 1rem;
            background: var(--clinic-success);
            color: white;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-right: 0.5rem;
            opacity: 0.8;
        }

        .btn-disabled {
            padding: 0.4rem 1rem;
            background: var(--clinic-muted);
            color: white;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.85rem;
            opacity: 0.7;
            cursor: not-allowed;
        }

        .no-appointments {
            text-align: center;
            padding: 3rem 2rem;
            background: var(--clinic-white);
            border-radius: 12px;
            box-shadow: var(--clinic-shadow);
            border: 1px solid rgba(45, 90, 160, 0.08);
        }

        .no-appointments i {
            font-size: 3rem;
            color: var(--clinic-muted);
            margin-bottom: 1rem;
            opacity: 0.7;
        }

        .no-appointments h3 {
            color: var(--clinic-text);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .no-appointments p {
            color: var(--clinic-muted);
            margin-bottom: 1.5rem;
        }

        .btn-primary-action {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.6rem 1.2rem;
            background: var(--clinic-primary);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary-action:hover {
            background: #234a87;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(45, 90, 160, 0.3);
            color: white;
            text-decoration: none;
        }

        .waiting-assignment {
            font-style: italic;
            color: var(--clinic-warning);
            background: rgba(243, 156, 18, 0.1);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.85rem;
            border: 1px solid rgba(243, 156, 18, 0.2);
        }

        /* Clinic-style medical cross decoration */
        .appointment-item::before {
            content: '';
            position: absolute;
            top: 0.8rem;
            right: 0.8rem;
            width: 12px;
            height: 12px;
            background: var(--clinic-accent);
            border-radius: 50%;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.8rem;
            }

            .appointments-container {
                padding: 0 0.5rem;
            }

            .search-form {
                flex-direction: column;
            }

            .search-input {
                width: 100%;
            }

            .appointment-header {
                flex-direction: column;
                gap: 0.8rem;
            }

            .appointment-details {
                grid-template-columns: 1fr;
            }

            .appointment-actions {
                justify-content: center;
            }

            .appointments-grid {
                padding: 1rem;
            }
        }

        /* Clinic-inspired subtle animations */
        .appointment-item {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .appointment-item:nth-child(1) { animation-delay: 0.1s; }
        .appointment-item:nth-child(2) { animation-delay: 0.2s; }
        .appointment-item:nth-child(3) { animation-delay: 0.3s; }
        .appointment-item:nth-child(4) { animation-delay: 0.4s; }
        .appointment-item:nth-child(5) { animation-delay: 0.5s; }

        /* Professional clinic styling */
        .clinic-badge {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
            padding: 0.15rem 0.5rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Clinic background pattern */
        .clinic-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            background: var(--clinic-light);
        }

        .clinic-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 50px 50px, rgba(45, 90, 160, 0.03) 2px, transparent 2px),
                radial-gradient(circle at 100px 100px, rgba(74, 147, 150, 0.03) 1px, transparent 1px);
            background-size: 100px 100px, 50px 50px;
        }

        /* Clinic professional icons */
        .appointment-item::after {
            content: '';
            position: absolute;
            bottom: 0.8rem;
            right: 0.8rem;
            width: 20px;
            height: 20px;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%234a9396"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>');
            background-size: contain;
            opacity: 0.2;
        }

        /* Clinic appointment priority indicators */
        .status-confirmed::before {
            content: '';
            position: absolute;
            left: -2px;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 6px;
            background: var(--clinic-success);
            border-radius: 50%;
        }

        .status-pending::before {
            content: '';
            position: absolute;
            left: -2px;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 6px;
            background: var(--clinic-warning);
            border-radius: 50%;
            animation: pulse-warning 1.5s infinite;
        }

        @keyframes pulse-warning {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Pagination Styles */
        .pagination-container {
            background: var(--clinic-warm);
            padding: 1.5rem;
            border-top: 1px solid rgba(45, 90, 160, 0.08);
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            background: var(--clinic-white);
            border: 1px solid rgba(45, 90, 160, 0.2);
            color: var(--clinic-primary);
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            min-width: 40px;
            justify-content: center;
        }
        
        .page-btn:hover {
            background: var(--clinic-primary);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(45, 90, 160, 0.3);
        }
        
        .page-btn.active {
            background: var(--clinic-primary);
            color: white;
            font-weight: 600;
        }
        
        .page-btn.disabled {
            background: #f8f9fa;
            color: #adb5bd;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-info {
            text-align: center;
            color: var(--clinic-muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        /* Pagination Styles */
        .pagination-container {
            background: var(--clinic-warm);
            padding: 1.5rem;
            border-top: 1px solid rgba(45, 90, 160, 0.08);
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            background: var(--clinic-white);
            border: 1px solid rgba(45, 90, 160, 0.2);
            color: var(--clinic-primary);
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            min-width: 40px;
            justify-content: center;
        }
        
        .page-btn:hover {
            background: var(--clinic-primary);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(45, 90, 160, 0.3);
        }
        
        .page-btn.active {
            background: var(--clinic-primary);
            color: white;
            font-weight: 600;
        }
        
        .page-btn.disabled {
            background: #f8f9fa;
            color: #adb5bd;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-info {
            text-align: center;
            color: var(--clinic-muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <!-- Clinic Background Elements -->
    <div class="clinic-bg">
        <div class="clinic-pattern"></div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-calendar-check"></i> My Appointments</h1>
            <p>Manage all your clinic appointments and records</p>
        </div>
    </div>

    <div class="appointments-container">
        <!-- Success/Error Messages -->
        <?php if (isset($_GET['review_success']) || isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" style="background: linear-gradient(135deg, #10b981, #059669); border: none; border-radius: 12px; color: white; margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Review Submitted Successfully!</strong>
                <?php if (isset($_SESSION['success'])): ?>
                    <?= $_SESSION['success'] ?>
                    <?php unset($_SESSION['success']); ?>
                <?php else: ?>
                    Your review has been submitted and is now visible to other patients.
                <?php endif; ?>
                <br><small><i class="fas fa-star me-1"></i>You can view or edit your review anytime in <a href="my_reviews.php" class="text-white text-decoration-underline fw-bold">My Reviews</a> section.</small>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="background: linear-gradient(135deg, #ef4444, #dc2626); border: none; border-radius: 12px; color: white; margin-bottom: 1.5rem;">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search Section -->
        <div class="search-card">
            <form method="GET" class="search-form">
                <div class="row g-3">
                    <!-- Doctor/Specialty Search -->
                    <div class="col-md-4">
                        <label for="search" class="form-label text-muted small mb-1">
                            <i class="fas fa-user-md me-1"></i>Doctor Name or Specialty
                        </label>
                        <input type="text" 
                               id="search"
                               name="search" 
                               class="form-control search-input" 
                               placeholder="Search doctor or specialty..." 
                               value="<?= htmlspecialchars($search_query) ?>">
                    </div>
                    
                    <!-- Date From -->
                    <div class="col-md-2">
                        <label for="date_from" class="form-label text-muted small mb-1">
                            <i class="fas fa-calendar me-1"></i>From Date
                        </label>
                        <input type="date" 
                               id="date_from"
                               name="date_from" 
                               class="form-control search-input" 
                               value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    
                    <!-- Date To -->
                    <div class="col-md-2">
                        <label for="date_to" class="form-label text-muted small mb-1">
                            <i class="fas fa-calendar me-1"></i>To Date
                        </label>
                        <input type="date" 
                               id="date_to"
                               name="date_to" 
                               class="form-control search-input" 
                               value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="col-md-2">
                        <label for="status" class="form-label text-muted small mb-1">
                            <i class="fas fa-filter me-1"></i>Status
                        </label>
                        <select id="status" name="status" class="form-select search-input">
                            <option value="">All Status</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <!-- Search Buttons -->
                    <div class="col-md-2">
                        <label class="form-label text-muted small mb-1 d-block">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn search-btn flex-fill">
                                <i class="fas fa-search"></i>Search
                            </button>
                            <a href="my_appointments.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Date Filters -->
                <div class="row mt-3">
                    <div class="col-12">
                        <small class="text-muted me-2">Quick filters:</small>
                        <button type="button" class="btn btn-sm btn-outline-primary me-2 quick-filter" data-filter="today">Today</button>
                        <button type="button" class="btn btn-sm btn-outline-primary me-2 quick-filter" data-filter="this-week">This Week</button>
                        <button type="button" class="btn btn-sm btn-outline-primary me-2 quick-filter" data-filter="this-month">This Month</button>
                        <button type="button" class="btn btn-sm btn-outline-primary me-2 quick-filter" data-filter="upcoming">Upcoming</button>
                        <button type="button" class="btn btn-sm btn-outline-primary quick-filter" data-filter="past">Past</button>
                    </div>
                </div>
                
                <!-- Record Display Options -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">Display options:</small>
                                <?php if (!$show_all_records): ?>
                                    <span class="badge bg-info ms-2">
                                        <i class="fas fa-calendar-alt"></i> Showing last <?= $default_limit_months ?> months
                                    </span>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['show_all' => '1'])) ?>" 
                                       class="btn btn-sm btn-outline-warning ms-2">
                                        <i class="fas fa-history"></i> Show All Records
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark ms-2">
                                        <i class="fas fa-infinity"></i> Showing all records
                                    </span>
                                    <a href="?<?= http_build_query(array_diff_key($_GET, ['show_all' => ''])) ?>" 
                                       class="btn btn-sm btn-outline-info ms-2">
                                        <i class="fas fa-filter"></i> Show Recent Only
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php if ($total_records > 0): ?>
                            <small class="text-muted">
                                <?= number_format($total_records) ?> total appointments found
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="appointments-card">
                <div class="card-header-custom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">
                            <i class="fas fa-stethoscope"></i>
                            Appointment Records
                            <span class="clinic-badge"><?= $result->num_rows ?> of <?= number_format($total_records) ?></span>
                        </h3>
                        <?php if ($total_pages > 1): ?>
                        <small class="text-white opacity-75">
                            Page <?= $current_page ?> of <?= $total_pages ?> 
                            (Showing <?= $start_record ?>-<?= $end_record ?>)
                        </small>
                        <?php endif; ?>
                    </div>
                    <div class="mt-2">
                        <a href="my_reviews.php" class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); border-radius: 8px;">
                            <i class="fas fa-star me-1"></i>View My Reviews
                        </a>
                    </div>
                </div>

                <div class="appointments-grid">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php 
                            $is_email_match = ($row['patient_email'] === $row['user_email']);
                            $now = new DateTime("now");
                            $appointmentDateTime = new DateTime($row['appointment_date'] . ' ' . $row['appointment_time']);
                            
                            // Check if appointment has passed (date + time)
                            $hasPassedDateTime = $appointmentDateTime < $now;
                            $canCancel = !$hasPassedDateTime; // 只有在預約時間還沒過期時才能取消
                            
                            // Status class mapping
                            $statusClass = 'status-' . strtolower($row['status']);
                        ?>
                        <div class="appointment-item">
                            <div class="appointment-header">
                                <div class="doctor-info">
                                    <h4>
                                        <?php if ($row['doctor_name']): ?>
                                            <i class="fas fa-user-md"></i>
                                            Dr. <?= htmlspecialchars($row['doctor_name']) ?>
                                        <?php else: ?>
                                            <i class="fas fa-clock"></i>
                                            Doctor Assignment Pending
                                        <?php endif; ?>
                                    </h4>
                                    <?php if ($row['specialty']): ?>
                                        <div class="doctor-specialty">
                                            <i class="fas fa-tooth"></i>
                                            <?= htmlspecialchars($row['specialty']) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="waiting-assignment">
                                            <i class="fas fa-hourglass-half"></i> Waiting for Doctor Assignment
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="appointment-status <?= $statusClass ?>">
                                    <?php
                                        $statusMap = [
                                            'confirmed' => 'Confirmed',
                                            'pending' => 'Pending',
                                            'cancelled' => 'Cancelled'
                                        ];
                                        echo $statusMap[strtolower($row['status'])] ?? $row['status'];
                                    ?>
                                </div>
                            </div>

                            <div class="appointment-details">
                                <div class="detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?= date('l, F j, Y', strtotime($row['appointment_date'])) ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?= date('g:i A', strtotime($row['appointment_time'])) ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <i class="fas fa-notes-medical"></i>
                                    <span>
                                        <?php
                                            if ($hasPassedDateTime) {
                                                echo '<span style="color: var(--clinic-success);">Completed</span>';
                                            } else {
                                                $diff = $now->diff($appointmentDateTime);
                                                if ($diff->days > 0) {
                                                    echo $diff->days . ' days remaining';
                                                } elseif ($diff->h > 0) {
                                                    echo $diff->h . ' hours remaining';
                                                } else {
                                                    echo '<span style="color: var(--clinic-warning); font-weight: 600;">Soon</span>';
                                                }
                                            }
                                        ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Appointment ID: #<?= str_pad($row['id'], 6, '0', STR_PAD_LEFT) ?></span>
                                </div>
                            </div>

                            <div class="appointment-actions">
                                <?php if ($canCancel && strtolower($row['status']) !== 'cancelled_by_admin' && strtolower($row['status']) !== 'cancelled_by_patient'): ?>
                                    <a href="cancel_appointment.php?id=<?= $row['id'] ?>" 
                                       class="btn-cancel"
                                       onclick="return confirm('Are you sure you want to cancel this appointment? This action cannot be undone.');">
                                        <i class="fas fa-times"></i> Cancel Appointment
                                    </a>
                                <?php elseif ($hasPassedDateTime && strtolower($row['status']) !== 'cancelled_by_admin' && strtolower($row['status']) !== 'cancelled_by_patient'): ?>
                                    <?php
                                    // Check if user has already reviewed this doctor for this appointment
                                    $review_check_sql = "SELECT id FROM doctor_reviews WHERE doctor_id = ? AND patient_id = ? AND appointment_id = ?";
                                    $review_check_stmt = $conn->prepare($review_check_sql);
                                    $review_check_stmt->bind_param("iii", $row['doctor_id'], $patient_id, $row['id']);
                                    $review_check_stmt->execute();
                                    $has_reviewed = $review_check_stmt->get_result()->num_rows > 0;
                                    ?>
                                    
                                    <?php if (!$has_reviewed && $row['doctor_id']): ?>
                                        <a href="rate_doctor.php?doctor_id=<?= $row['doctor_id'] ?>&appointment_id=<?= $row['id'] ?>" 
                                           class="btn-rate">
                                            <i class="fas fa-star"></i> Rate Doctor
                                        </a>
                                    <?php else: ?>
                                        <span class="btn-reviewed">
                                            <i class="fas fa-check-circle"></i> Reviewed
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span class="btn-disabled">
                                        <i class="fas fa-ban"></i> Past Appointment
                                    </span>
                                <?php else: ?>
                                    <span class="btn-disabled">
                                        <i class="fas fa-ban"></i>
                                        <?= $hasPassedDateTime ? 'Past Appointment' : 'Cannot Cancel' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?= $start_record ?>-<?= $end_record ?> of <?= number_format($total_records) ?> appointments
                    </div>
                    
                    <div class="pagination-nav">
                        <!-- Previous Page -->
                        <?php if ($current_page > 1): ?>
                            <?php 
                            $prev_params = $_GET;
                            $prev_params['page'] = $current_page - 1;
                            ?>
                            <a href="?<?= http_build_query($prev_params) ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                <i class="fas fa-chevron-left"></i> Previous
                            </span>
                        <?php endif; ?>
                        
                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        // Show first page if not in range
                        if ($start_page > 1) {
                            $first_params = $_GET;
                            $first_params['page'] = 1;
                            echo '<a href="?' . http_build_query($first_params) . '" class="page-btn">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="page-btn disabled">...</span>';
                            }
                        }
                        
                        // Show page numbers in range
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $page_params = $_GET;
                            $page_params['page'] = $i;
                            if ($i == $current_page) {
                                echo '<span class="page-btn active">' . $i . '</span>';
                            } else {
                                echo '<a href="?' . http_build_query($page_params) . '" class="page-btn">' . $i . '</a>';
                            }
                        }
                        
                        // Show last page if not in range
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="page-btn disabled">...</span>';
                            }
                            $last_params = $_GET;
                            $last_params['page'] = $total_pages;
                            echo '<a href="?' . http_build_query($last_params) . '" class="page-btn">' . $total_pages . '</a>';
                        }
                        ?>
                        
                        <!-- Next Page -->
                        <?php if ($current_page < $total_pages): ?>
                            <?php 
                            $next_params = $_GET;
                            $next_params['page'] = $current_page + 1;
                            ?>
                            <a href="?<?= http_build_query($next_params) ?>" class="page-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                Next <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="no-appointments">
                <i class="fas fa-calendar-times"></i>
                <h3>No Appointments Found</h3>
                <p>You don't have any appointment records yet. Book your first appointment to start your healthcare journey!</p>
                <a href="book_appointment.php" class="btn-primary-action">
                    <i class="fas fa-plus"></i> Book Appointment
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enhanced search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('#search');
            const searchForm = document.querySelector('.search-form');
            const searchBtn = document.querySelector('.search-btn');
            const dateFromInput = document.querySelector('#date_from');
            const dateToInput = document.querySelector('#date_to');
            const statusSelect = document.querySelector('#status');

            // Quick filter functionality
            document.querySelectorAll('.quick-filter').forEach(btn => {
                btn.addEventListener('click', function() {
                    const filter = this.dataset.filter;
                    const today = new Date();
                    
                    // Remove active class from all buttons
                    document.querySelectorAll('.quick-filter').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Clear existing date values
                    dateFromInput.value = '';
                    dateToInput.value = '';
                    statusSelect.value = '';
                    
                    switch(filter) {
                        case 'today':
                            const todayStr = today.toISOString().split('T')[0];
                            dateFromInput.value = todayStr;
                            dateToInput.value = todayStr;
                            break;
                            
                        case 'this-week':
                            const startOfWeek = new Date(today);
                            startOfWeek.setDate(today.getDate() - today.getDay());
                            const endOfWeek = new Date(startOfWeek);
                            endOfWeek.setDate(startOfWeek.getDate() + 6);
                            
                            dateFromInput.value = startOfWeek.toISOString().split('T')[0];
                            dateToInput.value = endOfWeek.toISOString().split('T')[0];
                            break;
                            
                        case 'this-month':
                            const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                            const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                            
                            dateFromInput.value = startOfMonth.toISOString().split('T')[0];
                            dateToInput.value = endOfMonth.toISOString().split('T')[0];
                            break;
                            
                        case 'upcoming':
                            dateFromInput.value = today.toISOString().split('T')[0];
                            statusSelect.value = 'confirmed';
                            break;
                            
                        case 'past':
                            dateToInput.value = today.toISOString().split('T')[0];
                            break;
                    }
                    
                    // Auto-submit the form
                    searchForm.submit();
                });
            });

            // Date validation
            dateFromInput.addEventListener('change', function() {
                if (dateToInput.value && this.value > dateToInput.value) {
                    alert('Start date cannot be later than end date');
                    this.value = '';
                }
            });

            dateToInput.addEventListener('change', function() {
                if (dateFromInput.value && this.value < dateFromInput.value) {
                    alert('End date cannot be earlier than start date');
                    this.value = '';
                }
            });

            // Debounced search for doctor name
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                document.querySelectorAll('.quick-filter').forEach(b => b.classList.remove('active'));
                
                searchTimeout = setTimeout(() => {
                    console.log('Searching for:', this.value);
                }, 500);
            });

            // Clear filters functionality
            document.querySelector('.btn-outline-secondary').addEventListener('click', function(e) {
                e.preventDefault();
                
                // Clear all form fields
                searchInput.value = '';
                dateFromInput.value = '';
                dateToInput.value = '';
                statusSelect.value = '';
                
                // Remove active states
                document.querySelectorAll('.quick-filter').forEach(b => b.classList.remove('active'));
                
                // Redirect to clear URL
                window.location.href = 'my_appointments.php';
            });

            // Search form enhancement
            searchForm.addEventListener('submit', function() {
                searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                searchBtn.disabled = true;
            });

            // Set active state for current filters
            const urlParams = new URLSearchParams(window.location.search);
            const currentDateFrom = urlParams.get('date_from');
            const currentDateTo = urlParams.get('date_to');
            const currentStatus = urlParams.get('status');
            
            // Check if any quick filter matches current state
            if (currentDateFrom && currentDateTo) {
                const today = new Date().toISOString().split('T')[0];
                
                if (currentDateFrom === today && currentDateTo === today) {
                    document.querySelector('[data-filter="today"]')?.classList.add('active');
                } else if (currentStatus === 'confirmed' && currentDateFrom === today && !currentDateTo) {
                    document.querySelector('[data-filter="upcoming"]')?.classList.add('active');
                }
            } else if (!currentDateFrom && currentDateTo) {
                const today = new Date().toISOString().split('T')[0];
                if (currentDateTo === today) {
                    document.querySelector('[data-filter="past"]')?.classList.add('active');
                }
            }

            // Animation for appointment items
            const appointmentItems = document.querySelectorAll('.appointment-item');
            appointmentItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(30px)';
                item.style.transition = `all 0.6s ease ${index * 0.1}s`;
                
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, 100);
            });

            // Enhanced cancel confirmation
            const cancelButtons = document.querySelectorAll('.btn-cancel');
            cancelButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    
                    // Create custom confirmation modal
                    const confirmed = confirm(
                        '⚠️ Cancel Appointment\n\n' +
                        'Are you sure you want to cancel this appointment?\n' +
                        'This action cannot be undone and you may need to reschedule.\n\n' +
                        'Click OK to proceed with cancellation.'
                    );
                    
                    if (confirmed) {
                        // Add loading state
                        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Cancelling...';
                        this.style.pointerEvents = 'none';
                        
                        // Navigate to cancellation
                        window.location.href = href;
                    }
                });
            });

            // Add hover effects for appointment items
            appointmentItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.borderLeftColor = 'var(--clinic-secondary)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.borderLeftColor = 'var(--clinic-primary)';
                });
            });

            // Status badge animations
            const statusBadges = document.querySelectorAll('.appointment-status');
            statusBadges.forEach(badge => {
                if (badge.textContent.toLowerCase().includes('confirmed')) {
                    badge.style.animation = 'pulse 2s infinite';
                }
            });
        });

        // CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
                70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
                100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
