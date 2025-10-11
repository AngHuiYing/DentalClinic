<?php
session_start();
include '../includes/db.php'; // 连接数据库
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// ---------------------------
// 確保 appointments 有 queue_number 欄位（如果沒有就新增）
// ---------------------------
$check_col_sql = "SELECT COLUMN_NAME 
                  FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'appointments' 
                    AND COLUMN_NAME = 'queue_number'";
$col_res = $conn->query($check_col_sql);
if ($col_res && $col_res->num_rows == 0) {
    // 若沒有這個欄位，嘗試新增（需要有 ALTER 權限）
    $alter_sql = "ALTER TABLE appointments ADD COLUMN queue_number INT NULL";
    $conn->query($alter_sql);
    // 不強制處理錯誤，若失敗請用 DB 客戶端手動新增
}

// 获取所有医生，供选择
$doctor_list = $conn->query("SELECT id, name, specialty FROM doctors ORDER BY name ASC");

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // sanitize minimal
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $patient_name = trim($_POST['patient_name'] ?? '');
    $patient_phone = trim($_POST['patient_phone'] ?? '');
    $patient_email = trim($_POST['patient_email'] ?? '');
    $doctor_id = isset($_POST['doctor_id']) && $_POST['doctor_id'] !== '' ? intval($_POST['doctor_id']) : null;
    $message = !empty($_POST['message']) ? trim($_POST['message']) : null;

    // basic validation
    if (!$patient_name || !$patient_phone || !$patient_email || !$appointment_date || !$appointment_time) {
        echo "<script>alert('Please fill required fields.'); window.history.back();</script>";
        exit;
    }

    // 如果選了醫生：檢查時間衝突 ±1.5 小時
    if ($doctor_id) {
        $check_sql = "
            SELECT * FROM appointments
            WHERE doctor_id = ? 
              AND appointment_date = ?
              AND status != 'rejected'
              AND (
                  TIMEDIFF(appointment_time, ?) BETWEEN '-01:30:00' AND '01:30:00'
              )
        ";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
        // ...existing code...
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            echo "<script>
                alert('This time slot conflicts with another booking (within 1.5 hours). Please choose a later time.');
                window.location.href = 'manage_appointments.php';
            </script>";
            exit;
        }

        // 檢查 unavailable_slots
        $check_unavail_sql = "SELECT * FROM unavailable_slots 
                              WHERE doctor_id = ? AND date = ?
                              AND ? >= from_time AND ? < to_time";
        $check_unavail_stmt = $conn->prepare($check_unavail_sql);
        $check_unavail_stmt->bind_param("isss", $doctor_id, $appointment_date, $appointment_time, $appointment_time);
        $check_unavail_stmt->execute();
        $unavail_result = $check_unavail_stmt->get_result();

        if ($unavail_result->num_rows > 0) {
            echo "<script>
                alert('This time slot is unavailable for the selected doctor. Please choose another one.');
                window.location.href = 'manage_appointments.php';
            </script>";
            exit;
        }
    }

    // -------------------------------------
    // 插入新預約（doctor_id 可能為 NULL）
    // 狀態這裏改為 'confirmed' 或 'pending' 視需求，我保留 Confirmed（與你原來一致）
    // -------------------------------------
    $sql = "INSERT INTO appointments 
        (doctor_id, appointment_date, appointment_time, status, patient_name, patient_phone, patient_email, message, created_at) 
        VALUES (?, ?, ?, 'Confirmed', ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    // 當 doctor_id 為 null 時，bind_param 不能直接用 null 於 i 型別 — 用變通
    if ($doctor_id === null) {
        // bind a NULL as string? easier: use NULL placeholder via separate query building
        // but here we'll bind with special handling: set doctor_id to NULL via 'i' with null -> needs mysqli_stmt::bind_param will convert to ''
        // safer: use separate query when doctor_id is null
        $stmt = $conn->prepare("INSERT INTO appointments 
            (doctor_id, appointment_date, appointment_time, status, patient_name, patient_phone, patient_email, message, created_at) 
            VALUES (NULL, ?, ?, 'Confirmed', ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssss", $appointment_date, $appointment_time, $patient_name, $patient_phone, $patient_email, $message);
    } else {
        $stmt->bind_param("issssss", $doctor_id, $appointment_date, $appointment_time, $patient_name, $patient_phone, $patient_email, $message);
    }

    if ($stmt->execute()) {
        // 取得剛插入的 appointment id
        $appointment_id = $stmt->insert_id;

        // -------------------------
        // 產生 queue_number（同一天、同一醫生的順序）
        // 若 doctor_id 為 null，則以 doctor_id IS NULL 群組
        // 這裡採簡單方法：COUNT(*) 當前日期+醫生 => 會包含剛插入的 row，因此正確
        // -------------------------
        if ($doctor_id === null) {
            $q_sql = "SELECT COUNT(*) AS cnt FROM appointments WHERE appointment_date = ? AND doctor_id IS NULL";
            $q_stmt = $conn->prepare($q_sql);
            $q_stmt->bind_param("s", $appointment_date);
        } else {
            $q_sql = "SELECT COUNT(*) AS cnt FROM appointments WHERE appointment_date = ? AND doctor_id = ?";
            $q_stmt = $conn->prepare($q_sql);
            $q_stmt->bind_param("si", $appointment_date, $doctor_id);
        }
        $q_stmt->execute();
        $q_res = $q_stmt->get_result();
        $q_row = $q_res->fetch_assoc();
        $queue_number = intval($q_row['cnt']); // count 包含剛插入的 row

        // 更新該 appointment 的 queue_number 欄位
        $update_sql = "UPDATE appointments SET queue_number = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $queue_number, $appointment_id);
        $update_stmt->execute();

        // -------------------------
        // 檢查是否需要為病人建立帳號（若沒有）
        // -------------------------
        $user_exists = false;
        $check_sql = "SELECT id FROM users WHERE email = ? LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $patient_email);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            $user_exists = true;
        }

        // 儲存將寄的郵件內容（先空）
        $account_plain_password = null;

        if (!$user_exists) {
            // 建立 patient 帳號
            $account_plain_password = bin2hex(random_bytes(4)); // 8 字元
            $hashed_password = password_hash($account_plain_password, PASSWORD_DEFAULT);
            $role = "patient";

            $insert_sql = "INSERT INTO users (name, email, phone, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("sssss", $patient_name, $patient_email, $patient_phone, $hashed_password, $role);
            $insert_stmt->execute();

            // reset token
            $token = bin2hex(random_bytes(32));
            $expires_at = date("Y-m-d H:i:s", strtotime("+1 hour"));

            $reset_sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
            $reset_stmt = $conn->prepare($reset_sql);
            $reset_stmt->bind_param("sss", $patient_email, $token, $expires_at);
            $reset_stmt->execute();

            $reset_link = "http://localhost/Dental_Clinic/patient/reset_password.php?token=" . $token;

            // 寄送帳號建立信（含臨時密碼與 reset link）
            $mailAccount = new PHPMailer(true);
            try {
                $mailAccount->isSMTP();
                $mailAccount->Host = 'smtp.gmail.com';
                $mailAccount->SMTPAuth = true;
                $mailAccount->Username = 'huiyingsyzz@gmail.com';
                $mailAccount->Password = 'exjs cyot yibs cgya'; // 建議用 APP Password
                $mailAccount->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mailAccount->Port = 587;

                $mailAccount->setFrom('huiyingsyzz@gmail.com', 'Green Life Dental Clinic');
                $mailAccount->addAddress($patient_email);

                $mailAccount->isHTML(true);
                $mailAccount->Subject = "Welcome - Your Patient Account Created";
                $mailAccount->Body = "
                    <p>Dear " . htmlspecialchars($patient_name) . ",</p>
                    <p>Your patient account has been created successfully. Below are your details:</p>
                    <ul>
                        <li><b>Name:</b> " . htmlspecialchars($patient_name) . "</li>
                        <li><b>Email:</b> " . htmlspecialchars($patient_email) . "</li>
                        <li><b>Phone:</b> " . htmlspecialchars($patient_phone) . "</li>
                        <li><b>Temporary Password:</b> " . htmlspecialchars($account_plain_password) . "</li>
                    </ul>
                    <p>Please reset your password using the link below (valid for 1 hour):</p>
                    <p><a href='$reset_link'>$reset_link</a></p>
                    <p>Thank you,<br>Green Life Dental Clinic</p>
                ";
                $mailAccount->send();
            } catch (Exception $e) {
                error_log("Account email error: {$mailAccount->ErrorInfo}");
            }
        }

        // -------------------------
        // 寄送預約成功提醒信（含日期/時間/號碼/醫生）
        // -------------------------
        // 取得醫生名稱（如果有）
        $doctor_name = 'No preference';
        if ($doctor_id) {
            $dstmt = $conn->prepare("SELECT name FROM doctors WHERE id = ? LIMIT 1");
            $dstmt->bind_param("i", $doctor_id);
            $dstmt->execute();
            $dres = $dstmt->get_result();
            if ($drow = $dres->fetch_assoc()) {
                $doctor_name = $drow['name'];
            }
        }

        $mailConfirm = new PHPMailer(true);
        try {
            $mailConfirm->isSMTP();
            $mailConfirm->Host = 'smtp.gmail.com';
            $mailConfirm->SMTPAuth = true;
            $mailConfirm->Username = 'huiyingsyzz@gmail.com';
            $mailConfirm->Password = 'exjs cyot yibs cgya'; // 建議用 APP Password
            $mailConfirm->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mailConfirm->Port = 587;

            $mailConfirm->setFrom('huiyingsyzz@gmail.com', 'Green Life Dental Clinic');
            $mailConfirm->addAddress($patient_email);

            $mailConfirm->isHTML(true);
            $mailConfirm->Subject = "Appointment Confirmed - Green Life Dental Clinic";

            $mailConfirm->Body = "
                <p>Dear " . htmlspecialchars($patient_name) . ",</p>
                <p>Your appointment has been successfully booked. Here are the details:</p>
                <ul>
                    <li><b>Date:</b> " . htmlspecialchars($appointment_date) . "</li>
                    <li><b>Time:</b> " . htmlspecialchars($appointment_time) . "</li>
                    <li><b>Dentist:</b> " . htmlspecialchars($doctor_name) . "</li>
                    <li><b>Your queue number for that day:</b> " . htmlspecialchars($queue_number) . "</li>
                </ul>
                <p>Please arrive 10 minutes before your scheduled time. If you cannot make it, please notify us.</p>
                <p>Thank you,<br>Green Life Dental Clinic</p>
            ";
            $mailConfirm->send();
        } catch (Exception $e) {
            error_log("Confirmation email error: {$mailConfirm->ErrorInfo}");
        }

        // 最後回覆使用者
        echo "<script>alert('Appointment booked successfully! A confirmation email has been sent.'); window.location.href='manage_appointments.php';</script>";
        exit;
    } else {
        echo "<script>alert('Failed to book appointment. Please try again.'); window.history.back();</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📅 Add New Appointment - Green Life Dental Clinic</title>
    
    <!-- Bootstrap 5.3.0 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            /* Medical Clinic Color Scheme */
            --clinic-primary: #0ea5e9;
            --clinic-secondary: #22d3ee;
            --clinic-success: #10b981;
            --clinic-warning: #f59e0b;
            --clinic-danger: #ef4444;
            --clinic-dark: #1f2937;
            --clinic-light: #f8fafc;
            --clinic-background: linear-gradient(135deg, #e0f7fa 0%, #e1f5fe 50%, #f3e5f5 100%);
        }

        body {
            background: var(--clinic-background);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--clinic-dark);
            min-height: 100vh;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
            padding: 2rem 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: white !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .page-title i {
            color: white !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-top: 0.5rem;
        }

        /* Main Card */
        .appointment-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid rgba(14, 165, 233, 0.1);
            overflow: hidden;
            max-width: 800px;
            margin: 0 auto;
        }

        .card-header-clinic {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .card-header-clinic h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        /* Form Sections */
        .form-section {
            padding: 2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--clinic-dark);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--clinic-primary);
        }

        .section-icon {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        /* Form Controls */
        .form-label-clinic {
            font-weight: 600;
            color: var(--clinic-dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control-clinic {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        .form-control-clinic:focus {
            border-color: var(--clinic-primary);
            box-shadow: 0 0 0 0.25rem rgba(14, 165, 233, 0.15);
            outline: none;
            background: white;
        }

        .form-select-clinic {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        .form-select-clinic:focus {
            border-color: var(--clinic-primary);
            box-shadow: 0 0 0 0.25rem rgba(14, 165, 233, 0.15);
            outline: none;
            background: white;
        }

        /* Buttons */
        .btn-clinic {
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-clinic-primary {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
        }

        .btn-clinic-primary:hover {
            background: linear-gradient(135deg, #0284c7, #0891b2);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(14, 165, 233, 0.4);
            color: white;
        }

        .btn-clinic-secondary {
            background: linear-gradient(135deg, #6b7280, #9ca3af);
            color: white;
        }

        .btn-clinic-secondary:hover {
            background: linear-gradient(135deg, #4b5563, #6b7280);
            transform: translateY(-2px);
            color: white;
        }

        /* Special Input Features */
        .input-group-clinic {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .input-icon {
            color: var(--clinic-primary);
            font-size: 1.2rem;
        }

        /* Time Slot Styling */
        .time-slot-disabled {
            background-color: #f1f5f9 !important;
            color: #94a3b8 !important;
        }

        /* Enhanced time option styling */
        .form-select option:disabled {
            background-color: #f8fafc !important;
            color: #6b7280 !important;
            font-style: italic;
        }

        /* Red disabled (conflicting appointments) */
        .form-select option[data-status="conflict"] {
            background-color: #fef2f2 !important;
            color: #dc2626 !important;
            font-weight: 500;
        }

        /* Orange disabled (unavailable slots) */
        .form-select option[data-status="unavailable"] {
            background-color: #fff7ed !important;
            color: #ea580c !important;
            font-weight: 500;
        }

        /* Gray disabled (past time) */
        .form-select option[data-status="past"] {
            background-color: #f9fafb !important;
            color: #9ca3af !important;
            font-style: italic;
        }

        /* Time selection help text */
        .time-status-legend {
            margin-top: 0.75rem;
            padding: 1rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }

        .status-item:last-child {
            margin-bottom: 0;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .status-dot.available {
            background: var(--clinic-success);
        }

        .status-dot.conflict {
            background: #dc2626;
        }

        .status-dot.unavailable {
            background: #ea580c;
        }

        .status-dot.past {
            background: #9ca3af;
        }

        /* Enhanced Doctor Selection Styles */
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .doctor-option {
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            background: #fafbfc;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .doctor-option:hover {
            border-color: var(--clinic-primary);
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(14, 165, 233, 0.15);
        }

        .doctor-option.selected {
            border-color: var(--clinic-success);
            background: rgba(16, 185, 129, 0.05);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.2);
        }

        .doctor-option.selected .selection-indicator {
            opacity: 1;
            transform: scale(1);
        }

        .doctor-card-content {
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            position: relative;
        }

        .doctor-option .doctor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin: 0;
            flex-shrink: 0;
            overflow: hidden;
            border: 3px solid white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .doctor-option .doctor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .doctor-option .doctor-avatar .avatar-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
            border-radius: 50%;
        }

        .no-preference-avatar {
            background: linear-gradient(135deg, var(--clinic-secondary), var(--clinic-success)) !important;
        }

        .doctor-info {
            flex: 1;
            min-width: 0;
        }

        .doctor-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--clinic-dark);
            margin: 0 0 0.5rem 0;
        }

        .doctor-specialty {
            color: var(--clinic-primary);
            font-weight: 600;
            font-size: 1rem;
            margin: 0 0 0.75rem 0;
            background: rgba(14, 165, 233, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            display: inline-block;
        }

        .doctor-experience {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
        }

        .doctor-experience i {
            color: var(--clinic-secondary);
        }

        .doctor-bio {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.5;
            margin: 0;
        }

        .selection-indicator {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 30px;
            height: 30px;
            background: var(--clinic-success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.3s ease;
        }

        .no-preference {
            border: 2px dashed var(--clinic-secondary) !important;
            background: rgba(34, 211, 238, 0.05) !important;
        }

        .no-preference:hover {
            border-color: var(--clinic-success) !important;
            background: rgba(16, 185, 129, 0.1) !important;
        }

        .no-preference.selected {
            border-color: var(--clinic-success) !important;
            background: rgba(16, 185, 129, 0.05) !important;
        }

        /* Help Text */
        .help-text {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Action Buttons */
        .action-buttons {
            padding: 2rem;
            background: #f8fafc;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        /* Required Field Indicator */
        .required {
            color: var(--clinic-danger);
            font-weight: bold;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .appointment-card {
                margin: 0 1rem;
            }
            
            .form-section {
                padding: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .doctors-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .doctor-card-content {
                padding: 1rem;
                flex-direction: column;
                text-align: center;
            }
            
            .doctor-option .doctor-avatar {
                width: 60px;
                height: 60px;
                margin: 0 auto 1rem;
            }
            
            .doctor-info {
                min-width: auto;
            }
            
            .selection-indicator {
                top: 0.5rem;
                right: 0.5rem;
                width: 25px;
                height: 25px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">
                        <i class="bi bi-calendar-plus me-3"></i>
                        Add New Appointment
                    </h1>
                    <p class="page-subtitle">📋 Schedule a new patient appointment with automated confirmations</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="manage_appointments.php" class="btn btn-clinic btn-clinic-secondary">
                        <i class="bi bi-arrow-left me-2"></i>
                        Back to Management
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="padding-bottom: 3rem;">
        <div class="appointment-card">
            <!-- Card Header -->
            <div class="card-header-clinic">
                <h3>
                    <i class="bi bi-person-plus-fill me-2"></i>
                    📅 New Appointment Booking
                </h3>
                <p class="mb-0 mt-2 opacity-90">Fill in the patient details and appointment schedule below</p>
            </div>

            <form method="POST" id="appointmentForm">
                <!-- Patient Information Section -->
                <div class="form-section">
                    <div class="section-title">
                        <div class="section-icon">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <span>👤 Patient Information</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-clinic">
                                <i class="bi bi-person-fill input-icon"></i>
                                Patient Name <span class="required">*</span>
                            </label>
                            <input type="text" name="patient_name" class="form-control form-control-clinic" 
                                   placeholder="Enter full name" required>
                            <div class="help-text">
                                <i class="bi bi-info-circle"></i>
                                Enter the patient's full legal name
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label-clinic">
                                <i class="bi bi-telephone-fill input-icon"></i>
                                Phone Number <span class="required">*</span>
                            </label>
                            <input type="tel" name="patient_phone" class="form-control form-control-clinic" 
                                   placeholder="e.g., +60123456789" required>
                            <div class="help-text">
                                <i class="bi bi-info-circle"></i>
                                Include country code for international numbers
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label-clinic">
                            <i class="bi bi-envelope-fill input-icon"></i>
                            Email Address <span class="required">*</span>
                        </label>
                        <input type="email" name="patient_email" class="form-control form-control-clinic" 
                               placeholder="patient@example.com" required>
                        <div class="help-text">
                            <i class="bi bi-info-circle"></i>
                            Confirmation email will be sent to this address
                        </div>
                    </div>
                </div>

                <!-- Doctor Assignment Section -->
                <div class="form-section">
                    <div class="section-title">
                        <div class="section-icon">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <span>👨‍⚕️ Doctor Assignment</span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label-clinic">
                            <i class="bi bi-person-heart input-icon"></i>
                            Preferred Dentist (Optional)
                        </label>
                        
                        <!-- Hidden input to store selected doctor ID -->
                        <input type="hidden" name="doctor_id" id="selected_doctor_id" value="">
                        
                        <!-- No Preference Option -->
                        <div class="doctor-option no-preference" data-doctor-id="" onclick="selectDoctor(this)">
                            <div class="doctor-card-content">
                                <div class="doctor-avatar no-preference-avatar">
                                    <i class="bi bi-shuffle"></i>
                                </div>
                                <div class="doctor-info">
                                    <h4 class="doctor-name">No Preference</h4>
                                    <p class="doctor-specialty">Admin will assign the best available doctor</p>
                                    <p class="doctor-bio">Our administrative team will assign you to the most suitable dentist based on your needs and availability.</p>
                                </div>
                                <div class="selection-indicator">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Doctor Cards Grid -->
                        <div class="doctors-grid">
                            <?php
                            // Re-query doctors for detailed information
                            $doctor_query = "SELECT id, name, specialty, bio, experience, image FROM doctors ORDER BY name ASC";
                            $doctor_result = $conn->query($doctor_query);
                            
                            while ($doctor = $doctor_result->fetch_assoc()): 
                            ?>
                            <div class="doctor-option" data-doctor-id="<?= $doctor['id'] ?>" onclick="selectDoctor(this)">
                                <div class="doctor-card-content">
                                    <div class="doctor-avatar">
                                        <?php if (!empty($doctor['image'])): ?>
                                            <img src="../<?= htmlspecialchars($doctor['image']) ?>" 
                                                 alt="Dr. <?= htmlspecialchars($doctor['name']) ?>"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="avatar-fallback" style="display: none;">
                                                <?= strtoupper(substr($doctor['name'], 0, 2)) ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="avatar-fallback">
                                                <?= strtoupper(substr($doctor['name'], 0, 2)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="doctor-info">
                                        <h4 class="doctor-name">Dr. <?= htmlspecialchars($doctor['name']) ?></h4>
                                        <p class="doctor-specialty"><?= htmlspecialchars($doctor['specialty']) ?></p>
                                        <div class="doctor-experience">
                                            <i class="bi bi-mortarboard"></i>
                                            <span><?= htmlspecialchars($doctor['experience']) ?> years of experience</span>
                                        </div>
                                        <p class="doctor-bio"><?= htmlspecialchars(substr($doctor['bio'], 0, 120)) ?><?= strlen($doctor['bio']) > 120 ? '...' : '' ?></p>
                                    </div>
                                    <div class="selection-indicator">
                                        <i class="bi bi-check-circle-fill"></i>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <div class="help-text">
                            <i class="bi bi-info-circle"></i>
                            Leave unselected for automatic assignment by admin
                        </div>
                    </div>
                </div>

                <!-- Appointment Details Section -->
                <div class="form-section">
                    <div class="section-title">
                        <div class="section-icon">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <span>📅 Appointment Schedule</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-clinic">
                                <i class="bi bi-calendar3 input-icon"></i>
                                Appointment Date <span class="required">*</span>
                            </label>
                            <input type="date" name="appointment_date" id="appointment_date" 
                                   class="form-control form-control-clinic" required>
                            <div class="help-text">
                                <i class="bi bi-info-circle"></i>
                                Select a date from today onwards
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label-clinic">
                                <i class="bi bi-clock-fill input-icon"></i>
                                Appointment Time <span class="required">*</span>
                            </label>
                            <select name="appointment_time" id="appointment_time" 
                                    class="form-select form-select-clinic" required>
                                <option value="">⏰ Select time slot</option>
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
                                <option value="17:30">05:30 PM</option>
                            </select>
                            <div class="time-status-legend">
                                <div class="status-item">
                                    <div class="status-dot available"></div>
                                    <span>Available time slots</span>
                                </div>
                                <div class="status-item">
                                    <div class="status-dot conflict"></div>
                                    <span>Conflicting with other appointments</span>
                                </div>
                                <div class="status-item">
                                    <div class="status-dot unavailable"></div>
                                    <span>Doctor unavailable</span>
                                </div>
                                <div class="status-item">
                                    <div class="status-dot past"></div>
                                    <span>Past time (today only)</span>
                                </div>
                            </div>
                            <div class="help-text">
                                <i class="bi bi-info-circle"></i>
                                Unavailable slots will be disabled automatically
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Information Section -->
                <div class="form-section">
                    <div class="section-title">
                        <div class="section-icon">
                            <i class="bi bi-chat-text"></i>
                        </div>
                        <span>💬 Additional Information</span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label-clinic">
                            <i class="bi bi-chat-square-text input-icon"></i>
                            Message / Special Requests (Optional)
                        </label>
                        <textarea name="message" class="form-control form-control-clinic" rows="4" 
                                  placeholder="Describe the dental issue, special requests, or any important information for the dentist..."></textarea>
                        <div class="help-text">
                            <i class="bi bi-info-circle"></i>
                            Help us prepare better for your appointment
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button type="submit" class="btn btn-clinic btn-clinic-primary btn-lg">
                        <i class="bi bi-check-circle-fill"></i>
                        Confirm Appointment
                    </button>
                    <a href="manage_appointments.php" class="btn btn-clinic btn-clinic-secondary btn-lg">
                        <i class="bi bi-x-circle"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    
    <!-- Bootstrap 5.3.0 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Enhanced Appointment Booking JavaScript -->
    <script>
        function getMalaysiaTimeNow() {
            const now = new Date();
            const utc = now.getTime() + now.getTimezoneOffset() * 60000;
            return new Date(utc + (8 * 60 * 60000)); // GMT+8
        }

        const appointmentDate = document.getElementById("appointment_date");
        const appointmentTime = document.getElementById("appointment_time");
        const selectedDoctorId = document.getElementById("selected_doctor_id");
        const form = document.getElementById("appointmentForm");

        // Doctor selection functionality
        function selectDoctor(element) {
            // Remove selection from all doctor options
            document.querySelectorAll('.doctor-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selection to clicked option
            element.classList.add('selected');
            
            // Update hidden input value
            const doctorId = element.getAttribute('data-doctor-id');
            selectedDoctorId.value = doctorId;
            
            // Update time options when doctor changes
            updateTimeOptions();
        }

        async function updateTimeOptions() {
            const selectedDate = appointmentDate.value;
            const doctorId = selectedDoctorId ? selectedDoctorId.value : "";

            const malaysiaNow = getMalaysiaTimeNow();
            const options = appointmentTime.querySelectorAll("option");
            appointmentTime.value = "";

            // Reset all options to available state
            options.forEach(opt => { 
                if (opt.value !== "") {
                    opt.disabled = false;
                    opt.removeAttribute('data-status');
                    opt.classList.remove('time-slot-disabled');
                    // Reset styles
                    opt.style.color = "";
                    opt.style.backgroundColor = "";
                    opt.style.fontStyle = "";
                    opt.style.fontWeight = "";
                    
                    // Reset text to original time display
                    const timeValue = opt.value;
                    if (timeValue) {
                        const [hour, minute] = timeValue.split(':');
                        const hourNum = parseInt(hour);
                        const ampm = hourNum >= 12 ? 'PM' : 'AM';
                        const hour12 = hourNum === 0 ? 12 : hourNum > 12 ? hourNum - 12 : hourNum;
                        opt.textContent = `${hour12.toString().padStart(2, '0')}:${minute} ${ampm}`;
                    }
                }
            });

            // Step 1: Disable past times for today with gray styling
            if (selectedDate) {
                options.forEach(option => {
                    if (option.value === "") return;
                    const optionTime = new Date(`${selectedDate}T${option.value}:00`);

                    if (new Date(selectedDate).toDateString() === malaysiaNow.toDateString()) {
                        const nowPlus30 = new Date(malaysiaNow.getTime() + 30 * 60000);
                        if (optionTime <= nowPlus30) {
                            option.disabled = true;
                            option.setAttribute('data-status', 'past');
                            option.textContent += " (Past)";
                            option.style.color = "#9ca3af";
                            option.style.backgroundColor = "#f9fafb";
                            option.style.fontStyle = "italic";
                        }
                    }
                });
            }

            // Step 2: Check availability with backend
            if (doctorId && selectedDate) {
                try {
                    const res = await fetch(`../get_unavailable_slots.php?doctor_id=${doctorId}&date=${selectedDate}`);
                    const data = await res.json();

                    // Disable booked slots (±90 minutes) with red styling
                    if (data.booked && Array.isArray(data.booked)) {
                        data.booked.forEach(timeStr => {
                            const [h, m] = timeStr.split(":");
                            const bookedTime = new Date(`${selectedDate}T${h.padStart(2,'0')}:${m.padStart(2,'0')}:00`);

                            options.forEach(option => {
                                if (option.value === "") return;
                                const optTime = new Date(`${selectedDate}T${option.value}:00`);

                                const diff = Math.abs((optTime - bookedTime) / (1000 * 60)); // minutes
                                if (diff <= 90) {
                                    option.disabled = true;
                                    option.setAttribute('data-status', 'conflict');
                                    if (!option.textContent.includes("(Conflict)")) {
                                        option.textContent += " (Conflict)";
                                    }
                                    option.style.color = "#dc2626";
                                    option.style.backgroundColor = "#fef2f2";
                                    option.style.fontWeight = "500";
                                }
                            });
                        });
                    }

                    // Disable unavailable time ranges with orange styling
                    if (data.unavailable && Array.isArray(data.unavailable)) {
                        data.unavailable.forEach(slot => {
                            const from = new Date(`${selectedDate}T${slot.from_time}`);
                            const to = new Date(`${selectedDate}T${slot.to_time}`);

                            options.forEach(option => {
                                if (option.value === "") return;
                                const optTime = new Date(`${selectedDate}T${option.value}:00`);
                                if (optTime >= from && optTime < to) {
                                    option.disabled = true;
                                    option.setAttribute('data-status', 'unavailable');
                                    if (!option.textContent.includes("(Unavailable)")) {
                                        option.textContent += " (Unavailable)";
                                    }
                                    option.style.color = "#ea580c";
                                    option.style.backgroundColor = "#fff7ed";
                                    option.style.fontWeight = "500";
                                }
                            });
                        });
                    }

                } catch (err) {
                    console.error("Error fetching unavailable slots:", err);
                }
            }
        }

        // Form validation and enhancement
        function validateForm(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            let firstInvalidField = null;

            requiredFields.forEach(field => {
                const container = field.closest('.mb-3');
                const existingError = container.querySelector('.error-message');
                
                if (!field.value.trim()) {
                    if (!existingError) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'error-message text-danger mt-1';
                        errorDiv.innerHTML = '<i class="bi bi-exclamation-circle"></i> This field is required';
                        container.appendChild(errorDiv);
                    }
                    field.classList.add('is-invalid');
                    if (!firstInvalidField) firstInvalidField = field;
                    isValid = false;
                } else {
                    if (existingError) existingError.remove();
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                }
            });

            if (!isValid) {
                e.preventDefault();
                if (firstInvalidField) {
                    firstInvalidField.focus();
                    firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                
                // Show error message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show position-fixed';
                alertDiv.style.top = '100px';
                alertDiv.style.right = '20px';
                alertDiv.style.zIndex = '9999';
                alertDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Please fill in all required fields before submitting.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(alertDiv);
                
                setTimeout(() => alertDiv.remove(), 5000);
            }
        }

        // Real-time field validation
        function setupFieldValidation() {
            const fields = form.querySelectorAll('input, select, textarea');
            fields.forEach(field => {
                field.addEventListener('blur', function() {
                    const container = this.closest('.mb-3');
                    const existingError = container.querySelector('.error-message');
                    
                    if (this.hasAttribute('required') && !this.value.trim()) {
                        this.classList.add('is-invalid');
                        if (!existingError) {
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'error-message text-danger mt-1';
                            errorDiv.innerHTML = '<i class="bi bi-exclamation-circle"></i> This field is required';
                            container.appendChild(errorDiv);
                        }
                    } else {
                        this.classList.remove('is-invalid');
                        if (this.value.trim()) this.classList.add('is-valid');
                        if (existingError) existingError.remove();
                    }
                });

                field.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid') && this.value.trim()) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                        const container = this.closest('.mb-3');
                        const existingError = container.querySelector('.error-message');
                        if (existingError) existingError.remove();
                    }
                });
            });
        }

        // Initialize on page load
        window.addEventListener("DOMContentLoaded", () => {
            console.log("Page loaded, initializing...");
            const today = getMalaysiaTimeNow().toISOString().split("T")[0];
            console.log("Today's date:", today);
            appointmentDate.setAttribute("min", today);
            appointmentDate.value = today;
            console.log("Date input value set to:", appointmentDate.value);
            
            // Select "No Preference" by default
            const noPreferenceOption = document.querySelector('.doctor-option.no-preference');
            if (noPreferenceOption) {
                selectDoctor(noPreferenceOption);
            }
            
            updateTimeOptions();
            setupFieldValidation();
        });

        // Event listeners
        appointmentDate.addEventListener("change", function() {
            console.log("Date changed to:", this.value);
            updateTimeOptions();
        });
        
        form.addEventListener("submit", validateForm);

        // Loading state for form submission
        form.addEventListener("submit", function(e) {
            if (!e.defaultPrevented) {
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Processing Appointment...';
                
                // Re-enable after timeout as fallback
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Confirm Appointment';
                }, 5000);
            }
        });
    </script>

</body>
</html>
