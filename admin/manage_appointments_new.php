<?php
session_start();
include '../includes/db.php';

// 检查管理员是否登录
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Page-specific variables for navbar
$page_title = "Manage Appointments";

$doctor_list = $conn->query("SELECT id, name, specialty FROM doctors ORDER BY name ASC");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_doctor'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $doctor_id = intval($_POST['doctor_id']);

    // 檢查日期時間是否已過
    $check_sql = "SELECT appointment_date, appointment_time FROM appointments WHERE id = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("i", $appointment_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $appt = $result_check->fetch_assoc();

    if ($appt) {
        $appt_datetime = strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']);
        if ($appt_datetime < time()) {
            echo "<script>
                    alert('Cannot assign doctor: The appointment time has already passed.');
                    window.location.href='manage_appointments.php';
                  </script>";
            exit;
        }
    }

    $sql = "UPDATE appointments SET doctor_id = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $doctor_id, $appointment_id);

    if ($stmt->execute()) {
        $admin_id = $_SESSION['admin_id'];
        $action = "Assigned doctor ID: $doctor_id to appointment ID: $appointment_id";
        $conn->query("INSERT INTO user_logs (admin_id, action) VALUES ('$admin_id', '$action')");

        echo "<script>alert('Doctor assigned successfully!'); window.location.href='manage_appointments.php';</script>";
        exit;
    } else {
        echo "<script>alert('Failed to assign doctor.');</script>";
    }
}

// 处理状态更新
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $new_status = trim($_POST['status']);
    
    // 允許的狀態
    $allowed_statuses = ['pending', 'confirmed', 'cancelled_by_patient', 'cancelled_by_admin'];
    if (!in_array($new_status, $allowed_statuses)) {
        echo "<script>alert('Invalid status selected: " . htmlspecialchars($new_status) . "'); window.location.href='manage_appointments.php';</script>";
        exit;
    }

    // 檢查 appointment 的日期時間
    $check_sql = "SELECT status, doctor_id, appointment_date, appointment_time 
                  FROM appointments WHERE id = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("i", $appointment_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $appointment = $result_check->fetch_assoc();

    if (!$appointment) {
        echo "<script>alert('Appointment not found!'); window.location.href='manage_appointments.php';</script>";
        exit;
    }

    // 檢查是否為過期預約
    if ($appointment) {
        $appt_datetime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
        if ($appt_datetime < time()) {
            echo "<script>
                    alert('Cannot update status: The appointment time has already passed.');
                    window.location.href='manage_appointments.php';
                  </script>";
            exit;
        }
    }

    // 如果沒有分配 doctor 且要確認預約
    if (empty($appointment['doctor_id']) && $new_status === 'confirmed') {
        echo "<script>
                alert('Cannot confirm appointment: Please assign a doctor first!');
                window.location.href='manage_appointments.php';
              </script>";
        exit;
    }

    // 執行更新
    $update_sql = "UPDATE appointments SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $new_status, $appointment_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // 记录管理操作
            $admin_id = $_SESSION['admin_id'];
            $action = "Updated appointment ID: $appointment_id to status: $new_status";
            $conn->query("INSERT INTO user_logs (admin_id, action) VALUES ('$admin_id', '$action')");

            // 查詢 email
            $email_sql = "SELECT COALESCE(u.email, a.patient_email) AS email
                          FROM appointments a
                          LEFT JOIN users u ON a.patient_id = u.id
                          WHERE a.id = ?";
            $stmt = $conn->prepare($email_sql);
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $appointment_data = $result->fetch_assoc();
            $email = $appointment_data['email'] ?? '';

            if (!empty($email)) {
                echo "<script>
                        alert('Appointment status updated successfully to: " . htmlspecialchars($new_status) . ". Please check your email client to notify the patient.');
                        window.location.href = 'mailto:$email?subject=Reply from Green Life Dental Clinic';
                        setTimeout(function(){
                            window.location.href = 'manage_appointments.php';
                        }, 5000);
                      </script>";
            } else {
                echo "<script>
                        alert('Appointment status updated successfully to: " . htmlspecialchars($new_status) . "');
                        window.location.href = 'manage_appointments.php';
                      </script>";
            }
            exit;
        } else {
            echo "<script>alert('Status update failed: No changes were made. Current status may already be: " . htmlspecialchars($new_status) . "'); window.location.href='manage_appointments.php';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Failed to update appointment status. Database error.'); window.location.href='manage_appointments.php';</script>";
        exit;
    }
}

