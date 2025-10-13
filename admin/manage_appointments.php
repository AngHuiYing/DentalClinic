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
    $appointment_id = intval($_POST['appointment_id']); // 确保是整数
    $new_status = trim($_POST['status']); // 去除空格
    
    // 调试信息
    error_log("Update Status Debug - Appointment ID: " . $appointment_id . ", New Status: " . $new_status);

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

    // 调试：显示当前预约信息
    error_log("Current appointment status: " . $appointment['status'] . ", Doctor ID: " . $appointment['doctor_id']);

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
        // 檢查是否真的更新了
        if ($stmt->affected_rows > 0) {
            error_log("Status update successful - Affected rows: " . $stmt->affected_rows);
            
            // 记录管理操作
            $admin_id = $_SESSION['admin_id'];
            $action = "Updated appointment ID: $appointment_id to status: $new_status";
            $conn->query("INSERT INTO user_logs (admin_id, action) VALUES ('$admin_id', '$action')");

            // 查詢 email（先從 users 表，再 fallback 到 appointments.patient_email）
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

            // 有 email → mailto + 5秒後返回
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
            error_log("Status update failed - No rows affected");
            echo "<script>alert('Status update failed: No changes were made. Current status may already be: " . htmlspecialchars($new_status) . "'); window.location.href='manage_appointments.php';</script>";
            exit;
        }
    } else {
        error_log("Status update failed - SQL Error: " . $stmt->error);
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
$appointments_per_page = 15; // 每頁顯示預約數
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
        // Custom date format (YYYY-MM-DD)
        $conditions[] = "a.appointment_date = ?";
        $params[] = $date_filter;
        $param_types .= 's';
    }
}

// 先計算總預約數
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

