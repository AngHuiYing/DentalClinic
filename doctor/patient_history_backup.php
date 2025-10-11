<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../doctor/login.php");
    exit;
}

// 获取医生 ID（doctor_id）
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    die("Doctor record not found!");
}

$doctor_id = $doctor['id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_record_id'])) {
    $record_id = intval($_POST['delete_record_id']);
    $del_stmt = $conn->prepare("DELETE FROM medical_records WHERE id = ?");
    $del_stmt->bind_param("i", $record_id);
    if ($del_stmt->execute()) {
        $redirect_email = isset($_POST['patient_email']) ? urlencode($_POST['patient_email']) : '';
        $msg = "Medical record deleted successfully!";
        header("Location: patient_history.php?patient_email={$redirect_email}&msg=" . urlencode($msg));
        exit;
    } else {
        $error = "Error deleting record: " . $conn->error;
    }
}

// 处理添加病历
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_record'])) {
    $patient_email   = $_POST['patient_email'];
    $chief_complaint = trim($_POST['chief_complaint']);
    $diagnosis       = trim($_POST['diagnosis']);
    $treatment_plan  = trim($_POST['treatment_plan']);
    $prescription    = trim($_POST['prescription']);
    $progress_notes  = trim($_POST['progress_notes']);
    $visit_date      = trim($_POST['visit_date']);

    // 確認該病人是這位醫生的患者（已經被 approve）
    $stmt = $conn->prepare("
        SELECT patient_email 
        FROM appointments 
        WHERE doctor_id = ? AND patient_email = ? AND status = 'confirmed'
    ");
    $stmt->bind_param("is", $doctor_id, $patient_email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo "<script>alert('You are not authorized to add records for this patient.'); window.location.href='patient_records.php';</script>";
        exit;
    }

    // 插入病历记录
    $stmt = $conn->prepare("
        INSERT INTO medical_records 
        (patient_email, doctor_id, chief_complaint, diagnosis, treatment_plan, prescription, progress_notes, visit_date, report_generated, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, NOW())
    ");
    $stmt->bind_param("sissssss", 
        $patient_email, 
        $doctor_id, 
        $chief_complaint, 
        $diagnosis, 
        $treatment_plan, 
        $prescription, 
        $progress_notes, 
        $visit_date
    );
    if ($stmt->execute()) {
        $medical_record_id = $stmt->insert_id;
        
        // 儲存服務
        $selected_services = [];
        $total_billing = 0;
        if (!empty($_POST['services_used'])) {
            $services = is_array($_POST['services_used']) 
                ? $_POST['services_used'] 
                : explode(',', $_POST['services_used']);

            $service_stmt = $conn->prepare(
                "INSERT INTO medical_record_services (medical_record_id, service_id) VALUES (?, ?)"
            );
            $service_stmt->bind_param("ii", $medical_record_id, $service_id);

            foreach ($services as $service_id) {
                $service_id = (int)$service_id;
                $service_stmt->execute();
                
                // 获取服务信息用于报告
                $service_info_stmt = $conn->prepare("SELECT name, price FROM services WHERE id = ?");
                $service_info_stmt->bind_param("i", $service_id);
                $service_info_stmt->execute();
                $service_info = $service_info_stmt->get_result()->fetch_assoc();
                if ($service_info) {
                    $selected_services[] = $service_info['name'] . ' (RM' . number_format($service_info['price'], 2) . ')';
                    $total_billing += $service_info['price'];
                }
            }
        }
        
        // 重定向到独立的报告页面
        $redirect_url = "view_report.php?patient_email=" . urlencode($patient_email) . 
                       "&record_id=" . $medical_record_id;
        header("Location: " . $redirect_url);
        exit;
    } else {
        echo "<script>alert('Error adding record. Try again.');</script>";
    }
}

// 获取当前医生的病人列表（今天有預約的）
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$today = date('Y-m-d');

if ($search) {
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            a.patient_email, 
            a.patient_name, 
            a.patient_phone,
            CASE 
                WHEN mr.id IS NOT NULL THEN 1 
                ELSE 0 
            END as has_record_today,
            mr.id as record_id,
            mr.visit_date,
            mr.created_at
        FROM appointments a
        LEFT JOIN medical_records mr ON a.patient_email = mr.patient_email 
            AND mr.doctor_id = ? 
            AND DATE(mr.visit_date) = ?
        WHERE a.doctor_id = ? 
          AND a.status = 'confirmed' 
          AND a.patient_email IS NOT NULL
          AND a.appointment_date = ?
          AND (a.patient_name LIKE ? OR a.patient_email LIKE ? OR a.patient_phone LIKE ?)
        ORDER BY has_record_today ASC, a.patient_name ASC
    ");
    $searchLike = "%$search%";
    $stmt->bind_param("ississss", $doctor_id, $today, $doctor_id, $today, $searchLike, $searchLike, $searchLike);
} else {
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            a.patient_email, 
            a.patient_name, 
            a.patient_phone,
            CASE 
                WHEN mr.id IS NOT NULL THEN 1 
                ELSE 0 
            END as has_record_today,
            mr.id as record_id,
            mr.visit_date,
            mr.created_at
        FROM appointments a
        LEFT JOIN medical_records mr ON a.patient_email = mr.patient_email 
            AND mr.doctor_id = ? 
            AND DATE(mr.visit_date) = ?
        WHERE a.doctor_id = ? 
          AND a.status = 'confirmed' 
          AND a.patient_email IS NOT NULL
          AND a.appointment_date = ?
        ORDER BY has_record_today ASC, a.patient_name ASC
    ");
    $stmt->bind_param("isis", $doctor_id, $today, $doctor_id, $today);
}
$stmt->execute();
$patients = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient History - Medical Records</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #4a9396;
            --accent-color: #84c69b;
            --light-bg: #f8fafe;
            --white: #ffffff;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --border-color: #e8ecef;
            --gray-50: #f8f9fa;
            --gray-300: #dee2e6;
            --gray-600: #6c757d;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 8px 15px rgba(0, 0, 0, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--light-bg) 0%, #e8f4f8 100%);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow);
            margin-top: 2rem;
            margin-bottom: 2rem;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color), var(--accent-color));
        }

        h2 {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 2.2rem;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
        }

        h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: var(--accent-color);
            border-radius: 2px;
        }

        h3 {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(44, 90, 160, 0.1);
        }

        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0.875rem 1.25rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafcff;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
            outline: none;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .btn {
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
            color: white;
        }

        .btn-secondary {
            background: var(--text-secondary);
            color: white;
            border: none;
        }

        .btn-secondary:hover {
            background: var(--text-primary);
            transform: translateY(-2px);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, var(--accent-color) 100%);
            color: white;
            font-size: 1.1rem;
            padding: 1rem 2rem;
            border: none;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
            color: white;
        }

        .input-group {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .input-group .form-control {
            border-right: none;
            border-radius: 0;
            background: var(--white);
        }

        .input-group .btn {
            border-radius: 0;
            border-left: none;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1.25rem 1.5rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        .alert-warning {
            background: rgba(243, 156, 18, 0.1);
            color: var(--warning-color);
            border-left: 4px solid var(--warning-color);
        }

        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .mb-3 {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: rgba(248, 250, 254, 0.6);
            border-radius: 15px;
            border: 1px solid rgba(44, 90, 160, 0.08);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        #service-point-group {
            background: rgba(248, 250, 254, 0.8);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
            border: 2px dashed rgba(44, 90, 160, 0.2);
        }

        .service-point {
            display: inline-block;
            background: linear-gradient(135deg, #e3f0fa 0%, #f0f8ff 100%);
            color: var(--primary-color);
            border: 2px solid #b3d8f7;
            border-radius: 25px;
            padding: 0.75rem 1.5rem;
            margin: 0.5rem 0.5rem 0.5rem 0;
            cursor: pointer;
            transition: all 0.3s ease;
            user-select: none;
            font-weight: 500;
            position: relative;
            overflow: hidden;
            font-size: 0.95rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .service-point::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s;
        }

        .service-point:hover::before {
            left: 100%;
        }

        .service-point:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(44, 90, 160, 0.2);
        }

        .service-point.selected {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-color: var(--primary-color);
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(44, 90, 160, 0.3);
        }

        .text-muted {
            color: var(--text-secondary) !important;
            font-style: italic;
        }

        .patient-info-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            position: relative;
            overflow: hidden;
        }

        .patient-info-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .patient-info-section h3 {
            color: white;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            font-size: 1.8rem;
            position: relative;
        }

        /* Patient Details Section */
        .patient-details-section {
            background: var(--white);
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .patient-details-section h3 {
            color: var(--primary-color);
            border-bottom: 2px solid rgba(44, 90, 160, 0.1);
            margin-bottom: 1.5rem;
        }

        .patient-details-section .form-control[readonly],
        .patient-details-section .form-control[disabled] {
            background-color: var(--gray-50);
            border-color: var(--gray-300);
            color: var(--gray-600);
        }

        .patient-details-section .text-warning {
            font-size: 0.85rem;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
                margin: 1rem;
            }

            h2 {
                font-size: 1.8rem;
            }

            .service-point {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
                margin: 0.3rem 0.3rem 0.3rem 0;
            }

            .btn {
                padding: 0.65rem 1.25rem;
                font-size: 0.95rem;
            }

            .mb-3 {
                padding: 1rem;
            }
        }

        /* 動畫效果 */
        .container {
            animation: fadeInUp 0.8s ease-out;
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

        .form-control:focus {
            animation: inputFocus 0.3s ease-out;
        }

        @keyframes inputFocus {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.02);
            }
            100% {
                transform: scale(1);
            }
        }

        
        /* 已存在記錄的特殊樣式 */
        .existing-record-section {
            background: rgba(248, 250, 254, 0.8);
            border: 2px solid rgba(44, 90, 160, 0.2);
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }
        
        .record-status-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--success-color), var(--accent-color));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .record-readonly .form-control {
            background: var(--gray-50);
            border-color: var(--border-color);
            cursor: default;
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-5" style="padding-top: 70px;">
    <h2>Add Patient Medical</h2>
    <!-- 搜索病人 -->
<form method="GET" class="mb-3">
    <div class="input-group">
        <input type="text" name="search" class="form-control" 
               placeholder="Search by Name, Email, or Phone" 
               value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
        <button type="submit" class="btn btn-primary">Search</button>
        <a href="patient_history.php" class="btn btn-secondary">Reset</a>
    </div>
</form>
    <form method="GET">
        <div class="mb-3">
            <label class="form-label">Select Patient:</label>
            <?php if ($patients->num_rows === 0) { ?>
                <div class="alert alert-warning">No patients with appointments today.</div>
            <?php } else { ?>
            <select name="patient_email" class="form-control" required>
                <option value="">Select Patient</option>
                <?php while ($row = $patients->fetch_assoc()) { ?>
                    <option value="<?= htmlspecialchars($row['patient_email']); ?>" <?= (isset($_GET['patient_email']) && $_GET['patient_email'] == $row['patient_email']) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($row['patient_name']); ?> (<?= htmlspecialchars($row['patient_email']); ?>) - <?= htmlspecialchars($row['patient_phone']); ?>
                        <?php if ($row['has_record_today'] == 1): ?>
                            ✅ [Record Added Today at <?= date('g:i A', strtotime($row['created_at'])); ?>]
                        <?php else: ?>
                            📝 [No Record Yet]
                        <?php endif; ?>
                    </option>
                <?php } ?>
            </select>
            <div class="mt-2">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    <strong>Note:</strong> Today's patients - ✅ = Already has medical record, 📝 = Ready for new record
                    <br>
                    You can only add/view records for patients with confirmed appointments today.
                </small>
            </div>
            <?php } ?>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-search me-1"></i>View/Add Record for Selected Patient
        </button>
    </form>

    <?php 
    if (isset($_GET['patient_email'])) {
        $patient_email = $_GET['patient_email'];

        // 確認醫生是否有權限
        $stmt = $conn->prepare("
            SELECT patient_name 
            FROM appointments
            WHERE doctor_id = ? AND patient_email = ? AND status = 'confirmed'
            LIMIT 1
        ");
        $stmt->bind_param("is", $doctor_id, $patient_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            echo "<div class='alert alert-danger mt-3'>You do not have permission to view this patient's records.</div>";
            exit;
        }

        $patient_name = $result->fetch_assoc()['patient_name'];
        
        // 檢查今天是否已有醫療記錄
        $today_record_stmt = $conn->prepare("
            SELECT id, chief_complaint, diagnosis, treatment_plan, prescription, progress_notes, visit_date, created_at 
            FROM medical_records 
            WHERE patient_email = ? AND doctor_id = ? AND DATE(visit_date) = ?
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $today_record_stmt->bind_param("sis", $patient_email, $doctor_id, $today);
        $today_record_stmt->execute();
        $today_record_result = $today_record_stmt->get_result();
        $existing_record = $today_record_result->fetch_assoc();
        
        // 获取病人病历记录
        $stmt = $conn->prepare("
        SELECT mr.id, mr.visit_date, mr.created_at, d.name as doctor_name, 
           mr.chief_complaint, mr.diagnosis, mr.treatment_plan, 
           mr.prescription, mr.progress_notes
        FROM medical_records mr
        JOIN doctors d ON mr.doctor_id = d.id
        WHERE mr.patient_email = ?
        ORDER BY mr.created_at DESC
        ");
        $stmt->bind_param("s", $patient_email);
        $stmt->execute();
        $records = $stmt->get_result();
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Dental Medical Report</title>
                <style>
                    body {
                        font-family: 'Inter', Arial, sans-serif;
                        line-height: 1.6;
                        margin: 0;
                        padding: 20px;
                        background: #f8f9fa;
                    }
                    .report-container {
                        max-width: 800px;
                        margin: 0 auto;
                        background: white;
                        padding: 40px;
                        border-radius: 10px;
                        box-shadow: 0 0 20px rgba(0,0,0,0.1);
                    }
                    .header {
                        text-align: center;
                        border-bottom: 3px solid #2c5aa0;
                        padding-bottom: 20px;
                        margin-bottom: 30px;
                    }
                    .header h1 {
                        color: #2c5aa0;
                        margin: 0;
                        font-size: 2.5rem;
                        font-weight: 700;
                    }
                    .header .clinic-info {
                        color: #666;
                        margin-top: 10px;
                    }
                    .report-meta {
                        text-align: right;
                        margin-bottom: 30px;
                        font-size: 0.9rem;
                        color: #666;
                    }
                    h2 {
                        color: #2c5aa0;
                        border-bottom: 2px solid #84c69b;
                        padding-bottom: 10px;
                        margin-top: 30px;
                        margin-bottom: 20px;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 20px;
                    }
                    th, td {
                        padding: 12px;
                        text-align: left;
                        border-bottom: 1px solid #ddd;
                    }
                    th {
                        background-color: #f8f9fa;
                        font-weight: 600;
                        color: #2c5aa0;
                        width: 200px;
                    }
                    td {
                        background-color: white;
                    }
                    .signature-section {
                        margin-top: 50px;
                        padding-top: 30px;
                        border-top: 1px solid #ddd;
                    }
                    .signature-line {
                        border-bottom: 1px solid #333;
                        width: 300px;
                        display: inline-block;
                        margin-left: 20px;
                    }
                    .print-btn {
                        background: #2c5aa0;
                        color: white;
                        padding: 12px 30px;
                        border: none;
                        border-radius: 5px;
                        cursor: pointer;
                        margin: 20px 0;
                        font-size: 1rem;
                    }
                    .print-btn:hover {
                        background: #1e3f73;
                    }
                    .back-btn {
                        background: #6c757d;
                        color: white;
                        padding: 10px 20px;
                        border: none;
                        border-radius: 5px;
                        cursor: pointer;
                        margin-left: 10px;
                        text-decoration: none;
                        display: inline-block;
                    }
                    @media print {
                        .print-btn, .back-btn { display: none; }
                        body { background: white; padding: 0; }
                        .report-container { 
                            box-shadow: none; 
                            margin: 0; 
                            border-radius: 0;
                            padding: 15px;
                            font-size: 0.85rem;
                            max-width: 100%;
                        }
                        /* 隐藏所有非报告内容 */
                        .navbar, nav, header, .container:not(.report-container) { display: none !important; }
                        
                        /* 优化标题 */
                        .header h1 {
                            font-size: 1.8rem !important;
                            margin-bottom: 0.5rem;
                        }
                        .header .clinic-info {
                            font-size: 0.9rem;
                            margin-top: 0.5rem;
                        }
                        
                        /* 紧凑表格 */
                        table {
                            margin-bottom: 15px !important;
                            font-size: 0.8rem;
                        }
                        th, td {
                            padding: 8px 10px !important;
                            border: 1px solid #ddd !important;
                        }
                        th {
                            background-color: #f8f9fa !important;
                            color: #2c5aa0 !important;
                            width: 150px !important;
                            font-size: 0.75rem !important;
                        }
                        
                        /* 紧凑标题 */
                        h2 {
                            font-size: 1.1rem !important;
                            margin-top: 20px !important;
                            margin-bottom: 10px !important;
                            border-bottom: 1px solid #ddd !important;
                        }
                        
                        /* 元数据 */
                        .report-meta {
                            font-size: 0.75rem !important;
                            margin-bottom: 15px !important;
                        }
                        
                        /* 签名区域 */
                        .signature-section {
                            margin-top: 25px !important;
                            padding-top: 15px !important;
                            page-break-inside: avoid;
                        }
                        .signature-section h2 {
                            font-size: 1rem !important;
                        }
                        .signature-section p {
                            font-size: 0.75rem !important;
                            margin: 8px 0 !important;
                        }
                        .signature-line {
                            width: 200px !important;
                            margin-left: 15px !important;
                        }
                        
                        /* 页面设置 */
                        @page {
                            size: A4;
                            margin: 15mm;
                        }
                        
                        /* 防止分页 */
                        table { page-break-inside: avoid; }
                        tr { page-break-inside: avoid; }
                        html, body { margin: 0; padding: 0; }
                    }
                    
                    /* 移动端响应式设计 */
                    @media screen and (max-width: 768px) {
                        body {
                            padding: 10px;
                            font-size: 14px;
                            line-height: 1.4;
                        }
                        
                        .report-container {
                            padding: 20px;
                            margin: 0;
                            border-radius: 8px;
                            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                            max-width: 100%;
                            overflow-x: hidden;
                        }
                        
                        .header {
                            padding-bottom: 15px;
                            margin-bottom: 20px;
                        }
                        
                        .header h1 {
                            font-size: 1.8rem;
                            margin-bottom: 8px;
                        }
                        
                        .header .clinic-info {
                            font-size: 0.8rem;
                            margin-top: 8px;
                        }
                        
                        .report-meta {
                            font-size: 0.8rem;
                            margin-bottom: 20px;
                            text-align: left;
                        }
                        
                        h2 {
                            font-size: 1.1rem;
                            margin-top: 20px;
                            margin-bottom: 15px;
                            padding-bottom: 8px;
                        }
                        
                        /* 移动端表格优化 */
                        table {
                            font-size: 0.8rem;
                            border: 1px solid #ddd;
                            border-radius: 6px;
                            overflow: hidden;
                            width: 100%;
                            table-layout: fixed;
                        }
                        
                        th {
                            font-size: 0.8rem;
                            padding: 8px 6px;
                            width: 35%;
                            word-wrap: break-word;
                            background-color: #f8f9fa;
                            border-right: 1px solid #ddd;
                        }
                        
                        td {
                            font-size: 0.8rem;
                            padding: 8px 6px;
                            word-wrap: break-word;
                            word-break: break-word;
                            hyphens: auto;
                            border-right: 1px solid #ddd;
                        }
                        
                        /* 签名区域移动端优化 */
                        .signature-section {
                            margin-top: 30px;
                            padding-top: 20px;
                        }
                        
                        .signature-section h2 {
                            font-size: 1rem;
                            margin-bottom: 15px;
                        }
                        
                        .signature-section p {
                            font-size: 0.8rem;
                            margin: 10px 0;
                            line-height: 1.4;
                        }
                        
                        .signature-line {
                            width: 150px;
                            margin-left: 10px;
                        }
                        
                        /* 按钮移动端优化 */
                        .print-btn, .back-btn {
                            font-size: 0.9rem;
                            padding: 10px 20px;
                            margin: 10px 5px;
                            border-radius: 6px;
                            display: inline-block;
                            text-align: center;
                            min-width: 120px;
                        }
                        
                        /* 移动端文字统一 */
                        .report-container * {
                            max-width: 100%;
                            box-sizing: border-box;
                        }
                        
                        /* 长文本处理 */
                        .report-container td {
                            overflow-wrap: break-word;
                            word-wrap: break-word;
                            -ms-word-break: break-all;
                            word-break: break-word;
                            -ms-hyphens: auto;
                            -moz-hyphens: auto;
                            -webkit-hyphens: auto;
                            hyphens: auto;
                        }
                    }
                    
                    /* 超小屏幕优化 */
                    @media screen and (max-width: 480px) {
                        .report-container {
                            padding: 15px;
                        }
                        
                        .header h1 {
                            font-size: 1.5rem;
                        }
                        
                        h2 {
                            font-size: 1rem;
                        }
                        
                        table {
                            font-size: 0.75rem;
                        }
                        
                        th {
                            font-size: 0.75rem;
                            padding: 6px 4px;
                        }
                        
                        td {
                            font-size: 0.75rem;
                            padding: 6px 4px;
                        }
                        
                        .report-meta {
                            font-size: 0.75rem;
                        }
                        
                        .signature-section p {
                            font-size: 0.75rem;
                        }
                        
                        .print-btn, .back-btn {
                            font-size: 0.85rem;
                            padding: 8px 16px;
                            margin: 8px 3px;
                            display: block;
                            width: calc(50% - 6px);
                            box-sizing: border-box;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="report-container">
                    <div class="header">
                        <h1>🦷 Dental Medical Report</h1>
                        <div class="clinic-info">
                            <strong>Green Life Dental Clinic</strong><br>
                            Professional Dental Care Services
                        </div>
                    </div>
                    
                    <div class="report-meta">
                        <strong>Report Generated:</strong> <?= date('F j, Y \a\t g:i A') ?><br>
                        <strong>Medical Record ID:</strong> #<?= str_pad($record_data['id'], 6, '0', STR_PAD_LEFT) ?>
                    </div>

                    <h2>👤 Patient Information</h2>
                    <table>
                        <tr><th>Full Name</th><td><?= htmlspecialchars($user_data['name'] ?? 'N/A') ?></td></tr>
                        <tr><th>Date of Birth</th><td><?= $user_data['date_of_birth'] ? date('F j, Y', strtotime($user_data['date_of_birth'])) : 'Not specified' ?></td></tr>
                        <tr><th>Gender</th><td><?= htmlspecialchars(ucfirst($user_data['gender'] ?? 'Not specified')) ?></td></tr>
                        <tr><th>Email</th><td><?= htmlspecialchars($user_data['email']) ?></td></tr>
                        <tr><th>Phone</th><td><?= htmlspecialchars($user_data['phone'] ?? 'N/A') ?></td></tr>
                        <tr><th>Medical Record No.</th><td>MRN-<?= str_pad($record_data['id'], 6, '0', STR_PAD_LEFT) ?></td></tr>
                    </table>

                    <h2>🏥 Visit Information</h2>
                    <table>
                        <tr><th>Visit Date</th><td><?= date('F j, Y', strtotime($record_data['visit_date'])) ?></td></tr>
                        <tr><th>Attending Doctor</th><td>Dr. <?= htmlspecialchars($record_data['doctor_name']) ?></td></tr>
                        <tr><th>Chief Complaint</th><td><?= htmlspecialchars($record_data['chief_complaint']) ?></td></tr>
                        <tr><th>Clinical Diagnosis</th><td><?= htmlspecialchars($record_data['diagnosis']) ?></td></tr>
                        <tr><th>Treatment Plan</th><td><?= htmlspecialchars($record_data['treatment_plan'] ?: 'None specified') ?></td></tr>
                        <tr><th>Prescription</th><td><?= htmlspecialchars($record_data['prescription'] ?: 'None prescribed') ?></td></tr>
                        <tr><th>Progress Notes</th><td><?= htmlspecialchars($record_data['progress_notes'] ?: 'No additional notes') ?></td></tr>
                        <tr><th>Services Performed</th><td><?= !empty($services_list) ? implode('<br>', $services_list) : 'No services recorded' ?></td></tr>
                        <tr><th>Total Billing Amount</th><td><strong>RM <?= number_format($total_billing, 2) ?></strong></td></tr>
                    </table>

                    <div class="signature-section">
                        <h2>✍️ Authorization & Signature</h2>
                        <p>
                            <br>
                            <br>
                            <strong>Doctor's Signature:</strong> 
                            <span class="signature-line"></span>
                        </p>
                        <br>
                        <p>
                            <strong>Date:</strong> 
                            <span class="signature-line"></span>
                        </p>
                        <br><br>
                        <p style="font-size: 0.9rem; color: #666;">
                            <em>This report is generated electronically and contains confidential medical information. 
                            Please handle with appropriate care and maintain patient privacy.</em>
                        </p>
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px;">
                        <button onclick="window.print()" class="print-btn">
                            🖨️ Print Report
                        </button>
                        <a href="patient_records.php" class="back-btn">
                            ← Go to Patient Records
                        </a>
                    </div>
                </div>
                
                <script>
                    // 自动显示打印对話框（可選）
                    // window.onload = function() { window.print(); }
                </script>
            </body>
            </html>
            <?php
            exit;
        }

        // 获取病人病历记录
        $stmt = $conn->prepare("
        SELECT mr.id, mr.visit_date, mr.created_at, d.name as doctor_name, 
           mr.chief_complaint, mr.diagnosis, mr.treatment_plan, 
           mr.prescription, mr.progress_notes
        FROM medical_records mr
        JOIN doctors d ON mr.doctor_id = d.id
        WHERE mr.patient_email = ?
        ORDER BY mr.created_at DESC
        ");
        $stmt->bind_param("s", $patient_email);
        $stmt->execute();
        $records = $stmt->get_result();
    ?>
    <div class="patient-info-section">
        <h3><i class="fas fa-user-circle me-2"></i>Medical Record for <?= htmlspecialchars($patient_name); ?></h3>
        <p style="margin: 0; font-size: 1.1rem; opacity: 0.9;">
            <i class="fas fa-envelope me-2"></i><?= htmlspecialchars($patient_email); ?>
        </p>
    </div>
    
    <!-- Patient Information Form -->
    <div class="patient-details-section mt-4">
        <h3><i class="fas fa-user-edit me-2"></i>Patient Information</h3>
        <div class="row">
            <?php
            // 获取患者详细信息
            $patient_stmt = $conn->prepare("SELECT name, email, phone, gender, date_of_birth FROM users WHERE email = ?");
            $patient_stmt->bind_param("s", $patient_email);
            $patient_stmt->execute();
            $patient_info = $patient_stmt->get_result()->fetch_assoc();
            ?>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-user me-2"></i>Full Name:</label>
                    <input type="text" id="patient_name" class="form-control" 
                           value="<?= htmlspecialchars($patient_info['name'] ?? '') ?>" 
                           <?= empty($patient_info['name']) ? '' : 'readonly' ?>>
                    <?php if (empty($patient_info['name'])): ?>
                    <small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Missing - Please update</small>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-envelope me-2"></i>Email:</label>
                    <input type="email" class="form-control" 
                           value="<?= htmlspecialchars($patient_info['email'] ?? $patient_email) ?>" readonly>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-birthday-cake me-2"></i>Date of Birth:</label>
                    <input type="date" id="patient_dob" class="form-control" 
                           value="<?= htmlspecialchars($patient_info['date_of_birth'] ?? '') ?>" 
                           <?= empty($patient_info['date_of_birth']) ? '' : 'readonly' ?>>
                    <?php if (empty($patient_info['date_of_birth'])): ?>
                    <small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Missing - Please update</small>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-venus-mars me-2"></i>Gender:</label>
                    <select id="patient_gender" class="form-control" 
                            <?= empty($patient_info['gender']) ? '' : 'disabled' ?>>
                        <option value="">Select Gender</option>
                        <option value="male" <?= ($patient_info['gender'] ?? '') === 'male' ? 'selected' : '' ?>>
                            Male
                        </option>
                        <option value="female" <?= ($patient_info['gender'] ?? '') === 'female' ? 'selected' : '' ?>>
                            Female
                        </option>
                        <option value="other" <?= ($patient_info['gender'] ?? '') === 'other' ? 'selected' : '' ?>>
                            Other
                        </option>
                    </select>
                    <?php if (empty($patient_info['gender'])): ?>
                    <small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Missing - Please update</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-phone me-2"></i>Contact Number:</label>
                    <input type="tel" id="patient_phone" class="form-control" 
                           value="<?= htmlspecialchars($patient_info['phone'] ?? '') ?>" 
                           <?= empty($patient_info['phone']) ? '' : 'readonly' ?>>
                    <?php if (empty($patient_info['phone'])): ?>
                    <small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Missing - Please update</small>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php 
            $missing_fields = [];
            if (empty($patient_info['name'])) $missing_fields[] = 'name';
            if (empty($patient_info['date_of_birth'])) $missing_fields[] = 'date_of_birth';
            if (empty($patient_info['gender'])) $missing_fields[] = 'gender';
            if (empty($patient_info['phone'])) $missing_fields[] = 'phone';
            
            if (!empty($missing_fields)): 
            ?>
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Incomplete Patient Information:</strong> Some fields are missing. 
                    You can update them now for better record keeping.
                    <button type="button" class="btn btn-sm btn-warning ms-2" onclick="updatePatientInfo()">
                        <i class="fas fa-edit me-1"></i>Update Information
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($existing_record): ?>
        <!-- 顯示今天已存在的醫療記錄 -->
        <div class="patient-info-section mt-4">
            <h3><i class="fas fa-file-medical me-2"></i>Today's Medical Record</h3>
            <p style="margin: 0; font-size: 1.1rem; opacity: 0.9;">
                <i class="fas fa-clock me-2"></i>Record created at: <?= date('M j, Y g:i A', strtotime($existing_record['created_at'])); ?>
            </p>
        </div>
        
        <div class="existing-record-section mt-4 record-readonly">
            <div class="record-status-badge">
                <i class="fas fa-check-circle me-2"></i>Medical Record Already Added Today
            </div>
            <h3><i class="fas fa-clipboard-list me-2"></i>Medical Record Details</h3>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-calendar me-2"></i>Visit Date:</label>
                        <input type="date" class="form-control" value="<?= htmlspecialchars($existing_record['visit_date']) ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-comment-medical me-2"></i>Chief Complaint:</label>
                        <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($existing_record['chief_complaint']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-diagnoses me-2"></i>Diagnosis:</label>
                        <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($existing_record['diagnosis']) ?></textarea>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-clipboard-list me-2"></i>Treatment Plan:</label>
                        <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($existing_record['treatment_plan']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-pills me-2"></i>Prescription:</label>
                        <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($existing_record['prescription']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-sticky-note me-2"></i>Progress Notes:</label>
                        <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($existing_record['progress_notes']) ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="view_report.php?patient_email=<?= urlencode($patient_email) ?>&record_id=<?= $existing_record['id'] ?>" 
                   class="btn btn-info me-3" target="_blank">
                    <i class="fas fa-eye me-2"></i>View Full Report
                </a>
                
                <form method="POST" style="display: inline;" 
                      onsubmit="return confirm('Are you sure you want to delete this medical record? This action cannot be undone.')">
                    <input type="hidden" name="delete_record_id" value="<?= $existing_record['id'] ?>">
                    <input type="hidden" name="patient_email" value="<?= htmlspecialchars($patient_email) ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete Record
                    </button>
                </form>
            </div>
            
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Note:</strong> This patient already has a medical record for today. 
                You can view the full report. Or you can delete the record to create a new one if you have any updates.
            </div>
        </div>
        
    <?php else: ?>
        <!-- 顯示添加新記錄的表單 -->
        <h3 class="mt-4"><i class="fas fa-plus-circle me-2"></i>Add New Medical Record</h3>
        <form method="POST" class="mt-3">
        <input type="hidden" name="patient_email" value="<?= htmlspecialchars($patient_email); ?>">

        <div class="mb-3">
            <label class="form-label"><i class="fas fa-calendar me-2"></i>Visit Date:</label>
            <input type="date" name="visit_date" class="form-control" value="<?= date('Y-m-d'); ?>" readonly required>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fas fa-comment-medical me-2"></i>Chief Complaint:</label>
            <textarea name="chief_complaint" class="form-control" rows="2" placeholder="Patient's main concern or reason for visit..." required></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fas fa-diagnoses me-2"></i>Diagnosis:</label>
            <textarea name="diagnosis" class="form-control" rows="3" placeholder="Clinical findings and diagnosis..." required></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fas fa-clipboard-list me-2"></i>Treatment Plan:</label>
            <textarea name="treatment_plan" class="form-control" rows="3" placeholder="Recommended treatments and procedures..."></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fas fa-pills me-2"></i>Prescription:</label>
            <textarea name="prescription" class="form-control" rows="3" placeholder="Medications and dosage instructions..."></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fas fa-sticky-note me-2"></i>Progress Notes:</label>
            <textarea name="progress_notes" class="form-control" rows="3" placeholder="Additional notes and observations..."></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fas fa-tooth me-2"></i>Services Used:</label>
            <div id="service-point-group">
                <?php 
                $services = $conn->query("SELECT * FROM services ORDER BY name ASC");
                while($srv = $services->fetch_assoc()): ?>
                    <span class="service-point" data-id="<?= $srv['id'] ?>">
                        <i class="fas fa-tooth me-2"></i><?= htmlspecialchars($srv['name']) ?> 
                        <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 10px; font-size: 0.85rem; margin-left: 0.5rem;">
                            RM<?= number_format($srv['price'],2) ?>
                        </span>
                    </span>
                <?php endwhile; ?>
            </div>
            <input type="hidden" name="services_used" id="services_used_hidden" required>
            <div style="margin-top: 1rem; text-align: center;">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>Click to select services, click again to deselect. At least one service is required.
                </small>
            </div>
        </div>
        <style>
        .service-point {
            display: inline-block;
            background: #e3f0fa;
            color: #1e90ff;
            border: 1px solid #b3d8f7;
            border-radius: 16px;
            padding: 6px 16px;
            margin: 4px 6px 4px 0;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            user-select: none;
        }
        .service-point.selected {
            background: #1e90ff;
            color: #fff;
            border-color: #1e90ff;
        }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const points = document.querySelectorAll('.service-point');
            const hidden = document.getElementById('services_used_hidden');
            let selected = [];
            points.forEach(function(point) {
                point.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    if (this.classList.contains('selected')) {
                        this.classList.remove('selected');
                        selected = selected.filter(sid => sid !== id);
                    } else {
                        this.classList.add('selected');
                        selected.push(id);
                    }
                    hidden.value = selected.join(',');
                });
            });
            // 表單送出時同步 name
            const form = document.querySelector('form[method="POST"]');
            if(form) {
                form.addEventListener('submit', function(e) {
                    if(selected.length === 0) {
                        alert('Please select at least one service.');
                        e.preventDefault();
                    } else {
                        // 轉成多個 input
                        form.querySelectorAll('input[name="services_used[]"]').forEach(i=>i.remove());
                        selected.forEach(function(id) {
                            const inp = document.createElement('input');
                            inp.type = 'hidden';
                            inp.name = 'services_used[]';
                            inp.value = id;
                            form.appendChild(inp);
                        });
                    }
                });
            }
        });
        </script>

        <script>
        // 更新患者信息函数
        function updatePatientInfo() {
            const patientEmail = '<?= htmlspecialchars($patient_email) ?>';
            const name = document.getElementById('patient_name').value.trim();
            const dob = document.getElementById('patient_dob').value;
            const gender = document.getElementById('patient_gender').value;
            const phone = document.getElementById('patient_phone').value.trim();
            
            if (!name || !dob || !gender || !phone) {
                alert('Please fill in all missing fields before updating.');
                return;
            }
            
            // 创建FormData对象
            const formData = new FormData();
            formData.append('action', 'update_patient_info');
            formData.append('patient_email', patientEmail);
            formData.append('name', name);
            formData.append('date_of_birth', dob);
            formData.append('gender', gender);
            formData.append('phone', phone);
            
            // 发送AJAX请求
            fetch('update_patient_info.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.trim() === 'success') {
                    alert('Patient information updated successfully!');
                    location.reload(); // 重新加载页面以显示更新后的信息
                } else {
                    alert('Error updating patient information: ' + data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating patient information.');
            });
        }
        </script>

        <div style="text-center: center; margin-top: 3rem;">
            <button type="submit" name="add_record" class="btn btn-success">
                <i class="fas fa-save me-2"></i>Add Medical Record
            </button>
        </div>
    </form>
    <?php endif; ?>
    <?php } ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