// 处理自定义日期
if (isset($_GET['custom_date']) && !empty($_GET['custom_date'])) {
    $date_filter = $_GET['custom_date'];
}

// 处理搜索
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$date_filter = isset($_GET['date_filter']) ? trim($_GET['date_filter']) : '';

// 分頁設定
$appointments_per_page = 15;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $appointments_per_page;

$sql = "
    SELECT a.id, 
           COALESCE(p.name, a.patient_name, '-') AS patient,
           COALESCE(a.patient_phone, p.phone, '-') AS patient_phone,
           COALESCE(a.patient_email, p.email, '-') AS final_email,
           d.name AS doctor, 
           a.appointment_date, 
           a.appointment_time, 
           a.status, 
           a.created_at,
           a.message
    FROM appointments a
    LEFT JOIN users p ON a.patient_id = p.id AND p.role = 'patient'
    LEFT JOIN doctors d ON a.doctor_id = d.id
";

$conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $conditions[] = "(p.name LIKE ? OR a.patient_name LIKE ? OR d.name LIKE ? OR a.status LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= 'ssss';
}

if (!empty($date_filter)) {
    if ($date_filter === 'today') {
        $conditions[] = "a.appointment_date = CURDATE()";
    } elseif ($date_filter === 'tomorrow') {
        $conditions[] = "a.appointment_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
    } elseif ($date_filter === 'this_week') {
        $conditions[] = "YEARWEEK(a.appointment_date) = YEARWEEK(CURDATE())";
    } elseif ($date_filter === 'this_month') {
        $conditions[] = "YEAR(a.appointment_date) = YEAR(CURDATE()) AND MONTH(a.appointment_date) = MONTH(CURDATE())";
    } elseif ($date_filter === 'upcoming') {
        $conditions[] = "a.appointment_date >= CURDATE()";
    } elseif ($date_filter === 'past') {
        $conditions[] = "a.appointment_date < CURDATE()";
    } else {
        $conditions[] = "a.appointment_date = ?";
        $params[] = $date_filter;
        $param_types .= 's';
    }
}

// 計算總預約數
$count_sql = "SELECT COUNT(*) as total FROM appointments a LEFT JOIN users p ON a.patient_id = p.id AND p.role = 'patient' LEFT JOIN doctors d ON a.doctor_id = d.id";
if (!empty($conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $conditions);
}

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_appointments = $total_result->fetch_assoc()['total'];
$count_stmt->close();

// 計算分頁
$total_pages = ceil($total_appointments / $appointments_per_page);
$start_appointment = ($current_page - 1) * $appointments_per_page + 1;
$end_appointment = min($current_page * $appointments_per_page, $total_appointments);

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

$all_params = $params;
$all_params[] = $appointments_per_page;
$all_params[] = $offset;
$all_param_types = $param_types . 'ii';

