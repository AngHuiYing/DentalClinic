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
        WHERE doctor_id = ? AND patient_email = ? AND (status = 'confirmed' OR status = 'completed')
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
          AND (a.status = 'confirmed' OR a.status = 'completed')
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
          AND (a.status = 'confirmed' OR a.status = 'completed')
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

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
                margin: 1rem;
                font-size: 0.8rem;
            }

            h2 {
                font-size: 1.8rem;
            }

            .service-point {
                padding: 0.6rem 1.2rem;
                font-size: 0.8rem;
                margin: 0.3rem 0.3rem 0.3rem 0;
            }

            .btn {
                padding: 0.65rem 1.25rem;
                font-size: 0.8rem;
            }

            .mb-3 {
                padding: 1rem;
            }

            .form-control, .form-select {
                font-size: 0.8rem;
                padding: 0.75rem 1rem;
            }

            .form-label {
                font-size: 0.8rem;
            }

            h3 {
                font-size: 1.2rem;
            }

            .patient-info-section, .patient-details-section, .existing-record-section {
                padding: 1.5rem;
                margin: 1.5rem 0;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 1rem;
                margin: 0.5rem;
                font-size: 0.75rem;
            }

            .service-point {
                font-size: 0.75rem;
                padding: 0.5rem 1rem;
            }

            .btn {
                font-size: 0.75rem;
                padding: 0.6rem 1rem;
            }

            .form-control, .form-select {
                font-size: 0.75rem;
                padding: 0.7rem 0.9rem;
            }

            .form-label {
                font-size: 0.75rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            h3 {
                font-size: 1rem;
            }
        }

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
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Notice:</strong> You can only view and add medical records for patients who have confirmed appointments for today (<?= date('F j, Y') ?>). 
                    Please ensure patients have scheduled appointments before attempting to create medical records.
                </div>
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
            WHERE doctor_id = ? AND patient_email = ? AND (status = 'confirmed' OR status = 'completed')
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
                    <label class="form-label">Full Name:</label>
                    <input type="text" id="patient_name" class="form-control" 
                           value="<?= htmlspecialchars($patient_info['name'] ?? '') ?>" 
                           placeholder="Enter patient's full name"
                           <?= !empty($patient_info['name']) ? 'readonly' : '' ?>>
                    <?php if (empty($patient_info['name'])): ?>
                        <small class="text-warning">⚠️ Please update patient's name</small>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Date of Birth:</label>
                    <input type="date" id="patient_dob" class="form-control" 
                           value="<?= htmlspecialchars($patient_info['date_of_birth'] ?? '') ?>"
                           <?= !empty($patient_info['date_of_birth']) ? 'readonly' : '' ?>>
                    <?php if (empty($patient_info['date_of_birth'])): ?>
                        <small class="text-warning">⚠️ Please update patient's date of birth</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Gender:</label>
                    <select id="patient_gender" class="form-control" 
                            <?= !empty($patient_info['gender']) ? 'disabled' : '' ?>>
                        <option value="">Select Gender</option>
                        <option value="male" <?= ($patient_info['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= ($patient_info['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= ($patient_info['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                    <?php if (empty($patient_info['gender'])): ?>
                        <small class="text-warning">⚠️ Please update patient's gender</small>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Email:</label>
                    <input type="email" class="form-control" 
                           value="<?= htmlspecialchars($patient_info['email'] ?? $patient_email) ?>" readonly>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Phone Number:</label>
                    <input type="tel" id="patient_phone" class="form-control" 
                           value="<?= htmlspecialchars($patient_info['phone'] ?? '') ?>" 
                           placeholder="Enter phone number"
                           <?= !empty($patient_info['phone']) ? 'readonly' : '' ?>>
                    <?php if (empty($patient_info['phone'])): ?>
                        <small class="text-warning">⚠️ Please update patient's phone number</small>
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
                <div class="text-center mt-3">
                    <button type="button" class="btn btn-warning" onclick="updatePatientInfo()">
                        <i class="fas fa-save me-2"></i>Update Missing Patient Information
                    </button>
                    <small class="d-block mt-2 text-muted">
                        Complete patient information is required before adding medical records.
                    </small>
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
                <i class="fas fa-calendar me-2"></i>Record created on <?= date('F j, Y \a\t g:i A', strtotime($existing_record['created_at'])); ?>
            </p>
        </div>
        
        <div class="existing-record-section mt-4 record-readonly">
            <div class="record-status-badge">
                <i class="fas fa-check-circle me-1"></i>Today's Record Complete
            </div>
            <h3><i class="fas fa-clipboard-list me-2"></i>Medical Record Details</h3>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Chief Complaint:</label>
                        <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($existing_record['chief_complaint']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Diagnosis:</label>
                        <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($existing_record['diagnosis']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Treatment Plan:</label>
                        <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($existing_record['treatment_plan']) ?></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Prescription:</label>
                        <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($existing_record['prescription']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Progress Notes:</label>
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
                You can view the full report or delete this record to create a new one.
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
                $services_stmt = $conn->prepare("SELECT id, name, price FROM services ORDER BY name");
                $services_stmt->execute();
                $services = $services_stmt->get_result();
                while ($service = $services->fetch_assoc()) {
                    echo "<span class='service-point' data-service-id='{$service['id']}'>{$service['name']} (RM" . number_format($service['price'], 2) . ")</span>";
                }
                ?>
            </div>
            <input type="hidden" name="services_used" id="services_used_hidden" required>
            <div style="margin-top: 1rem; text-align: center;">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>Click on services to select them for this visit
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
                    const serviceId = this.dataset.serviceId;
                    const index = selected.indexOf(serviceId);
                    
                    if (index > -1) {
                        selected.splice(index, 1);
                        this.classList.remove('selected');
                    } else {
                        selected.push(serviceId);
                        this.classList.add('selected');
                    }
                    
                    hidden.value = selected.join(',');
                });
            });

            const form = document.querySelector('form[method="POST"]');
            if(form) {
                form.addEventListener('submit', function(e) {
                    if (selected.length === 0) {
                        e.preventDefault();
                        alert('Please select at least one service for this visit.');
                        return false;
                    }
                    
                    const servicesInput = document.createElement('input');
                    servicesInput.type = 'hidden';
                    servicesInput.name = 'services_used';
                    servicesInput.value = selected.join(',');
                    this.appendChild(servicesInput);
                });
            }
        });
        </script>

        <script>
        function updatePatientInfo() {
            const patientEmail = '<?= htmlspecialchars($patient_email) ?>';
            const name = document.getElementById('patient_name').value.trim();
            const dob = document.getElementById('patient_dob').value;
            const gender = document.getElementById('patient_gender').value;
            const phone = document.getElementById('patient_phone').value.trim();
            
            if (!name || !dob || !gender || !phone) {
                alert('Please fill in all patient information fields.');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_patient_info');
            formData.append('patient_email', patientEmail);
            formData.append('name', name);
            formData.append('date_of_birth', dob);
            formData.append('gender', gender);
            formData.append('phone', phone);
            
            fetch('update_patient_info.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert('Patient information updated successfully!');
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating patient information.');
            });
        }
        </script>

        <div style="text-center: center; margin-top: 3rem;">
            <button type="submit" name="add_record" class="btn btn-success">
                <i class="fas fa-save me-2"></i>Save Medical Record & Generate Report
            </button>
        </div>
    </form>
    <?php endif; ?>
    <?php } ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>