// 準備參數，加上分頁參數
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
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }
        
        .simple-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #059669 100%);
            padding: 1rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .header-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }
        
        .header-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info {
            background: rgba(255,255,255,0.1);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            color: white;
            font-size: 0.9rem;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(220,38,38,0.8);
            color: white;
            text-decoration: none;
        }

        /* CSS Variables */
        :root {
            --info-color: #0284c7;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            
            /* Clear button colors */
            --btn-primary: #4f46e5;
            --btn-success: #059669;
            --btn-danger: #dc2626;
            --btn-warning: #d97706;
            --btn-secondary: #6b7280;
            --btn-info: #0284c7;
            
            /* Legacy variables for compatibility */
            --primary-gradient: linear-gradient(135deg, var(--btn-primary) 0%, #6366f1 100%);
            --secondary-gradient: linear-gradient(135deg, var(--btn-secondary) 0%, #9ca3af 100%);
            --success-gradient: linear-gradient(135deg, var(--btn-success) 0%, #10b981 100%);
            --warning-gradient: linear-gradient(135deg, var(--btn-warning) 0%, #f59e0b 100%);
            --danger-gradient: linear-gradient(135deg, var(--btn-danger) 0%, #ef4444 100%);
            --info-gradient: linear-gradient(135deg, var(--btn-info) 0%, #0ea5e9 100%);
            
            /* Glassmorphism Colors */
            --glass-bg: rgba(255, 255, 255, 0.4);
            --glass-border: rgba(255, 255, 255, 0.25);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            
            /* Light Mode Colors */
            --light-card: rgba(255, 255, 255, 0.8);
            --light-text: #2d3748;
            --light-border: rgba(0, 0, 0, 0.1);
            
            /* Spacing */
            --spacing-xs: 0.5rem;
            --spacing-sm: 1rem;
            --spacing-md: 1.5rem;
            --spacing-lg: 2rem;
            --spacing-xl: 3rem;
            
            /* Border Radius */
            --radius-sm: 8px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --radius-xl: 32px;
        }

        /* Main layout styles */
        .main-container {
            background: white;
            margin: 20px auto;
            max-width: 1400px;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            position: relative;
            z-index: 1;
            padding: 0 20px;
        }

        .header-section {
            background: linear-gradient(135deg, var(--btn-primary) 0%, #1d4ed8 100%);
            color: white;
            padding: 2rem;
            position: relative;
            margin: 0 -20px;
        }

        .dashboard-cards {
            padding: 2rem 0;
            width: 100%;
        }

        /* Glassmorphism Cards */
        .glass-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.2s ease-in-out;
            margin-bottom: 1.5rem;
            overflow: hidden;
            width: 100%;
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: white;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Modern Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            text-align: center;
            line-height: 1.25;
        }

        .btn-primary {
            background-color: var(--btn-primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: #3730a3;
            color: white;
        }

        .btn-success {
            background-color: var(--btn-success);
            color: white;
        }

        .btn-success:hover {
            background-color: #047857;
            color: white;
        }

        .btn-secondary {
            background-color: var(--btn-secondary);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #4b5563;
            color: white;
        }

        /* Form Controls */
        .form-control, .form-select {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            color: var(--light-text);
            font-size: 0.875rem;
            transition: all 0.2s ease;
            padding: 0.75rem 1rem;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--btn-primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* Input Group */
        .input-group {
            display: flex;
            gap: 1rem;
            align-items: stretch;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            justify-content: space-between;
            width: 100%;
        }

        .input-group .form-control {
            flex: 2;
            margin-bottom: 0;
            min-width: 250px;
        }

        .input-group .form-select {
            flex: 1;
            min-width: 180px;
        }

        .input-group input[type="date"] {
            flex: 1;
            min-width: 160px;
        }

        .input-group .btn {
            flex-shrink: 0;
            min-width: 100px;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            width: 100%;
        }

        .section-icon {
            width: 3rem;
            height: 3rem;
            background-color: var(--btn-primary);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);
        }

        /* Table Styles */
        .table-responsive {
            background: white;
            border-radius: 0.75rem;
            overflow-x: auto;
            overflow-y: visible;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
            width: 100%;
        }

        .table {
            margin-bottom: 0;
            color: var(--light-text);
            width: 100%;
            min-width: 1200px;
        }

        .table th {
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            border: none;
            padding: 1rem;
            min-width: 120px;
            text-align: center;
            background-color: #f9fafb;
            color: #374151;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #f3f4f6;
            min-width: 120px;
            text-align: center;
            color: #374151;
        }

        /* Pagination Styles */
        .pagination-container {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-top: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .pagination-info {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pagination-info::before {
            content: "📊";
            font-size: 1rem;
        }

        .pagination-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.5rem;
            height: 2.5rem;
            padding: 0.5rem 0.75rem;
            background: white;
            color: #6b7280;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .page-btn:hover {
            background: linear-gradient(135deg, var(--btn-primary) 0%, #3730a3 100%);
            color: white;
            border-color: var(--btn-primary);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            text-decoration: none;
        }

        .page-btn.active {
            background: linear-gradient(135deg, var(--btn-primary) 0%, #3730a3 100%);
            color: white;
            border-color: var(--btn-primary);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            transform: scale(1.05);
        }

        .page-btn.active::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%);
            pointer-events: none;
        }

        .page-btn.disabled {
            background: #f9fafb;
            color: #d1d5db;
            border-color: #f3f4f6;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .page-btn.disabled:hover {
            background: #f9fafb;
            color: #d1d5db;
            border-color: #f3f4f6;
            transform: none;
            box-shadow: none;
        }

        .page-btn i {
            font-size: 0.75rem;
        }

        /* Special styling for Previous/Next buttons */
        .page-btn:first-child,
        .page-btn:last-child {
            min-width: auto;
            padding: 0.5rem 1rem;
            gap: 0.5rem;
        }

        .page-btn:first-child {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
            border-color: #6b7280;
        }

        .page-btn:last-child {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            border-color: #059669;
        }

        .page-btn:first-child:hover {
            background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
            border-color: #4b5563;
        }

        .page-btn:last-child:hover {
            background: linear-gradient(135deg, #047857 0%, #065f46 100%);
            border-color: #047857;
        }

        /* Page Jump Styles */
        .page-jump {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
            margin-top: 1rem;
        }

        .page-jump input[type="number"] {
            transition: all 0.2s ease-in-out;
        }

        .page-jump input[type="number"]:focus {
            outline: none;
            border-color: var(--btn-primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .page-jump .page-btn {
            background: linear-gradient(135deg, var(--btn-info) 0%, #0369a1 100%);
            color: white;
            border-color: var(--btn-info);
        }

        .page-jump .page-btn:hover {
            background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
            border-color: #0369a1;
        }

        /* Custom User Profile for Manage Appointments Page */
        .user-profile {
            max-width: 269px !important;
            height: 62px !important;
            padding: 6px 12px !important;
            gap: 10px !important;
        }

        .user-avatar {
            width: 26px !important;
            height: 26px !important;
            font-size: 11px !important;
            flex-shrink: 0;
        }

        .user-info {
            line-height: 1.3 !important;
        }

        .user-name {
            font-size: 13px !important;
            font-weight: 600 !important;
        }

        .user-role {
            font-size: 10px !important;
            opacity: 0.85;
        }

        .logout-btn {
            padding: 6px 10px !important;
            font-size: 11px !important;
            gap: 4px !important;
        }

        .logout-btn i {
            font-size: 10px !important;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-container {
                margin: 10px;
                padding: 0 10px;
            }
            
            .input-group {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .input-group .form-control,
            .input-group .form-select,
            .input-group input[type="date"] {
                flex: none;
                width: 100%;
                min-width: auto;
            }
            
            .input-group .btn {
                width: 100%;
            }

            .pagination-container {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
                padding: 1rem;
            }

            .pagination-info {
                text-align: center;
                justify-content: center;
            }

            .pagination-nav {
                justify-content: center;
                flex-wrap: wrap;
            }

            .page-btn {
                min-width: 2.25rem;
                height: 2.25rem;
                font-size: 0.8rem;
            }

            .page-btn:first-child,
            .page-btn:last-child {
                padding: 0.4rem 0.8rem;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content Container -->
    <div class="main-container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="welcome-text">
            <div class="d-flex align-items-center mb-3">
                <div class="me-3">
                    <i class="fas fa-calendar-check" style="font-size: 3rem; color: white;"></i>
                </div>
                <div>
                    <h1 class="mb-2" style="color: white;">Appointment Management Hub</h1>
                    <p class="mb-0 opacity-90" style="color: white;">Efficiently manage all patient appointments with advanced filtering and real-time status updates</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-cards">
        <!-- Header Actions -->
        <div class="glass-card fade-in">
            <div class="header-actions">
                <h2 class="section-title">
                    <i class="fas fa-calendar-check"></i>
                    Manage All Appointments
                </h2>
                <a href="add_appointment.php" class="btn btn-success">
                    <i class="fas fa-plus"></i>
                    Add New Appointment
                </a>
            </div>
        </div>

    <!-- Advanced Search Section -->
    <div class="glass-card slide-in">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-search"></i>
            </div>
            <h3 class="section-title">🔍 Advanced Search & Filters</h3>
        </div>
        <form method="GET">
            <div class="input-group">
                <input type="text" name="search" class="form-control" 
                       placeholder="Search by Patient Name, Doctor, or Appointment Status..." 
                       value="<?= htmlspecialchars($search) ?>">
                
                <select name="date_filter" class="form-select" style="max-width: 200px;">
                    <option value="">📅 All Dates</option>
                    <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>📍 Today</option>
                    <option value="tomorrow" <?= $date_filter === 'tomorrow' ? 'selected' : '' ?>>⏭️ Tomorrow</option>
                    <option value="this_week" <?= $date_filter === 'this_week' ? 'selected' : '' ?>>📅 This Week</option>
                    <option value="this_month" <?= $date_filter === 'this_month' ? 'selected' : '' ?>>📆 This Month</option>
                    <option value="upcoming" <?= $date_filter === 'upcoming' ? 'selected' : '' ?>>🔮 Upcoming</option>
                    <option value="past" <?= $date_filter === 'past' ? 'selected' : '' ?>>📋 Past</option>
                </select>
                
                <input type="date" name="custom_date" class="form-control" 
                       placeholder="Custom Date" 
                       value="<?= !empty($date_filter) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_filter) ? htmlspecialchars($date_filter) : '' ?>"
                       style="max-width: 160px;"
                       onchange="if(this.value) { document.querySelector('select[name=\"date_filter\"]').value = this.value; }">
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Search
                </button>
                <a href="manage_appointments.php" class="btn btn-secondary">
                    <i class="fas fa-refresh"></i>
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Today's Appointments Section -->
    <?php
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$date_filter = isset($_GET['date_filter']) ? trim($_GET['date_filter']) : '';

$today_conditions = ["a.appointment_date = CURDATE()"];
$today_params = [];
$today_param_types = '';

if (!empty($search)) {
    $today_conditions[] = "(p.name LIKE ? OR a.patient_name LIKE ? OR d.name LIKE ? OR a.status LIKE ?)";
    $today_params[] = "%$search%";
    $today_params[] = "%$search%";
    $today_params[] = "%$search%";
    $today_params[] = "%$search%";
    $today_param_types .= 'ssss';
}

$today_sql = "
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
    WHERE " . implode(" AND ", $today_conditions) . "
    ORDER BY a.appointment_time ASC
";
$stmt_today = $conn->prepare($today_sql);
if (!empty($today_params)) {
    $stmt_today->bind_param($today_param_types, ...$today_params);
}
$stmt_today->execute();
$today_appts = $stmt_today->get_result();
?>

<?php if ($today_appts && $today_appts->num_rows > 0) { ?>
    <div class="glass-card fade-in">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <h3 class="section-title">📅 Today's Priority Appointments</h3>
        </div>
        <div class="table-responsive table-scroll-indicator">
            <table class="table table-hover">
                <thead class="table-primary">
                    <tr>
                        <th><i class="fas fa-hashtag me-2"></i>ID</th>
                        <th><i class="fas fa-user me-2"></i>Patient</th>
                        <th><i class="fas fa-phone me-2"></i>Phone</th>
                        <th><i class="fas fa-envelope me-2"></i>Email</th>
                        <th><i class="fas fa-user-md me-2"></i>Doctor</th>
                        <th><i class="fas fa-clock me-2"></i>Time</th>
                        <th><i class="fas fa-info-circle me-2"></i>Status</th>
                        <th><i class="fas fa-paper-plane me-2"></i>Contact</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $today_appts->fetch_assoc()) { ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['id']); ?></strong></td>
                        <td>
                            <div style="font-weight: 600;">
                                <?= htmlspecialchars($row['patient'] ?: '-'); ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($row['patient_phone'] ?: '-'); ?></td>
                        <td>
                            <code style="background: rgba(102, 126, 234, 0.1); padding: 2px 6px; border-radius: 4px; font-size: 0.85rem;">
                                <?= htmlspecialchars($row['final_email'] ?: '-'); ?>
                            </code>
                        </td>
                        <td>
                            <div class="badge bg-success">
                                <i class="fas fa-stethoscope me-1"></i>
                                <?= htmlspecialchars($row['doctor'] ?: 'Unassigned'); ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #667eea; font-size: 1.1rem;">
                                <?= htmlspecialchars($row['appointment_time']); ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge 
                                <?= $row['status'] == 'confirmed' ? 'bg-success' : 
                                ($row['status'] == 'cancelled_by_patient' ? 'bg-danger' : 
                                ($row['status'] == 'cancelled_by_admin' ? 'bg-dark' : 'bg-warning')); ?>">
                                <i class="fas fa-<?= $row['status'] == 'confirmed' ? 'check-circle' : 
                                    ($row['status'] == 'cancelled_by_patient' ? 'times-circle' : 
                                    ($row['status'] == 'cancelled_by_admin' ? 'ban' : 'clock')); ?> me-1"></i>
                                <?= ucfirst(str_replace('_', ' ', htmlspecialchars($row['status']))); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($row['final_email'])) { ?>
                                <a href="mailto:<?= htmlspecialchars($row['final_email']); ?>?subject=Reply from Green Life Dental Clinic" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-envelope"></i>
                                    Send Mail
                                </a>
                            <?php } else { ?>
                                <span class="text-muted">N/A</span>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <div class="mobile-scroll-hint">
                <i class="fas fa-hand-point-right"></i> Swipe right to see more columns
            </div>
        </div>
    </div>
<?php } ?>


    <!-- All Appointments Section -->
    <?php if ($appointments && $appointments->num_rows > 0) { ?>
        <div class="glass-card slide-in">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-list-alt"></i>
                </div>
                <div style="flex: 1;">
                    <h3 class="section-title">📋 Complete Appointments Registry</h3>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small style="color: var(--text-secondary); font-weight: 500;">
                            Showing <?= $appointments->num_rows ?> of <?= number_format($total_appointments) ?> appointments
                        </small>
                        <?php if ($total_pages > 1): ?>
                        <small style="color: var(--primary); font-weight: 600;">
                            Page <?= $current_page ?> of <?= $total_pages ?>
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="table-responsive table-scroll-indicator">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th><i class="fas fa-hashtag me-2"></i>ID</th>
                            <th><i class="fas fa-user me-2"></i>Patient</th>
                            <th><i class="fas fa-phone me-2"></i>Phone</th>
                            <th><i class="fas fa-envelope me-2"></i>Email</th>
                            <th><i class="fas fa-user-md me-2"></i>Doctor</th>
                            <th><i class="fas fa-calendar me-2"></i>Date</th>
                            <th><i class="fas fa-clock me-2"></i>Time</th>
                            <th><i class="fas fa-comment me-2"></i>Message</th>
                            <th><i class="fas fa-info-circle me-2"></i>Status</th>
                            <th><i class="fas fa-history me-2"></i>Created At</th>
                            <th><i class="fas fa-cogs me-2"></i>Actions</th>
                            <th><i class="fas fa-paper-plane me-2"></i>Contact</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $appointments->fetch_assoc()) { 
                            $is_today = ($row['appointment_date'] === date('Y-m-d'));
                            $appt_date = strtotime($row['appointment_date']);
                            $is_expired = $appt_date < strtotime(date('Y-m-d')); 
                        ?>
                            <tr class="<?= $is_today ? 'table-info' : '' ?>">
                                <td>
                                    <strong style="color: #667eea;"><?= htmlspecialchars($row['id']); ?></strong>
                                    <?php if ($is_today) { ?>
                                        <div class="badge bg-warning" style="font-size: 0.7rem; margin-top: 2px;">
                                            <i class="fas fa-star"></i> TODAY
                                        </div>
                                    <?php } ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600;">
                                        <?= htmlspecialchars($row['patient'] ?: '-'); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-family: 'Courier New', monospace; font-size: 0.9rem;">
                                        <?= htmlspecialchars($row['patient_phone'] ?: '-'); ?>
                                    </div>
                                </td>
                                <td>
                                    <code style="background: rgba(102, 126, 234, 0.1); padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;">
                                        <?= htmlspecialchars($row['final_email'] ?: '-'); ?>
                                    </code>
                                </td>
                                <td>
                                    <?php if (!empty($row['doctor'])) { ?>
                                        <div class="badge bg-success">
                                            <i class="fas fa-stethoscope me-1"></i>
                                            Dr. <?= htmlspecialchars($row['doctor']); ?>
                                        </div>
                                    <?php } else { ?>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Unassigned
                                        </span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #667eea;">
                                        <?= date('M j, Y', strtotime($row['appointment_date'])); ?>
                                    </div>
                                    <?php if ($is_expired) { ?>
                                        <small class="text-muted">(Expired)</small>
                                    <?php } ?>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #764ba2; font-size: 1rem;">
                                        <?= htmlspecialchars($row['appointment_time']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?= !empty($row['message']) 
                                    ? '<div class="text-truncate" style="max-width: 120px;" title="'.htmlspecialchars($row['message']).'">'.
                                      '<i class="fas fa-comment-dots me-1"></i>'.
                                      htmlspecialchars(mb_strimwidth($row['message'], 0, 30, "...")).
                                      '</div>'
                                    : '<span class="text-muted"><i class="fas fa-minus"></i> N/A</span>'; ?>
                                </td>
                                <td>
                                    <span class="badge 
                                    <?= $row['status'] == 'confirmed' ? 'bg-success' : 
                                    ($row['status'] == 'cancelled_by_patient' ? 'bg-danger' : 
                                    ($row['status'] == 'cancelled_by_admin' ? 'bg-dark' : 'bg-warning')); ?>">
                                    <i class="fas fa-<?= $row['status'] == 'confirmed' ? 'check-circle' : 
                                        ($row['status'] == 'cancelled_by_patient' ? 'times-circle' : 
                                        ($row['status'] == 'cancelled_by_admin' ? 'ban' : 'clock')); ?> me-1"></i>
                                    <?= ucfirst(str_replace('_', ' ', htmlspecialchars($row['status']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem; color: rgba(45,55,72,0.7);">
                                        <?= date('M j, Y', strtotime($row['created_at'])); ?><br>
                                        <small><?= date('g:i A', strtotime($row['created_at'])); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div style="flex-wrap: wrap; display: flex; flex-direction: column; gap: 8px;">
                                        <?php if ($is_expired): ?>
                                            <!-- Expired - No actions available -->
                                            <div class="text-muted text-center" style="padding: 8px;">
                                                <i class="fas fa-clock"></i> Expired
                                            </div>
                                        <?php else: ?>
                                            <!-- Active - Full Actions -->
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="appointment_id" value="<?= $row['id']; ?>">
                                                <select name="status" class="form-select form-select-sm" style="width: 100%; margin-bottom: 4px;">
                                                    <option value="confirmed" <?= $row['status'] == 'confirmed' ? 'selected' : ''; ?>>✅ Confirmed</option>
                                                    <option value="cancelled_by_patient" <?= $row['status'] == 'cancelled_by_patient' ? 'selected' : ''; ?>>❌ Cancelled (Patient)</option>
                                                    <option value="cancelled_by_admin" <?= $row['status'] == 'cancelled_by_admin' ? 'selected' : ''; ?>>🚫 Cancelled (Admin)</option>
                                                </select>
                                                <button type="submit" name="update_status" class="btn btn-primary btn-sm" style="width: 100%;">
                                                    <i class="fas fa-sync-alt"></i>
                                                    Update Status
                                                </button>
                                            </form>

                                            <?php if (empty($row['doctor'])) { ?>
                                                <form method="POST" style="margin: 4px 0 0 0;">
                                                    <input type="hidden" name="appointment_id" value="<?= $row['id']; ?>">
                                                    <select name="doctor_id" class="form-select form-select-sm" style="width: 100%; margin-bottom: 4px;" required>
                                                        <option value="">🩺 Select Doctor...</option>
                                                        <?php 
                                                            $doctor_list->data_seek(0);
                                                            while ($doc = $doctor_list->fetch_assoc()) { ?>
                                                            <option value="<?= $doc['id']; ?>">Dr. <?= htmlspecialchars($doc['name']); ?> (<?= htmlspecialchars($doc['specialty']); ?>)</option>
                                                        <?php } ?>
                                                    </select>
                                                    <button type="submit" name="assign_doctor" class="btn btn-success btn-sm" style="width: 100%;">
                                                        <i class="fas fa-user-plus"></i>
                                                        Assign Doctor
                                                    </button>
                                                </form>
                                            <?php } ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($row['final_email'])) { ?>
                                        <a href="mailto:<?= htmlspecialchars($row['final_email']); ?>?subject=Reply from Green Life Dental Clinic" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-envelope"></i>
                                            Send Mail
                                        </a>
                                    <?php } else { ?>
                                        <span class="text-muted">
                                            <i class="fas fa-minus"></i> N/A
                                        </span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <div class="mobile-scroll-hint">
                    <i class="fas fa-hand-point-right"></i> Swipe right to see more columns
                </div>
            </div>
            
            <!-- Enhanced Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <strong><?= $start_appointment ?>-<?= $end_appointment ?></strong> of <strong><?= number_format($total_appointments) ?></strong> appointments
                </div>
                
                <div class="pagination-nav">
                    <!-- Previous Page -->
                    <?php if ($current_page > 1): ?>
                        <?php 
                        $prev_params = $_GET;
                        $prev_params['page'] = $current_page - 1;
                        ?>
                        <a href="?<?= http_build_query($prev_params) ?>" class="page-btn" title="Previous Page">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="page-btn disabled" title="No Previous Page">
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
                        echo '<a href="?' . http_build_query($first_params) . '" class="page-btn" title="First Page">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="page-btn disabled">...</span>';
                        }
                    }
                    
                    // Show page numbers in range
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $page_params = $_GET;
                        $page_params['page'] = $i;
                        if ($i == $current_page) {
                            echo '<span class="page-btn active" title="Current Page">' . $i . '</span>';
                        } else {
                            echo '<a href="?' . http_build_query($page_params) . '" class="page-btn" title="Go to Page ' . $i . '">' . $i . '</a>';
                        }
                    }
                    
                    // Show last page if not in range
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="page-btn disabled">...</span>';
                        }
                        $last_params = $_GET;
                        $last_params['page'] = $total_pages;
                        echo '<a href="?' . http_build_query($last_params) . '" class="page-btn" title="Last Page">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <!-- Next Page -->
                    <?php if ($current_page < $total_pages): ?>
                        <?php 
                        $next_params = $_GET;
                        $next_params['page'] = $current_page + 1;
                        ?>
                        <a href="?<?= http_build_query($next_params) ?>" class="page-btn" title="Next Page">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-btn disabled" title="No Next Page">
                            Next <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Quick Page Jump -->
                <?php if ($total_pages > 10): ?>
                <div class="page-jump">
                    <form method="GET" style="display: flex; align-items: center; gap: 0.5rem;">
                        <!-- Preserve existing GET parameters -->
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key !== 'page'): ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <span style="font-size: 0.875rem; color: #6b7280;">Jump to:</span>
                        <input type="number" name="page" min="1" max="<?= $total_pages ?>" 
                               value="<?= $current_page ?>" 
                               style="width: 70px; padding: 0.25rem 0.5rem; border: 2px solid #e5e7eb; border-radius: 0.375rem; text-align: center; font-size: 0.875rem;"
                               onchange="this.form.submit()">
                        <button type="submit" class="page-btn" style="min-width: auto; padding: 0.25rem 0.75rem; font-size: 0.875rem;">
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php } else { ?>
        <div class="glass-card">
            <div class="section-header text-center">
                <div style="font-size: 4rem; color: rgba(45, 55, 72, 0.3); margin-bottom: 1rem;">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div>
                    <h4 style="color: rgba(45,55,72,0.8); margin-bottom: 1rem;">📋 No Appointments Found</h4>
                    <p style="color: rgba(45,55,72,0.6); margin-bottom: 1.5rem;">
                        No appointments match your current search criteria. Try adjusting your filters or add a new appointment.
                    </p>
                    <a href="add_appointment.php" class="btn btn-success">
                        <i class="fas fa-plus"></i>
                        Add First Appointment
                    </a>
                </div>
            </div>
        </div>
    <?php } ?>
    </div> <!-- End dashboard-cards -->
</div> <!-- End main-container -->

<!-- Modern JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle custom date input
        const customDateInput = document.querySelector('input[name="custom_date"]');
        const dateFilterSelect = document.querySelector('select[name="date_filter"]');
        
        if (customDateInput && dateFilterSelect) {
            // When custom date is selected, update the hidden select
            customDateInput.addEventListener('change', function() {
                if (this.value) {
                    dateFilterSelect.value = this.value;
                    // Visual feedback
                    this.style.borderColor = '#4f46e5';
                    this.style.background = 'rgba(79, 70, 229, 0.1)';
                } else {
                    this.style.borderColor = '';
                    this.style.background = '';
                }
            });
            
            // When preset date filter is selected, clear custom date if it's a preset
            dateFilterSelect.addEventListener('change', function() {
                const presetValues = ['today', 'tomorrow', 'this_week', 'this_month', 'upcoming', 'past', ''];
                if (presetValues.includes(this.value)) {
                    customDateInput.value = '';
                    customDateInput.style.borderColor = '';
                    customDateInput.style.background = '';
                }
            });
        }
        
        // Initialize all enhancements
        initializeAnimations();
        setupFormEnhancements();
        setupTableInteractions();
        setupStatusUpdates();
        setupTableScroll();
        setupMobileEnhancements();
        setupPaginationEnhancements();
        
        // Responsive adjustments
        handleResponsiveChanges();
        window.addEventListener('resize', handleResponsiveChanges);
        window.addEventListener('orientationchange', function() {
            setTimeout(handleResponsiveChanges, 100);
        });
    });
    
    function handleResponsiveChanges() {
        const isMobile = window.innerWidth <= 768;
        const tables = document.querySelectorAll('.table-responsive');
        
        tables.forEach(table => {
            if (isMobile) {
                // Ensure mobile scroll hints are visible
                const scrollHint = table.parentElement.querySelector('.mobile-scroll-hint');
                if (scrollHint && table.scrollWidth > table.clientWidth) {
                    scrollHint.style.display = 'block';
                }
                
                // Add mobile-specific classes
                table.classList.add('mobile-table');
            } else {
                // Hide mobile scroll hints on desktop
                const scrollHint = table.parentElement.querySelector('.mobile-scroll-hint');
                if (scrollHint) {
                    scrollHint.style.display = 'none';
                }
                
                table.classList.remove('mobile-table');
            }
        });
        
        // Adjust form layouts for mobile
        const inputGroups = document.querySelectorAll('.input-group');
        inputGroups.forEach(group => {
            if (isMobile) {
                group.style.flexDirection = 'column';
                group.style.gap = '0.75rem';
            } else {
                group.style.flexDirection = 'row';
                group.style.gap = '0.75rem';
            }
        });
    }

    function initializeAnimations() {
        // Stagger animations for cards
        const cards = document.querySelectorAll('.glass-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });

        // Smooth scrolling for internal links
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
    }

    function setupFormEnhancements() {
        // Enhanced search input with real-time feedback
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const value = this.value.trim();
                
                // Visual feedback for search terms
                if (value.length > 2) {
                    this.style.background = 'rgba(137, 247, 254, 0.2)';
                    this.style.borderColor = 'rgba(137, 247, 254, 0.5)';
                } else {
                    this.style.background = 'rgba(255, 255, 255, 0.6)';
                    this.style.borderColor = 'rgba(45, 55, 72, 0.2)';
                }
            });
        }

        // Form submission loading states - but ONLY for non-status-update forms
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            // Skip status update forms to avoid interference
            const isStatusUpdateForm = form.querySelector('button[name="update_status"]');
            const isDocAssignForm = form.querySelector('button[name="assign_doctor"]');
            
            if (!isStatusUpdateForm && !isDocAssignForm) {
                form.addEventListener('submit', function() {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<div class="loading"></div> Processing...';
                        submitBtn.disabled = true;
                        
                        // Re-enable after 3 seconds as fallback
                        setTimeout(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }, 3000);
                    }
                });
            }
        });
    }

    function setupTableInteractions() {
        // Enhanced table row hover effects
        const tableRows = document.querySelectorAll('.table tbody tr');
        tableRows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.01)';
                this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
                this.style.zIndex = '10';
            });

            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.boxShadow = 'none';
                this.style.zIndex = 'auto';
            });
        });

        // Message tooltip enhancement
        const messageElements = document.querySelectorAll('td [title]');
        messageElements.forEach(element => {
            element.addEventListener('mouseenter', function() {
                if (this.title && this.title.length > 30) {
                    this.style.cursor = 'help';
                    this.style.textDecoration = 'underline dotted';
                }
            });
        });
    }

    function setupStatusUpdates() {
        // Enhanced status update confirmations
        const statusForms = document.querySelectorAll('form[method="POST"]');
        statusForms.forEach(form => {
            const statusSelect = form.querySelector('select[name="status"]');
            const updateBtn = form.querySelector('button[name="update_status"]');
            
            if (statusSelect && updateBtn) {
                // Debug: Log form detection
                console.log('Status form detected:', form);
                
                statusSelect.addEventListener('change', function() {
                    const newStatus = this.value;
                    const statusText = this.options[this.selectedIndex].text;
                    
                    console.log('Status changed to:', newStatus);
                    
                    // Visual feedback for status change
                    this.style.background = getStatusColor(newStatus);
                    this.style.color = 'white';
                    this.style.fontWeight = '600';
                    
                    updateBtn.style.background = 'var(--success-gradient)';
                    updateBtn.style.transform = 'scale(1.05)';
                });
                
                // Debug: Add form submission handler
                form.addEventListener('submit', function(e) {
                    const appointmentId = form.querySelector('input[name="appointment_id"]').value;
                    const selectedStatus = statusSelect.value;
                    console.log('Form submitting - Appointment ID:', appointmentId, 'Status:', selectedStatus);
                    
                    // Don't prevent default - let the form submit normally
                    return true;
                });
            }
        });

        // Doctor assignment confirmations
        const doctorForms = document.querySelectorAll('form[method="POST"] select[name="doctor_id"]');
        doctorForms.forEach(select => {
            select.addEventListener('change', function() {
                if (this.value) {
                    const assignBtn = this.parentElement.querySelector('button[name="assign_doctor"]');
                    if (assignBtn) {
                        assignBtn.style.background = 'var(--success-gradient)';
                        assignBtn.style.transform = 'scale(1.05)';
                    }
                }
            });
        });
    }

    function getStatusColor(status) {
        const colors = {
            'confirmed': '#059669',
            'cancelled_by_patient': '#dc2626',
            'cancelled_by_admin': '#6b7280',
            'pending': '#d97706'
        };
        return colors[status] || colors.pending;
    }

    // Enhanced notification system
    function showNotification(message, type = 'info', duration = 3000) {
        // Remove existing notifications
        const existing = document.querySelectorAll('.notification');
        existing.forEach(n => n.remove());

        const notification = document.createElement('div');
        notification.className = 'notification';
        notification.style.cssText = `
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: ${getNotificationColor(type)};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            z-index: 10000;
            backdrop-filter: blur(10px);
            animation: slideInRight 0.4s ease-out;
            max-width: 400px;
            word-wrap: break-word;
        `;
        
        const icon = getNotificationIcon(type);
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i class="${icon}" style="font-size: 1.2rem;"></i>
                <div>${message}</div>
            </div>
        `;

        document.body.appendChild(notification);

        // Auto remove
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.4s ease-in';
            setTimeout(() => notification.remove(), 400);
        }, duration);
    }

    function getNotificationColor(type) {
        const colors = {
            'success': '#059669',
            'error': '#dc2626',
            'warning': '#d97706',
            'info': '#0284c7'
        };
        return colors[type] || colors.info;
    }

    function getNotificationIcon(type) {
        const icons = {
            'success': 'fas fa-check-circle',
            'error': 'fas fa-exclamation-triangle',
            'warning': 'fas fa-exclamation-circle',
            'info': 'fas fa-info-circle'
        };
        return icons[type] || icons.info;
    }

    // Add CSS animations for notifications
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes slideOutRight {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(100%); }
        }
    `;
    document.head.appendChild(style);

    // Auto-highlight today's appointments
    document.addEventListener('DOMContentLoaded', function() {
        const todayRows = document.querySelectorAll('.table-info');
        todayRows.forEach(row => {
            row.style.background = 'linear-gradient(135deg, rgba(13, 202, 240, 0.1), rgba(168, 237, 234, 0.1))';
            row.style.borderLeft = '4px solid #0dcaf0';
        });

        // Add scroll behavior for tables
        setupTableScroll();
    });

    function setupTableScroll() {
        const tableContainers = document.querySelectorAll('.table-responsive');
        tableContainers.forEach(container => {
            // Enhanced mobile scroll experience
            let isScrolling = false;
            
            // Touch scroll for mobile
            container.addEventListener('touchstart', function(e) {
                isScrolling = true;
            }, { passive: true });
            
            container.addEventListener('touchend', function(e) {
                isScrolling = false;
            }, { passive: true });
            
            // Show/hide scroll indicator based on scroll position
            function updateScrollIndicator() {
                const scrollHint = container.parentElement.querySelector('.mobile-scroll-hint');
                const isScrollable = container.scrollWidth > container.clientWidth;
                const isAtEnd = container.scrollLeft >= (container.scrollWidth - container.clientWidth - 10);
                
                if (scrollHint) {
                    if (isScrollable && !isAtEnd && !isScrolling) {
                        scrollHint.style.display = 'block';
                        scrollHint.style.opacity = '1';
                    } else {
                        scrollHint.style.opacity = '0.5';
                        if (isAtEnd) {
                            setTimeout(() => {
                                scrollHint.style.display = 'none';
                            }, 2000);
                        }
                    }
                }
            }

            // Initial check
            updateScrollIndicator();

            // Update on scroll
            container.addEventListener('scroll', updateScrollIndicator);
            
            // Update on window resize
            window.addEventListener('resize', updateScrollIndicator);

            // Enhanced scroll with mouse wheel (for desktop)
            container.addEventListener('wheel', function(e) {
                if (e.deltaY !== 0 && window.innerWidth > 768) {
                    e.preventDefault();
                    this.scrollLeft += e.deltaY * 2;
                }
            }, { passive: false });
            
            // Auto-hide scroll hint after user interacts
            container.addEventListener('scroll', function() {
                const scrollHint = this.parentElement.querySelector('.mobile-scroll-hint');
                if (scrollHint && this.scrollLeft > 50) {
                    scrollHint.style.transition = 'opacity 0.5s ease';
                    scrollHint.style.opacity = '0.3';
                }
            });
        });
    }
    
    // Enhanced mobile experience
    function setupMobileEnhancements() {
        // Add touch feedback for buttons on mobile
        if ('ontouchstart' in window) {
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => {
                btn.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.95)';
                }, { passive: true });
                
                btn.addEventListener('touchend', function() {
                    this.style.transform = '';
                }, { passive: true });
            });
        }
        
        // Improve form select experience on mobile
        const selects = document.querySelectorAll('.form-select-sm');
        selects.forEach(select => {
            // Add visual feedback when select is focused on mobile
            select.addEventListener('focus', function() {
                if (window.innerWidth <= 768) {
                    this.style.borderColor = '#4f46e5';
                    this.style.boxShadow = '0 0 0 3px rgba(79, 70, 229, 0.1)';
                }
            });
            
            select.addEventListener('blur', function() {
                this.style.borderColor = '';
                this.style.boxShadow = '';
            });
        });
        
        // Auto-scroll to show full table actions on mobile when clicked
        const actionCells = document.querySelectorAll('.table td:last-child');
        actionCells.forEach(cell => {
            const forms = cell.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('focus', function() {
                    if (window.innerWidth <= 768) {
                        setTimeout(() => {
                            this.scrollIntoView({ 
                                behavior: 'smooth', 
                                block: 'center',
                                inline: 'center' 
                            });
                        }, 100);
                    }
                }, true);
            });
        });
    }

    // Enhanced Pagination functionality
    function setupPaginationEnhancements() {
        // Smooth page transitions with loading indicator
        const pageLinks = document.querySelectorAll('.page-btn:not(.disabled):not(.active)');
        pageLinks.forEach(link => {
            if (link.tagName === 'A') {
                link.addEventListener('click', function(e) {
                    // Add loading state
                    const originalContent = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    this.style.pointerEvents = 'none';
                    
                    // Restore if navigation doesn't happen (fallback)
                    setTimeout(() => {
                        this.innerHTML = originalContent;
                        this.style.pointerEvents = '';
                    }, 2000);
                });
            }
        });

        // Enhanced page jump functionality
        const pageJumpInput = document.querySelector('.page-jump input[type="number"]');
        if (pageJumpInput) {
            // Add keyboard shortcuts
            pageJumpInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.form.submit();
                }
                
                // Arrow key navigation
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.value = Math.min(parseInt(this.max), parseInt(this.value || 1) + 1);
                }
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.value = Math.max(1, parseInt(this.value || 1) - 1);
                }
            });

            // Validate input
            pageJumpInput.addEventListener('input', function() {
                const value = parseInt(this.value);
                const max = parseInt(this.max);
                
                if (value < 1) {
                    this.value = 1;
                    this.style.borderColor = '#ef4444';
                } else if (value > max) {
                    this.value = max;
                    this.style.borderColor = '#ef4444';
                } else {
                    this.style.borderColor = '#e5e7eb';
                }
            });

            // Auto-submit on value change (with debounce)
            let jumpTimeout;
            pageJumpInput.addEventListener('change', function() {
                clearTimeout(jumpTimeout);
                jumpTimeout = setTimeout(() => {
                    const value = parseInt(this.value);
                    const current = <?= $current_page ?>;
                    
                    if (value && value !== current && value >= 1 && value <= parseInt(this.max)) {
                        // Visual feedback before submission
                        this.style.background = 'rgba(34, 197, 94, 0.1)';
                        this.style.borderColor = '#22c55e';
                        
                        setTimeout(() => {
                            this.form.submit();
                        }, 300);
                    }
                }, 500);
            });
        }

        // Add page transition effects
        const paginationContainer = document.querySelector('.pagination-container');
        if (paginationContainer) {
            // Animate on load
            paginationContainer.style.opacity = '0';
            paginationContainer.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                paginationContainer.style.transition = 'all 0.5s ease-out';
                paginationContainer.style.opacity = '1';
                paginationContainer.style.transform = 'translateY(0)';
            }, 100);

            // Add visual feedback on hover
            const navButtons = paginationContainer.querySelectorAll('.page-btn');
            navButtons.forEach(btn => {
                if (!btn.classList.contains('disabled') && !btn.classList.contains('active')) {
                    btn.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-2px) scale(1.05)';
                    });
                    
                    btn.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(0) scale(1)';
                    });
                }
            });
        }

        // Show pagination stats with animation
        const paginationInfo = document.querySelector('.pagination-info');
        if (paginationInfo) {
            // Add count-up animation for numbers
            const numbers = paginationInfo.querySelectorAll('strong');
            numbers.forEach(num => {
                const finalValue = parseInt(num.textContent.replace(/,/g, ''));
                if (finalValue > 0) {
                    animateNumber(num, 0, finalValue, 1000);
                }
            });
        }
    }

    // Number animation utility
    function animateNumber(element, start, end, duration) {
        const startTime = performance.now();
        const difference = end - start;
        
        function updateNumber(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function for smooth animation
            const easeOut = 1 - Math.pow(1 - progress, 3);
            const current = Math.floor(start + (difference * easeOut));
            
            element.textContent = current.toLocaleString();
            
            if (progress < 1) {
                requestAnimationFrame(updateNumber);
            } else {
                element.textContent = end.toLocaleString();
            }
        }
        
        requestAnimationFrame(updateNumber);
    }
</script>

</body>
</html>