if (!empty($all_params)) {
    $stmt->bind_param($all_param_types, ...$all_params);
}
$stmt->execute();
$appointments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 Manage All Appointments - Green Life Dental Clinic</title>
    
    <!-- Bootstrap and Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        /* Simple Header */
        .simple-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #059669 100%);
            padding: 1rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .header-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4f46e5, #059669);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .user-details h6 {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
        }
        
        .user-details small {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.75rem;
        }
        
        .logout-btn {
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .logout-btn:hover {
            background: rgba(220, 38, 38, 0.9);
            color: white;
            transform: translateY(-1px);
            text-decoration: none;
        }
        
        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .page-title-section {
            background: linear-gradient(135deg, #4f46e5 0%, #059669 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .page-title-section h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .page-title-section p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        /* Filter Section */
        .filter-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .filter-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Table Section */
        .table-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        
        .table {
            margin-bottom: 0;
            min-width: 1200px;
        }
        
        .table th {
            background: linear-gradient(135deg, #4f46e5 0%, #059669 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            border: none;
            text-align: center;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #f3f4f6;
            text-align: center;
            font-size: 0.875rem;
        }
        
        .table tbody tr:hover {
            background-color: #f8fafc;
        }
        
        /* Status Badges */
        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .bg-success {
            background-color: #059669 !important;
            color: white !important;
        }
        
        .bg-danger {
            background-color: #dc2626 !important;
            color: white !important;
        }
        
        .bg-warning {
            background-color: #d97706 !important;
            color: white !important;
        }
        
        .bg-dark {
            background-color: #6b7280 !important;
            color: white !important;
        }
        
        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.75rem;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary { background-color: #4f46e5; color: white; }
        .btn-success { background-color: #059669; color: white; }
        .btn-danger { background-color: #dc2626; color: white; }
        .btn-warning { background-color: #d97706; color: white; }
        .btn-secondary { background-color: #6b7280; color: white; }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.7rem;
        }
        
        /* Form Controls */
        .form-control, .form-select {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .form-select-sm {
            padding: 0.375rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 0.375rem;
        }
        
        /* Pagination */
        .pagination-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            background: white;
            border: 1px solid #d1d5db;
            color: #374151;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            min-width: 40px;
            justify-content: center;
        }
        
        .page-btn:hover {
            background-color: #f9fafb;
            border-color: #4f46e5;
            color: #4f46e5;
            text-decoration: none;
        }
        
        .page-btn.active {
            background-color: #4f46e5;
            color: white;
            border-color: #4f46e5;
            font-weight: 600;
        }
        
        .page-btn.disabled {
            background: #f9fafb;
            color: #9ca3af;
            cursor: not-allowed;
            pointer-events: none;
            border-color: #e5e7eb;
        }
        
        .pagination-info {
            text-align: center;
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 1rem 0;
            }
            
            .main-container {
                padding: 0 1rem;
            }
            
            .header-container {
                padding: 0 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .page-title-section h1 {
                font-size: 1.75rem;
            }
            
            .filter-section, .table-section {
                padding: 1rem;
            }
            
            .table {
                min-width: 800px;
            }
            
            .btn {
                font-size: 0.7rem;
                padding: 0.375rem 0.75rem;
            }
            
            .form-control, .form-select {
                padding: 0.5rem;
                font-size: 0.875rem;
            }
        }
    </style>
</head>

<body>
    <!-- Simple Header -->
    <header class="simple-header">
        <div class="header-container">
            <div class="header-brand">
                <i class="fas fa-tooth text-white" style="font-size: 2rem;"></i>
                <div>
                    <h4 style="margin: 0; color: white; font-weight: 700;">Green Life Dental</h4>
                    <small style="color: rgba(255,255,255,0.8);">Admin Dashboard</small>
                </div>
            </div>
            
            <div class="header-user">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h6><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator'); ?></h6>
                        <small>Administrator</small>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Page Title Section -->
        <div class="page-title-section">
            <h1><i class="fas fa-calendar-check me-3"></i>Manage Appointments</h1>
            <p>View, filter, and manage all dental clinic appointments efficiently</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <h5 class="filter-title"><i class="fas fa-filter me-2"></i>Filter Appointments</h5>
            <form method="GET" action="" class="row">
                <div class="col-md-3 mb-3">
                    <label for="date_filter" class="form-label">Date Filter</label>
                    <select name="date_filter" id="date_filter" class="form-select">
                        <option value="">All Dates</option>
                        <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>📍 Today</option>
                        <option value="tomorrow" <?= $date_filter === 'tomorrow' ? 'selected' : '' ?>>⏭️ Tomorrow</option>
                        <option value="this_week" <?= $date_filter === 'this_week' ? 'selected' : '' ?>>📅 This Week</option>
                        <option value="this_month" <?= $date_filter === 'this_month' ? 'selected' : '' ?>>📆 This Month</option>
                        <option value="upcoming" <?= $date_filter === 'upcoming' ? 'selected' : '' ?>>🔮 Upcoming</option>
                        <option value="past" <?= $date_filter === 'past' ? 'selected' : '' ?>>📋 Past</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="custom_date" class="form-label">Custom Date</label>
                    <input type="date" name="custom_date" id="custom_date" class="form-control"
                           value="<?= !empty($date_filter) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_filter) ? htmlspecialchars($date_filter) : '' ?>"
                           onchange="this.form.submit()">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" placeholder="Search by patient, doctor, or status..."
                           value="<?= htmlspecialchars($search) ?>" class="form-control">
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Appointments Table -->
        <div class="table-section">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    All Appointments (<?= $total_appointments ?> total)
                </h5>
                <small class="text-muted">
                    Showing <?= $start_appointment ?>-<?= $end_appointment ?> of <?= $total_appointments ?>
                </small>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                            <th>Contact</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($appointments && $appointments->num_rows > 0): ?>
                            <?php while ($row = $appointments->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['id']); ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['patient'] ?: '-'); ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($row['patient_phone'] ?: '-'); ?></td>
                                    <td>
                                        <?php if (!empty($row['final_email'])): ?>
                                            <a href="mailto:<?= htmlspecialchars($row['final_email']); ?>">
                                                <?= htmlspecialchars($row['final_email']); ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['doctor'] ?: 'Unassigned'); ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($row['appointment_date']); ?></td>
                                    <td><?= htmlspecialchars($row['appointment_time']); ?></td>
                                    <td><?= htmlspecialchars($row['message'] ?: '-'); ?></td>
                                    <td>
                                        <?php
                                        $status = $row['status'];
                                        $badge_class = 'bg-dark';
                                        switch ($status) {
                                            case 'confirmed':
                                                $badge_class = 'bg-success';
                                                break;
                                            case 'cancelled_by_patient':
                                            case 'cancelled_by_admin':
                                                $badge_class = 'bg-danger';
                                                break;
                                            case 'pending':
                                                $badge_class = 'bg-warning';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?= $badge_class; ?>">
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $status))); ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['created_at']); ?></td>
                                    <td>
                                        <div class="d-flex flex-column gap-2">
                                            <!-- Assign Doctor Form -->
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="appointment_id" value="<?= $row['id']; ?>">
                                                <select name="doctor_id" class="form-select form-select-sm" required>
                                                    <option value="">Select Doctor</option>
                                                    <?php 
                                                    $doctor_list->data_seek(0);
                                                    while ($doctor = $doctor_list->fetch_assoc()): ?>
                                                        <option value="<?= $doctor['id']; ?>">
                                                            <?= htmlspecialchars($doctor['name']); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                                <button type="submit" name="assign_doctor" class="btn btn-success btn-sm mt-1 w-100">
                                                    <i class="fas fa-user-md me-1"></i>Assign
                                                </button>
                                            </form>

                                            <!-- Update Status Form -->
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="appointment_id" value="<?= $row['id']; ?>">
                                                <select name="status" class="form-select form-select-sm" required>
                                                    <option value="">Update Status</option>
                                                    <option value="pending">Pending</option>
                                                    <option value="confirmed">Confirmed</option>
                                                    <option value="cancelled_by_admin">Cancel</option>
                                                </select>
                                                <button type="submit" name="update_status" class="btn btn-warning btn-sm mt-1 w-100">
                                                    <i class="fas fa-edit me-1"></i>Update
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['final_email'])): ?>
                                            <a href="mailto:<?= htmlspecialchars($row['final_email']); ?>?subject=Reply from Green Life Dental Clinic" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-envelope me-1"></i>Email
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No email</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="text-center py-4">
                                    <i class="fas fa-calendar-times text-muted me-2"></i>
                                    No appointments found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Page <?= $current_page ?> of <?= $total_pages ?> 
                    (<?= $total_appointments ?> total appointments)
                </div>
                <div class="pagination-nav">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date_filter=' . urlencode($date_filter) : '' ?>" 
                           class="page-btn">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?= $current_page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date_filter=' . urlencode($date_filter) : '' ?>" 
                           class="page-btn">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-btn disabled">
                            <i class="fas fa-angle-double-left"></i>
                        </span>
                        <span class="page-btn disabled">
                            <i class="fas fa-angle-left"></i>
                        </span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i == $current_page): ?>
                            <span class="page-btn active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date_filter=' . urlencode($date_filter) : '' ?>" 
                               class="page-btn"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?= $current_page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date_filter=' . urlencode($date_filter) : '' ?>" 
                           class="page-btn">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date_filter=' . urlencode($date_filter) : '' ?>" 
                           class="page-btn">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-btn disabled">
                            <i class="fas fa-angle-right"></i>
                        </span>
                        <span class="page-btn disabled">
                            <i class="fas fa-angle-double-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>