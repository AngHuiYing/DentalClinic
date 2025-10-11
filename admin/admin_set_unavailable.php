<?php
session_start();
include "../db.php";

// 验证管理员身份
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 获取所有医生
$doctors_sql = "SELECT id, name FROM doctors";
$doctors_result = $conn->query($doctors_sql);

// 处理删除功能
if (isset($_GET['delete_id']) && isset($_GET['doctor_id'])) {
    $delete_id = $_GET['delete_id'];
    $doctor_id = $_GET['doctor_id'];
    $current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    
    $delete_sql = "DELETE FROM unavailable_slots WHERE id = ? AND doctor_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $delete_id, $doctor_id);
    $delete_stmt->execute();
    
    // 重定向时保持当前页面
    header("Location: admin_set_unavailable.php?doctor_id=" . $doctor_id . "&page=" . $current_page);
    exit;
}

// 处理设置不可用时间段
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['doctor_id'])) {
    $doctor_id = $_POST['doctor_id'];
    $date = $_POST['date'];
    $from_time = $_POST['from_time'];
    $to_time = $_POST['to_time'];

    // 只允许设置未来时间
    if (strtotime($date . ' ' . $to_time) <= time()) {
        echo "<script>alert('Please select a future time slot.');</script>";
    } elseif ($from_time >= $to_time) {
        echo "<script>alert('End time must be later than start time.');</script>";
    } else {
        // 检查是否存在重叠
        $check_sql = "SELECT * FROM unavailable_slots 
                      WHERE doctor_id = ? AND date = ? 
                      AND ((from_time < ? AND to_time > ?) 
                      OR (from_time < ? AND to_time > ?) 
                      OR (from_time >= ? AND to_time <= ?))";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("isssssss", $doctor_id, $date, $to_time, $to_time, $from_time, $from_time, $from_time, $to_time);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            echo "<script>alert('This time slot overlaps with an existing one.');</script>";
        } else {
            $sql = "INSERT INTO unavailable_slots (doctor_id, date, from_time, to_time) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $doctor_id, $date, $from_time, $to_time);
            if ($stmt->execute()) {
                echo "<script>alert('Unavailable time set successfully!');</script>";
            } else {
                echo "<script>alert('Failed to set unavailable time.');</script>";
            }
        }
    }
}

// 自动删除超过1年的过期记录
if (isset($_GET['doctor_id'])) {
    $doctor_id = $_GET['doctor_id'];
    
    // 删除超过1年的记录
    $one_year_ago = date('Y-m-d', strtotime('-1 year'));
    $cleanup_sql = "DELETE FROM unavailable_slots WHERE doctor_id = ? AND date < ?";
    $cleanup_stmt = $conn->prepare($cleanup_sql);
    $cleanup_stmt->bind_param("is", $doctor_id, $one_year_ago);
    $cleanup_stmt->execute();
    
    // 分页设置
    $limit = 10; // 每页显示10条记录
    $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($current_page - 1) * $limit;
    
    // 计算总记录数（只包含未来1年内的记录）
    $count_sql = "SELECT COUNT(*) as total FROM unavailable_slots WHERE doctor_id = ? AND date >= ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("is", $doctor_id, $one_year_ago);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // 获取当前页的记录（只显示未来1年内的记录）
    $unavailable_sql = "SELECT * FROM unavailable_slots WHERE doctor_id = ? AND date >= ? ORDER BY date, from_time LIMIT ? OFFSET ?";
    $unavailable_stmt = $conn->prepare($unavailable_sql);
    $unavailable_stmt->bind_param("isii", $doctor_id, $one_year_ago, $limit, $offset);
    $unavailable_stmt->execute();
    $unavailable_result = $unavailable_stmt->get_result();
    $unavailable_slots = [];
    while ($row = $unavailable_result->fetch_assoc()) {
        $unavailable_slots[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Doctor Schedule Management | Dental Clinic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --admin-primary: #c0392b;
            --admin-secondary: #e74c3c;
            --admin-dark: #a93226;
            --admin-light: #fadbd8;
            --admin-gradient: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
            --clinic-accent: #2c3e50;
            --clinic-muted: #95a5a6;
            --clinic-light-bg: #f8fafc;
            --clinic-card-shadow: 0 10px 30px rgba(192, 57, 43, 0.15);
            --clinic-border-radius: 16px;
            --clinic-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            line-height: 1.6;
            color: #2c3e50;
        }

        /* Navbar Spacing */
        .main-content {
            padding-top: 100px;
        }

        /* Page Header */
        .page-header {
            background: var(--admin-gradient);
            padding: 60px 0;
            margin-bottom: 50px;
            border-radius: 0 0 50px 50px;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="%23ffffff" opacity="0.1"><path d="M0,0v46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1547.29,35.58,1000,98.74V0Z"/></svg>') repeat-x;
            animation: wave 20s linear infinite;
        }

        @keyframes wave {
            0% { background-position-x: 0; }
            100% { background-position-x: 1000px; }
        }

        .page-header h1 {
            color: white;
            font-size: 2.5rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 15px;
            text-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .page-header .subtitle {
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
            font-size: 1.1rem;
            font-weight: 400;
        }

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Cards */
        .clinic-card {
            background: white;
            border-radius: var(--clinic-border-radius);
            box-shadow: var(--clinic-card-shadow);
            border: none;
            transition: var(--clinic-transition);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .clinic-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(192, 57, 43, 0.25);
        }

        .card-header {
            background: var(--admin-gradient);
            border: none;
            padding: 25px;
            color: white;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-body {
            padding: 30px;
        }

        /* Form Styling */
        .form-label {
            font-weight: 600;
            color: var(--clinic-accent);
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-control, .form-select {
            border: 2px solid #e3e6eb;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 1rem;
            transition: var(--clinic-transition);
            background-color: #fafbfc;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.25rem rgba(192, 57, 43, 0.25);
            background-color: white;
        }

        /* Buttons */
        .btn {
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--clinic-transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-admin {
            background: var(--admin-gradient);
            color: white;
        }

        .btn-admin:hover {
            background: linear-gradient(135deg, #a93226 0%, #c0392b 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(192, 57, 43, 0.4);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            border: none;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #229954 0%, #27ae60 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46, 204, 113, 0.4);
        }

        /* Table Styling */
        .table-container {
            background: white;
            border-radius: var(--clinic-border-radius);
            box-shadow: var(--clinic-card-shadow);
            overflow: hidden;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Custom scrollbar for webkit browsers */
        .table-container::-webkit-scrollbar {
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f8f9fa;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: var(--admin-primary);
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: var(--admin-dark);
        }

        .table {
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 700px;
            width: 100%;
        }

        .table thead th {
            background: var(--admin-gradient);
            color: white;
            border: none;
            padding: 20px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 18px 20px;
            border-top: 1px solid #eef2f7;
            vertical-align: middle;
            font-size: 0.95rem;
            white-space: nowrap;
        }

        /* Table column widths */
        .table th:nth-child(1), .table td:nth-child(1) { min-width: 150px; } /* Date column */
        .table th:nth-child(2), .table td:nth-child(2) { min-width: 120px; } /* Start Time column */
        .table th:nth-child(3), .table td:nth-child(3) { min-width: 120px; } /* End Time column */
        .table th:nth-child(4), .table td:nth-child(4) { min-width: 100px; } /* Duration column */
        .table th:nth-child(5), .table td:nth-child(5) { min-width: 120px; } /* Actions column */

        .table tbody tr {
            transition: var(--clinic-transition);
        }

        .table tbody tr:hover {
            background-color: var(--admin-light);
            transform: scale(1.01);
        }

        /* Search Input */
        .search-container {
            position: relative;
            margin-bottom: 25px;
        }

        .search-container .form-control {
            padding-left: 50px;
            border-radius: 50px;
            border: 2px solid #e3e6eb;
            background: white;
        }

        .search-container .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--clinic-muted);
            font-size: 1.1rem;
        }

        /* Filter Container Styling */
        .filter-container {
            background: var(--admin-light);
            border-radius: var(--clinic-border-radius);
            padding: 25px;
            margin-bottom: 25px;
            border: 2px solid rgba(192, 57, 43, 0.1);
        }

        .filter-container .form-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--admin-primary);
            margin-bottom: 8px;
        }

        .filter-container .form-select,
        .filter-container .form-control {
            border: 2px solid rgba(192, 57, 43, 0.2);
            border-radius: 10px;
            transition: var(--clinic-transition);
        }

        .filter-container .form-select:focus,
        .filter-container .form-control:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.25rem rgba(192, 57, 43, 0.15);
        }

        /* Active Filters Display */
        #activeFilters {
            padding: 15px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            border-left: 4px solid var(--admin-primary);
        }

        #activeFilters .badge {
            font-size: 0.85rem;
            padding: 8px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        #activeFilters .btn-close {
            background: none;
            border: none;
            opacity: 0.7;
            padding: 0;
            margin: 0;
            width: 12px;
            height: 12px;
        }

        #activeFilters .btn-close:hover {
            opacity: 1;
        }

        /* Result Counter */
        #resultCount {
            font-weight: bold;
            font-size: 1.1rem;
        }

        /* Input Group Styling */
        .input-group .input-group-text {
            border: 2px solid rgba(192, 57, 43, 0.2);
            background: rgba(192, 57, 43, 0.1);
            color: var(--admin-primary);
        }

        .input-group .form-control {
            border-left: none;
        }

        .input-group .btn-outline-secondary {
            border-color: rgba(192, 57, 43, 0.2);
            color: var(--admin-primary);
        }

        .input-group .btn-outline-secondary:hover {
            background: var(--admin-primary);
            border-color: var(--admin-primary);
            color: white;
        }

        /* Delete Button */
        .delete-btn {
            color: #e74c3c;
            text-decoration: none;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 8px;
            transition: var(--clinic-transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .delete-btn:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        /* Alert Styling */
        .alert {
            border-radius: var(--clinic-border-radius);
            border: none;
            padding: 20px;
            margin-bottom: 25px;
        }

        .alert-info {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding-top: 80px;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .page-header {
                padding: 40px 0;
                margin-bottom: 30px;
            }

            .card-body {
                padding: 20px;
            }

            .table-container {
                margin: 0 -15px;
                border-radius: 0;
            }

            .table thead th,
            .table tbody td {
                padding: 12px 8px;
                font-size: 0.85rem;
            }

            .btn {
                padding: 10px 20px;
                font-size: 0.9rem;
            }

            /* Adjust table columns for mobile */
            .table th:nth-child(1), .table td:nth-child(1) { min-width: 130px; }
            .table th:nth-child(2), .table td:nth-child(2) { min-width: 100px; }
            .table th:nth-child(3), .table td:nth-child(3) { min-width: 100px; }
            .table th:nth-child(4), .table td:nth-child(4) { min-width: 80px; }
            .table th:nth-child(5), .table td:nth-child(5) { min-width: 100px; }
            
            /* Pagination responsive styles */
            .pagination-nav {
                gap: 0.25rem;
            }
            
            .page-btn {
                padding: 0.4rem 0.6rem;
                font-size: 0.85rem;
                min-width: 38px;
            }
            
            .pagination-container {
                padding: 15px;
                margin-top: 20px;
            }
        }

        /* Loading Animation */
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .spinner-border {
            color: var(--admin-primary);
        }

        /* Pagination Styles */
        .pagination-container {
            background: white;
            border-radius: var(--clinic-border-radius);
            padding: 25px;
            margin-top: 30px;
            box-shadow: var(--clinic-card-shadow);
            border: 1px solid rgba(192, 57, 43, 0.1);
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid rgba(192, 57, 43, 0.2);
            color: var(--admin-primary);
            padding: 0.5rem 0.75rem;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--clinic-transition);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            min-width: 45px;
            justify-content: center;
        }
        
        .page-btn:hover {
            background: var(--admin-primary);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(192, 57, 43, 0.4);
        }
        
        .page-btn.active {
            background: var(--admin-gradient);
            color: #1a202c;
            border-color: var(--admin-primary);
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(192, 57, 43, 0.3);
            text-shadow: none;
        }
        
        .page-btn.disabled {
            background: rgba(255, 255, 255, 0.5);
            color: rgba(107, 114, 128, 0.6);
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-info {
            text-align: center;
            color: var(--clinic-accent);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            padding: 15px;
            background: var(--admin-light);
            border-radius: 10px;
            border-left: 4px solid var(--admin-primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--clinic-muted);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* Flatpickr Custom Styling */
        .flatpickr-calendar {
            border-radius: 16px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
        }

        .flatpickr-day.selected {
            background: var(--admin-primary) !important;
            border-color: var(--admin-primary) !important;
        }

        .flatpickr-day:hover {
            background: var(--admin-light) !important;
        }

        /* Pagination Styles */
        .pagination-container {
            background: white;
            border-radius: var(--clinic-border-radius);
            padding: 25px;
            margin-top: 30px;
            box-shadow: var(--clinic-card-shadow);
            border: 1px solid rgba(192, 57, 43, 0.1);
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid rgba(192, 57, 43, 0.2);
            color: var(--admin-primary);
            padding: 0.5rem 0.75rem;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--clinic-transition);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            min-width: 45px;
            justify-content: center;
        }
        
        .page-btn:hover {
            background: var(--admin-primary);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(192, 57, 43, 0.4);
        }
        
        .page-btn.active {
            background: var(--admin-gradient);
            color: #1a202c;
            border-color: var(--admin-primary);
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(192, 57, 43, 0.3);
            text-shadow: none;
        }
        
        .page-btn.disabled {
            background: rgba(255, 255, 255, 0.5);
            color: rgba(107, 114, 128, 0.6);
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-info {
            text-align: center;
            color: var(--clinic-accent);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            padding: 15px;
            background: var(--admin-light);
            border-radius: 10px;
            border-left: 4px solid var(--admin-primary);
        }
        
        @media (max-width: 768px) {
            .pagination-nav {
                gap: 0.25rem;
            }
            
            .page-btn {
                padding: 0.4rem 0.6rem;
                font-size: 0.85rem;
                min-width: 38px;
            }
            
            .pagination-container {
                padding: 15px;
                margin-top: 20px;
            }
        }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-user-clock"></i> Doctor Schedule Management</h1>
            <p class="subtitle">Manage doctor availability and unavailable time slots</p>
        </div>
    </div>

    <div class="main-container">
        <!-- Doctor Selection Card -->
        <div class="clinic-card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-user-md"></i>
                    Select Doctor
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" id="doctorSelectionForm">
                    <div class="row align-items-end">
                        <div class="col-md-8">
                            <label for="doctor_id" class="form-label">
                                <i class="fas fa-stethoscope"></i> Choose Doctor
                            </label>
                            <select name="doctor_id" id="doctor_id" class="form-select" required>
                                <option value="">-- Select a Doctor --</option>
                                <?php while ($doctor = $doctors_result->fetch_assoc()): ?>
                                    <option value="<?= $doctor['id'] ?>" <?= isset($_GET['doctor_id']) && $_GET['doctor_id'] == $doctor['id'] ? 'selected' : '' ?>>
                                        Dr. <?= htmlspecialchars($doctor['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-admin w-100">
                                <i class="fas fa-search"></i> View Schedule
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($_GET['doctor_id'])): ?>
            <!-- Set Unavailable Time Card -->
            <div class="clinic-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-calendar-times"></i>
                        Set Unavailable Time Slot
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Configure time slots when the selected doctor will be unavailable for appointments.
                    </div>
                    <form method="POST" id="unavailableTimeForm">
                        <input type="hidden" name="doctor_id" value="<?= $_GET['doctor_id'] ?>">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <label for="date" class="form-label">
                                    <i class="fas fa-calendar-alt"></i> Date
                                </label>
                                <input type="text" id="date" name="date" class="form-control" 
                                       placeholder="Select date" required readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="from_time" class="form-label">
                                    <i class="fas fa-clock"></i> Start Time
                                </label>
                                <select id="from_time" name="from_time" class="form-select" required>
                                    <option value="">Select start time</option>
                                    <option value="09:00">09:00 AM</option>
                                    <option value="09:30">09:30 AM</option>
                                    <option value="10:00">10:00 AM</option>
                                    <option value="10:30">10:30 AM</option>
                                    <option value="11:00">11:00 AM</option>
                                    <option value="11:30">11:30 AM</option>
                                    <option value="14:00">02:00 PM</option>
                                    <option value="14:30">02:30 PM</option>
                                    <option value="15:00">03:00 PM</option>
                                    <option value="15:30">03:30 PM</option>
                                    <option value="16:00">04:00 PM</option>
                                    <option value="16:30">04:30 PM</option>
                                    <option value="17:00">05:00 PM</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="to_time" class="form-label">
                                    <i class="fas fa-clock"></i> End Time
                                </label>
                                <select id="to_time" name="to_time" class="form-select" required>
                                    <option value="">Select end time</option>
                                    <option value="09:30">09:30 AM</option>
                                    <option value="10:00">10:00 AM</option>
                                    <option value="10:30">10:30 AM</option>
                                    <option value="11:00">11:00 AM</option>
                                    <option value="11:30">11:30 AM</option>
                                    <option value="14:30">02:30 PM</option>
                                    <option value="15:00">03:00 PM</option>
                                    <option value="15:30">03:30 PM</option>
                                    <option value="16:00">04:00 PM</option>
                                    <option value="16:30">04:30 PM</option>
                                    <option value="17:00">05:00 PM</option>
                                    <option value="17:30">05:30 PM</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex gap-3 mt-4">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus-circle"></i> Add Unavailable Slot
                            </button>
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-undo"></i> Reset Form
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Existing Unavailable Slots -->
            <div class="clinic-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-list-alt"></i>
                        Current Unavailable Time Slots
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Filter Controls -->
                    <div class="filter-container mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">
                                    <i class="fas fa-calendar-alt"></i> Filter by Date
                                </label>
                                <select id="dateFilter" class="form-select">
                                    <option value="">All Dates</option>
                                    <option value="today">Today</option>
                                    <option value="tomorrow">Tomorrow</option>
                                    <option value="this_week">This Week</option>
                                    <option value="this_month">This Month</option>
                                    <option value="upcoming">Upcoming</option>
                                    <option value="past">Past</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">
                                    <i class="fas fa-clock"></i> Filter by Time Period
                                </label>
                                <select id="timeFilter" class="form-select">
                                    <option value="">All Times</option>
                                    <option value="morning">Morning (9AM - 12PM)</option>
                                    <option value="afternoon">Afternoon (2PM - 6PM)</option>
                                    <option value="short">Short (≤ 1 hour)</option>
                                    <option value="long">Long (> 1 hour)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">
                                    <i class="fas fa-search"></i> Quick Search
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" id="quickSearch" class="form-control" 
                                           placeholder="Search keywords...">
                                    <button class="btn btn-outline-secondary" type="button" id="clearFilters">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Active Filters Display -->
                        <div id="activeFilters" class="mt-3" style="display: none;">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="text-muted">Active filters:</span>
                                <div id="filterTags"></div>
                            </div>
                        </div>
                        
                        <!-- Results Counter -->
                        <div class="mt-3">
                            <small class="text-muted">
                                Showing <span id="resultCount">0</span> slot(s)
                            </small>
                        </div>
                    </div>

                    <!-- Loading Spinner -->
                    <div class="loading-spinner" id="loadingSpinner">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>

                    <!-- Slots Table -->
                    <?php if (!empty($unavailable_slots)): ?>
                        <div class="table-container">
                            <table class="table table-hover" id="slotsTable">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-calendar"></i> Date</th>
                                        <th><i class="fas fa-play"></i> Start Time</th>
                                        <th><i class="fas fa-stop"></i> End Time</th>
                                        <th><i class="fas fa-clock"></i> Duration</th>
                                        <th><i class="fas fa-cogs"></i> Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unavailable_slots as $slot): ?>
                                        <tr>
                                            <td>
                                                <strong><?= date('M d, Y', strtotime($slot['date'])) ?></strong>
                                                <br><small class="text-muted"><?= date('l', strtotime($slot['date'])) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?= date('h:i A', strtotime($slot['from_time'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= date('h:i A', strtotime($slot['to_time'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                    $start = strtotime($slot['from_time']);
                                                    $end = strtotime($slot['to_time']);
                                                    $duration = ($end - $start) / 60; // in minutes
                                                    $hours = floor($duration / 60);
                                                    $mins = $duration % 60;
                                                    echo ($hours > 0 ? $hours . 'h ' : '') . ($mins > 0 ? $mins . 'm' : '');
                                                ?>
                                            </td>
                                            <td>
                                                <a class="delete-btn" 
                                                   href="?delete_id=<?= $slot['id'] ?>&doctor_id=<?= $_GET['doctor_id'] ?>&page=<?= $current_page ?>" 
                                                   onclick="return confirm('Are you sure you want to delete this unavailable time slot?');">
                                                    <i class="fas fa-trash-alt"></i> Remove
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination Navigation -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <div class="pagination-info">
                                <i class="fas fa-info-circle"></i>
                                Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_records) ?> of <?= number_format($total_records) ?> unavailable slots
                            </div>
                            
                            <div class="pagination-nav">
                                <!-- Previous Page -->
                                <?php if ($current_page > 1): ?>
                                    <a href="?doctor_id=<?= $_GET['doctor_id'] ?>&page=<?= $current_page - 1 ?>" class="page-btn">
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
                                    echo '<a href="?doctor_id=' . $_GET['doctor_id'] . '&page=1" class="page-btn">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span class="page-btn disabled">...</span>';
                                    }
                                }
                                
                                // Show page numbers in range
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    if ($i == $current_page) {
                                        echo '<span class="page-btn active">' . $i . '</span>';
                                    } else {
                                        echo '<a href="?doctor_id=' . $_GET['doctor_id'] . '&page=' . $i . '" class="page-btn">' . $i . '</a>';
                                    }
                                }
                                
                                // Show last page if not in range
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="page-btn disabled">...</span>';
                                    }
                                    echo '<a href="?doctor_id=' . $_GET['doctor_id'] . '&page=' . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
                                }
                                ?>
                                
                                <!-- Next Page -->
                                <?php if ($current_page < $total_pages): ?>
                                    <a href="?doctor_id=<?= $_GET['doctor_id'] ?>&page=<?= $current_page + 1 ?>" class="page-btn">
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
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <h4>No Unavailable Slots Found</h4>
                            <p>This doctor currently has no unavailable time slots configured.</p>
                            <small class="text-muted mt-2 d-block">
                                <i class="fas fa-info-circle"></i>
                                Note: Records older than 1 year are automatically removed for better performance.
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Initialize Flatpickr for date selection
flatpickr("#date", {
    dateFormat: "Y-m-d",
    minDate: "today",
    theme: "material_red",
    disable: [
        function(date) {
            // Disable Sundays (0 = Sunday)
            return date.getDay() === 0;
        }
    ],
    locale: {
        firstDayOfWeek: 1 // Start week on Monday
    },
    onChange: function(selectedDates, dateStr, instance) {
        // Add visual feedback when date is selected
        const dateInput = document.getElementById('date');
        dateInput.style.borderColor = 'var(--admin-primary)';
        dateInput.style.boxShadow = '0 0 0 0.25rem rgba(192, 57, 43, 0.25)';
        
        setTimeout(() => {
            dateInput.style.borderColor = '';
            dateInput.style.boxShadow = '';
        }, 2000);
    }
});

// Enhanced Filter Functionality
const dateFilter = document.getElementById('dateFilter');
const timeFilter = document.getElementById('timeFilter');
const quickSearch = document.getElementById('quickSearch');
const clearFiltersBtn = document.getElementById('clearFilters');
const activeFiltersDiv = document.getElementById('activeFilters');
const filterTagsDiv = document.getElementById('filterTags');
const resultCountSpan = document.getElementById('resultCount');

if (dateFilter && timeFilter && quickSearch) {
    // Add event listeners for all filters
    dateFilter.addEventListener('change', applyFilters);
    timeFilter.addEventListener('change', applyFilters);
    quickSearch.addEventListener('keyup', debounce(applyFilters, 300));
    
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', clearAllFilters);
    }
    
    // Initial count
    updateResultCount();
}

function applyFilters() {
    const table = document.getElementById('slotsTable');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    const dateFilterValue = dateFilter.value;
    const timeFilterValue = timeFilter.value;
    const searchValue = quickSearch.value.toLowerCase();
    
    let visibleCount = 0;
    
    rows.forEach((row, index) => {
        let showRow = true;
        
        // Get row data
        const dateCell = row.cells[0].textContent;
        const startTimeCell = row.cells[1].textContent;
        const endTimeCell = row.cells[2].textContent;
        const durationCell = row.cells[3].textContent;
        
        // Parse date for date filtering
        const rowDate = new Date(row.cells[0].querySelector('strong').textContent);
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        // Apply date filter
        if (dateFilterValue && showRow) {
            switch (dateFilterValue) {
                case 'today':
                    showRow = isSameDay(rowDate, today);
                    break;
                case 'tomorrow':
                    showRow = isSameDay(rowDate, tomorrow);
                    break;
                case 'this_week':
                    showRow = isThisWeek(rowDate);
                    break;
                case 'this_month':
                    showRow = isThisMonth(rowDate);
                    break;
                case 'upcoming':
                    showRow = rowDate >= today;
                    break;
                case 'past':
                    showRow = rowDate < today;
                    break;
            }
        }
        
        // Apply time filter
        if (timeFilterValue && showRow) {
            const startTime = parseTime(startTimeCell);
            const duration = parseDuration(durationCell);
            
            switch (timeFilterValue) {
                case 'morning':
                    showRow = startTime >= 9 && startTime < 12;
                    break;
                case 'afternoon':
                    showRow = startTime >= 14 && startTime < 18;
                    break;
                case 'short':
                    showRow = duration <= 60;
                    break;
                case 'long':
                    showRow = duration > 60;
                    break;
            }
        }
        
        // Apply quick search
        if (searchValue && showRow) {
            const searchableText = (dateCell + ' ' + startTimeCell + ' ' + endTimeCell + ' ' + durationCell).toLowerCase();
            showRow = searchableText.includes(searchValue);
        }
        
        // Show/hide row with animation
        if (showRow) {
            row.style.display = '';
            row.style.animation = 'fadeIn 0.3s ease-in';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    updateResultCount(visibleCount);
    updateActiveFilters();
    updateEmptyState(visibleCount === 0);
}

function clearAllFilters() {
    dateFilter.value = '';
    timeFilter.value = '';
    quickSearch.value = '';
    
    // Add visual feedback
    [dateFilter, timeFilter, quickSearch].forEach(element => {
        element.style.borderColor = '#28a745';
        element.style.boxShadow = '0 0 0 0.25rem rgba(40, 167, 69, 0.25)';
        setTimeout(() => {
            element.style.borderColor = '';
            element.style.boxShadow = '';
        }, 1000);
    });
    
    applyFilters();
    showToast('All filters cleared', 'success');
}

function updateActiveFilters() {
    const activeFilters = [];
    
    if (dateFilter.value) {
        const selectedOption = dateFilter.options[dateFilter.selectedIndex];
        activeFilters.push({
            type: 'date',
            label: selectedOption.text,
            value: dateFilter.value
        });
    }
    
    if (timeFilter.value) {
        const selectedOption = timeFilter.options[timeFilter.selectedIndex];
        activeFilters.push({
            type: 'time',
            label: selectedOption.text,
            value: timeFilter.value
        });
    }
    
    if (quickSearch.value) {
        activeFilters.push({
            type: 'search',
            label: `"${quickSearch.value}"`,
            value: quickSearch.value
        });
    }
    
    if (activeFilters.length > 0) {
        activeFiltersDiv.style.display = 'block';
        filterTagsDiv.innerHTML = activeFilters.map(filter => `
            <span class="badge bg-primary me-2 mb-1">
                <i class="fas fa-${getFilterIcon(filter.type)}"></i>
                ${filter.label}
                <button class="btn-close btn-close-white ms-1" 
                        onclick="removeFilter('${filter.type}', '${filter.value}')"
                        style="font-size: 0.7em;"></button>
            </span>
        `).join('');
    } else {
        activeFiltersDiv.style.display = 'none';
    }
}

function removeFilter(type, value) {
    switch (type) {
        case 'date':
            dateFilter.value = '';
            break;
        case 'time':
            timeFilter.value = '';
            break;
        case 'search':
            quickSearch.value = '';
            break;
    }
    applyFilters();
}

function getFilterIcon(type) {
    const icons = {
        'date': 'calendar-alt',
        'time': 'clock',
        'search': 'search'
    };
    return icons[type] || 'filter';
}

function updateResultCount(count) {
    const table = document.getElementById('slotsTable');
    if (!table) return;
    
    if (count === undefined) {
        const rows = table.querySelectorAll('tbody tr');
        count = Array.from(rows).filter(row => row.style.display !== 'none').length;
    }
    
    if (resultCountSpan) {
        resultCountSpan.textContent = count;
        resultCountSpan.style.fontWeight = 'bold';
        resultCountSpan.style.color = count === 0 ? '#e74c3c' : '#27ae60';
    }
}

// Helper functions
function isSameDay(date1, date2) {
    return date1.getDate() === date2.getDate() &&
           date1.getMonth() === date2.getMonth() &&
           date1.getFullYear() === date2.getFullYear();
}

function isThisWeek(date) {
    const today = new Date();
    const startOfWeek = new Date(today);
    startOfWeek.setDate(today.getDate() - today.getDay() + 1); // Monday
    const endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(startOfWeek.getDate() + 6); // Sunday
    
    return date >= startOfWeek && date <= endOfWeek;
}

function isThisMonth(date) {
    const today = new Date();
    return date.getMonth() === today.getMonth() &&
           date.getFullYear() === today.getFullYear();
}

function parseTime(timeString) {
    // Extract time from badge text like "09:00 AM"
    const match = timeString.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
    if (!match) return 0;
    
    let hours = parseInt(match[1]);
    const minutes = parseInt(match[2]);
    const ampm = match[3].toUpperCase();
    
    if (ampm === 'PM' && hours !== 12) hours += 12;
    if (ampm === 'AM' && hours === 12) hours = 0;
    
    return hours + (minutes / 60);
}

function parseDuration(durationString) {
    // Parse duration like "1h 30m" or "30m"
    let totalMinutes = 0;
    const hourMatch = durationString.match(/(\d+)h/);
    const minuteMatch = durationString.match(/(\d+)m/);
    
    if (hourMatch) totalMinutes += parseInt(hourMatch[1]) * 60;
    if (minuteMatch) totalMinutes += parseInt(minuteMatch[1]);
    
    return totalMinutes;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Enhanced Search Functionality (keeping for compatibility)
// Update Empty State
function updateEmptyState(show) {
    let emptyState = document.querySelector('.filter-empty-state');
    
    if (show && !emptyState) {
        emptyState = document.createElement('div');
        emptyState.className = 'filter-empty-state empty-state';
        emptyState.innerHTML = `
            <i class="fas fa-filter"></i>
            <h4>No Results Found</h4>
            <p>No unavailable slots match your current filter criteria.</p>
            <button class="btn btn-outline-primary" onclick="clearAllFilters()">
                <i class="fas fa-times"></i> Clear All Filters
            </button>
        `;
        
        const tableContainer = document.querySelector('.table-container');
        if (tableContainer) {
            tableContainer.style.display = 'none';
            tableContainer.parentNode.appendChild(emptyState);
        }
    } else if (!show && emptyState) {
        emptyState.remove();
        const tableContainer = document.querySelector('.table-container');
        if (tableContainer) {
            tableContainer.style.display = 'block';
        }
    }
}

// Form Validation and Enhancement
const unavailableForm = document.getElementById('unavailableTimeForm');
if (unavailableForm) {
    unavailableForm.addEventListener('submit', function(e) {
        const fromTime = document.getElementById('from_time').value;
        const toTime = document.getElementById('to_time').value;
        const date = document.getElementById('date').value;
        
        // Validate time range
        if (fromTime >= toTime) {
            e.preventDefault();
            showToast('End time must be later than start time.', 'error');
            return false;
        }
        
        // Validate date
        if (!date) {
            e.preventDefault();
            showToast('Please select a date.', 'error');
            return false;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Slot...';
        submitBtn.disabled = true;
        
        // Re-enable button after 3 seconds (in case of page not redirecting)
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 3000);
    });
}

// Doctor Selection Enhancement
const doctorSelect = document.getElementById('doctor_id');
if (doctorSelect) {
    doctorSelect.addEventListener('change', function() {
        if (this.value) {
            // Add visual feedback
            this.style.borderColor = 'var(--admin-primary)';
            this.style.boxShadow = '0 0 0 0.25rem rgba(192, 57, 43, 0.25)';
        }
    });
}

// Time Selection Logic
const fromTimeSelect = document.getElementById('from_time');
const toTimeSelect = document.getElementById('to_time');

if (fromTimeSelect && toTimeSelect) {
    fromTimeSelect.addEventListener('change', function() {
        const selectedFrom = this.value;
        const toOptions = toTimeSelect.querySelectorAll('option');
        
        // Enable/disable end time options based on start time
        toOptions.forEach(option => {
            if (option.value && option.value <= selectedFrom) {
                option.disabled = true;
                option.style.color = '#ccc';
            } else {
                option.disabled = false;
                option.style.color = '';
            }
        });
        
        // Reset end time if it's now invalid
        if (toTimeSelect.value && toTimeSelect.value <= selectedFrom) {
            toTimeSelect.value = '';
        }
    });
}

// Toast Notification System
function showToast(message, type = 'info') {
    // Remove existing toasts
    const existingToast = document.querySelector('.custom-toast');
    if (existingToast) {
        existingToast.remove();
    }
    
    const toast = document.createElement('div');
    toast.className = `custom-toast alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'}`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        animation: slideInRight 0.3s ease-out;
        border-radius: 12px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    `;
    
    const icon = type === 'error' ? 'fas fa-exclamation-circle' : 
                type === 'success' ? 'fas fa-check-circle' : 'fas fa-info-circle';
    
    toast.innerHTML = `
        <i class="${icon}"></i>
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .table tbody tr {
        animation: fadeIn 0.3s ease-in;
    }
`;
document.head.appendChild(style);

// Initialize tooltips
if (typeof bootstrap !== 'undefined') {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Pagination enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scroll to top when changing pages
    const pageLinks = document.querySelectorAll('.page-btn:not(.disabled)');
    pageLinks.forEach(link => {
        if (link.tagName === 'A') {
            link.addEventListener('click', function() {
                // Show loading state
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                this.style.pointerEvents = 'none';
                
                // Scroll to top smoothly
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }
    });
    
    // Add visual feedback for current page
    const activePage = document.querySelector('.page-btn.active');
    if (activePage) {
        activePage.style.animation = 'pulse 1s ease-in-out';
    }
    
    // Show notification about auto-cleanup feature
    <?php if (isset($_GET['doctor_id']) && $total_records >= 0): ?>
    setTimeout(() => {
        showToast('Auto-cleanup feature is active: Records older than 1 year are automatically removed.', 'info');
    }, 2000);
    <?php endif; ?>
});

// Add pulse animation
const pulseStyle = document.createElement('style');
pulseStyle.textContent = `
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
`;
document.head.appendChild(pulseStyle);
</script>
</body>
</html>
