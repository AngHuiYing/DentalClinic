<?php
session_start();
include "db.php"; // 连接数据库
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Kuala_Lumpur');

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
              AND status = 'confirmed'
              AND (
                  TIMEDIFF(appointment_time, ?) BETWEEN '-01:30:00' AND '01:30:00'
              )
        ";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            echo "<script>
                alert('This time slot conflicts with another booking (within 1.5 hours). Please choose a later time.');
                window.location.href = 'book_appointment.php';
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
                window.location.href = 'book_appointment.php';
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
        echo "<script>alert('Appointment booked successfully! A confirmation email has been sent.'); window.location.href='index.php';</script>";
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
    <title>Book Appointment - Dental Clinic</title>
    
    <!-- Bootstrap 5.3.0 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6.4.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--clinic-light) 0%, #e6f4ea 50%, var(--clinic-warm) 100%);
            color: var(--clinic-text);
            line-height: 1.6;
            min-height: 100vh;
            padding-top: 80px;
            position: relative;
        }

        /* Background decoration */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(45, 90, 160, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(74, 147, 150, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(132, 198, 155, 0.02) 0%, transparent 50%);
            z-index: -1;
            pointer-events: none;
        }

        /* Floating medical elements */
        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .floating-icon {
            position: absolute;
            color: rgba(45, 90, 160, 0.05);
            font-size: 2.5rem;
            animation: float 10s ease-in-out infinite;
        }

        .floating-icon:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; }
        .floating-icon:nth-child(2) { top: 20%; right: 20%; animation-delay: 2s; }
        .floating-icon:nth-child(3) { top: 60%; left: 15%; animation-delay: 4s; }
        .floating-icon:nth-child(4) { top: 70%; right: 10%; animation-delay: 6s; }
        .floating-icon:nth-child(5) { top: 80%; left: 50%; animation-delay: 8s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.05; }
            50% { transform: translateY(-30px) rotate(180deg); opacity: 0.02; }
        }

        .appointment-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .appointment-card {
            background: var(--clinic-white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--clinic-shadow-hover);
            border: 1px solid rgba(45, 90, 160, 0.1);
            position: relative;
            animation: fadeInUp 0.6s ease forwards;
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

        .card-header-custom {
            background: linear-gradient(135deg, var(--clinic-primary) 0%, var(--clinic-secondary) 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .card-header-custom::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
                radial-gradient(circle at 70% 70%, rgba(255, 255, 255, 0.1) 2px, transparent 2px);
            background-size: 40px 40px;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .appointment-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .card-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        .card-body-custom {
            padding: 2rem;
        }

        .appointment-info {
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.1) 0%, rgba(74, 147, 150, 0.1) 100%);
            border: 1px solid rgba(39, 174, 96, 0.2);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .appointment-info i {
            color: var(--clinic-success);
            font-size: 1.2rem;
        }

        .appointment-info p {
            color: var(--clinic-text);
            font-size: 0.95rem;
            font-weight: 500;
            margin: 0;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--clinic-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(45, 90, 160, 0.1);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--clinic-text);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .required {
            color: var(--clinic-danger);
        }

        .input-wrapper {
            position: relative;
        }

        .form-control {
            border: 2px solid #e8ecef;
            border-radius: 12px;
            padding: 1rem 1rem 1rem 3rem;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--clinic-warm);
            height: auto;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--clinic-primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(45, 90, 160, 0.1);
            transform: translateY(-2px);
        }

        .form-select {
            border: 2px solid #e8ecef;
            border-radius: 12px;
            padding: 1rem 1rem 1rem 3rem;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--clinic-warm);
            height: auto;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--clinic-primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(45, 90, 160, 0.1);
            transform: translateY(-2px);
        }

        .form-select option:disabled {
            color: var(--clinic-muted);
            background: #f8f9fa;
            font-style: italic;
        }

        /* Enhanced disabled option styling */
        .form-select option[style*="color: #6b7280"] {
            background-color: #f8fafc !important;
            color: #6b7280 !important;
        }

        .form-select option[style*="color: #e74c3c"] {
            background-color: #fef2f2 !important;
            color: #e74c3c !important;
            font-weight: 500;
        }

        .form-select option[style*="color: #f39c12"] {
            background-color: #fffbeb !important;
            color: #f39c12 !important;
            font-weight: 500;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--clinic-muted);
            font-size: 1.1rem;
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .form-control:focus + .input-icon,
        .form-select:focus + .input-icon {
            color: var(--clinic-primary);
            transform: translateY(-50%) scale(1.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
            padding: 1rem;
        }

        .btn-appointment {
            width: 100%;
            padding: 1.25rem 2rem;
            background: linear-gradient(135deg, var(--clinic-primary) 0%, var(--clinic-secondary) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 1rem;
        }

        .btn-appointment::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-appointment:hover::before {
            left: 100%;
        }

        .btn-appointment:hover {
            transform: translateY(-3px);
            box-shadow: var(--clinic-shadow-hover);
        }

        .time-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .time-slot {
            padding: 0.75rem;
            border: 2px solid #e8ecef;
            border-radius: 8px;
            background: var(--clinic-warm);
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .time-slot:hover {
            border-color: var(--clinic-primary);
            background: rgba(45, 90, 160, 0.1);
            transform: translateY(-2px);
        }

        .time-slot.selected {
            background: var(--clinic-primary);
            color: white;
            border-color: var(--clinic-primary);
        }

        .time-slot.disabled {
            background: #f8f9fa;
            color: var(--clinic-muted);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .time-slot.disabled:hover {
            transform: none;
            border-color: #e8ecef;
        }

        .doctor-card {
            border: 2px solid #e8ecef;
            border-radius: 12px;
            padding: 1rem;
            background: var(--clinic-warm);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .doctor-card:hover {
            border-color: var(--clinic-primary);
            background: rgba(45, 90, 160, 0.05);
            transform: translateY(-2px);
        }

        .doctor-card.selected {
            border-color: var(--clinic-primary);
            background: rgba(45, 90, 160, 0.1);
        }

        .doctor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--clinic-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin: 0 auto 0.5rem;
        }

        /* Enhanced Doctor Selection Styles */
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .doctor-option {
            border: 2px solid #e8ecef;
            border-radius: 16px;
            background: var(--clinic-warm);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .doctor-option:hover {
            border-color: var(--clinic-primary);
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(45, 90, 160, 0.15);
        }

        .doctor-option.selected {
            border-color: var(--clinic-success);
            background: rgba(39, 174, 96, 0.05);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.2);
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
            background: var(--clinic-primary);
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
            background: linear-gradient(135deg, var(--clinic-secondary), var(--clinic-accent)) !important;
        }

        .doctor-info {
            flex: 1;
            min-width: 0;
        }

        .doctor-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--clinic-text);
            margin: 0 0 0.5rem 0;
        }

        .doctor-specialty {
            color: var(--clinic-primary);
            font-weight: 600;
            font-size: 1rem;
            margin: 0 0 0.75rem 0;
            background: rgba(45, 90, 160, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            display: inline-block;
        }

        .doctor-experience {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--clinic-muted);
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
        }

        .doctor-experience i {
            color: var(--clinic-secondary);
        }

        .doctor-bio {
            color: var(--clinic-muted);
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
            background: rgba(74, 147, 150, 0.05) !important;
        }

        .no-preference:hover {
            border-color: var(--clinic-accent) !important;
            background: rgba(132, 198, 155, 0.1) !important;
        }

        .no-preference.selected {
            border-color: var(--clinic-success) !important;
            background: rgba(39, 174, 96, 0.05) !important;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e8ecef;
            z-index: 1;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e8ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--clinic-muted);
            font-weight: 600;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .step.active .step-circle {
            background: var(--clinic-primary);
            color: white;
        }

        .step.completed .step-circle {
            background: var(--clinic-success);
            color: white;
        }

        .step-label {
            font-size: 0.85rem;
            color: var(--clinic-muted);
            text-align: center;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .appointment-container {
                padding: 0 0.5rem;
                margin: 1rem auto;
            }

            .card-header-custom {
                padding: 1.5rem;
            }

            .card-body-custom {
                padding: 1.5rem;
            }

            .card-title {
                font-size: 1.5rem;
            }

            .appointment-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .time-grid {
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                gap: 0.25rem;
            }

            .progress-steps {
                flex-direction: column;
                gap: 1rem;
            }

            .progress-steps::before {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include '../Dental_Clinic/includes/navbar.php'; ?>

    <!-- Floating medical elements -->
    <div class="floating-elements">
        <i class="fas fa-calendar-plus floating-icon"></i>
        <i class="fas fa-tooth floating-icon"></i>
        <i class="fas fa-user-md floating-icon"></i>
        <i class="fas fa-stethoscope floating-icon"></i>
        <i class="fas fa-clock floating-icon"></i>
    </div>

    <div class="appointment-container">
        <div class="appointment-card">
            <div class="card-header-custom">
                <div class="header-content">
                    <div class="appointment-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h1 class="card-title">Book Your Appointment</h1>
                    <p class="card-subtitle">Schedule your visit with our professional dental care team</p>
                </div>
            </div>
            
            <div class="card-body-custom">
                <!-- Progress Steps -->
                <div class="progress-steps">
                    <div class="step active">
                        <div class="step-circle">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="step">
                        <div class="step-circle">2</div>
                        <div class="step-label">Doctor & Date</div>
                    </div>
                    <div class="step">
                        <div class="step-circle">3</div>
                        <div class="step-label">Confirmation</div>
                    </div>
                </div>

                <div class="appointment-info">
                    <i class="fas fa-info-circle"></i>
                    <p>We will contact you within 24 hours to confirm your appointment and provide any additional instructions.</p>
                </div>
                
                <form method="POST" id="appointmentForm">
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Personal Information
                        </h3>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-user"></i>
                                        Full Name <span class="required">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <input type="text" 
                                               name="patient_name" 
                                               class="form-control" 
                                               placeholder="Enter your full name"
                                               required>
                                        <i class="fas fa-user input-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-phone"></i>
                                        Phone Number <span class="required">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <input type="tel" 
                                               name="patient_phone" 
                                               class="form-control" 
                                               placeholder="Enter your phone number"
                                               required>
                                        <i class="fas fa-phone input-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-envelope"></i>
                                        Email Address <span class="required">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <input type="email" 
                                               name="patient_email" 
                                               class="form-control" 
                                               placeholder="Enter your email address"
                                               required>
                                        <i class="fas fa-envelope input-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Doctor Selection Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user-md"></i>
                            Choose Your Dentist
                        </h3>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-stethoscope"></i>
                                Select Your Preferred Doctor (Optional)
                            </label>
                            
                            <!-- Hidden input to store selected doctor ID -->
                            <input type="hidden" name="doctor_id" id="selected_doctor_id" value="">
                            
                            <!-- No Preference Option -->
                            <div class="doctor-option no-preference" data-doctor-id="" onclick="selectDoctor(this)">
                                <div class="doctor-card-content">
                                    <div class="doctor-avatar no-preference-avatar">
                                        <i class="fas fa-random"></i>
                                    </div>
                                    <div class="doctor-info">
                                        <h4 class="doctor-name">No Preference</h4>
                                        <p class="doctor-specialty">Admin will assign the best available doctor</p>
                                        <p class="doctor-bio">Our administrative team will assign you to the most suitable dentist based on your needs and availability.</p>
                                    </div>
                                    <div class="selection-indicator">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Doctor Cards Grid -->
                            <div class="doctors-grid">
                                <?php
                                // Get detailed doctor information
                                $doctor_query = "SELECT id, name, specialty, bio, experience, image FROM doctors ORDER BY name ASC";
                                $doctor_result = $conn->query($doctor_query);
                                
                                while ($doctor = $doctor_result->fetch_assoc()): 
                                ?>
                                <div class="doctor-option" data-doctor-id="<?= $doctor['id'] ?>" onclick="selectDoctor(this)">
                                    <div class="doctor-card-content">
                                        <div class="doctor-avatar">
                                            <?php if (!empty($doctor['image'])): ?>
                                                <img src="<?= htmlspecialchars($doctor['image']) ?>" 
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
                                                <i class="fas fa-graduation-cap"></i>
                                                <span><?= htmlspecialchars($doctor['experience']) ?> years of experience</span>
                                            </div>
                                            <p class="doctor-bio"><?= htmlspecialchars(substr($doctor['bio'], 0, 120)) ?><?= strlen($doctor['bio']) > 120 ? '...' : '' ?></p>
                                        </div>
                                        <div class="selection-indicator">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Date & Time Selection Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-calendar-alt"></i>
                            Date & Time Selection
                        </h3>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-calendar"></i>
                                        Preferred Date <span class="required">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <input type="date" 
                                               name="appointment_date" 
                                               id="appointment_date" 
                                               class="form-control"
                                               required>
                                        <i class="fas fa-calendar input-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-clock"></i>
                                        Preferred Time <span class="required">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select name="appointment_time" id="appointment_time" class="form-select" required>
                                            <option value="">Select time</option>
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
                                        <i class="fas fa-clock input-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Message Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-comment-medical"></i>
                            Additional Information
                        </h3>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-comment-dots"></i>
                                Message (Optional)
                            </label>
                            <textarea name="message" 
                                      class="form-control" 
                                      placeholder="Please describe your dental concern, symptoms, or any special requirements..."
                                      rows="4"></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn-appointment">
                        <i class="fas fa-calendar-check me-2"></i>
                        Confirm Appointment
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Malaysia time functions
        function getMalaysiaTimeNow() {
            const now = new Date();
            const utc = now.getTime() + now.getTimezoneOffset() * 60000;
            return new Date(utc + (8 * 60 * 60000)); // GMT+8
        }

        const appointmentDate = document.getElementById("appointment_date");
        const appointmentTime = document.getElementById("appointment_time");
        const doctorSelect = document.querySelector("select[name='doctor_id']"); // This may be null now
        const appointmentForm = document.getElementById("appointmentForm");
        const selectedDoctorId = document.getElementById("selected_doctor_id");

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
            updateProgress();
        }

        // Progress steps functionality
        function updateProgressSteps(currentStep) {
            const steps = document.querySelectorAll('.step');
            steps.forEach((step, index) => {
                if (index < currentStep) {
                    step.classList.add('completed');
                    step.classList.remove('active');
                } else if (index === currentStep) {
                    step.classList.add('active');
                    step.classList.remove('completed');
                } else {
                    step.classList.remove('active', 'completed');
                }
            });
        }

        // Form validation and progress tracking
        function validatePersonalInfo() {
            const name = document.querySelector('input[name="patient_name"]').value.trim();
            const phone = document.querySelector('input[name="patient_phone"]').value.trim();
            const email = document.querySelector('input[name="patient_email"]').value.trim();
            
            return name && phone && email;
        }

        function validateDateTime() {
            const date = appointmentDate.value;
            const time = appointmentTime.value;
            
            return date && time;
        }

        // Update progress based on form completion
        function updateProgress() {
            if (validatePersonalInfo()) {
                if (validateDateTime()) {
                    updateProgressSteps(2);
                } else {
                    updateProgressSteps(1);
                }
            } else {
                updateProgressSteps(0);
            }
        }

        // Time slot availability checker
        async function updateTimeOptions() {
            const selectedDate = appointmentDate.value;
            const doctorId = selectedDoctorId ? selectedDoctorId.value : "";

            const malaysiaNow = getMalaysiaTimeNow();
            const options = appointmentTime.querySelectorAll("option");
            appointmentTime.value = "";

            // Show loading for time slots
            appointmentTime.disabled = true;

            // 先全部啟用
            options.forEach(opt => { 
                if (opt.value !== "") {
                    opt.disabled = false;
                    opt.style.color = '';
                    opt.style.backgroundColor = '';
                }
            });

            // Step 1: 禁用已過去的時間
            options.forEach(option => {
                if (option.value === "") return;
                const optionTime = new Date(`${selectedDate}T${option.value}:00`);

                if (new Date(selectedDate).toDateString() === malaysiaNow.toDateString()) {
                    const nowPlus30 = new Date(malaysiaNow.getTime() + 30 * 60000);
                    if (optionTime <= nowPlus30) {
                        option.disabled = true;
                        option.style.color = '#6b7280';
                        option.style.backgroundColor = '#f8fafc';
                    }
                }
            });

            // Step 2: 從後端拿已預約 & 不可用時段
            if (doctorId && selectedDate) {
                try {
                    const res = await fetch(`get_unavailable_slots.php?doctor_id=${doctorId}&date=${selectedDate}`);
                    const data = await res.json();

                    // 2a. 已被預約 → 禁用 ±90 分鐘 (1.5小時)
                    data.booked.forEach(timeStr => {
                        const [h, m] = timeStr.split(":");
                        const bookedTime = new Date(`${selectedDate}T${h}:${m}:00`);

                        options.forEach(option => {
                            if (option.value === "") return;
                            const optTime = new Date(`${selectedDate}T${option.value}:00`);

                            const diff = (optTime - bookedTime) / (1000 * 60); // 分鐘
                            if (Math.abs(diff) <= 90) {
                                option.disabled = true; // 禁用 ±90 分鐘 (包含等於90分鐘的時間點)
                                option.style.color = '#e74c3c';
                                option.style.backgroundColor = '#fef2f2';
                            }
                        });
                    });

                    // 2b. 不可用時段 → 整段禁用
                    data.unavailable.forEach(slot => {
                        const from = new Date(`${selectedDate}T${slot.from_time}`);
                        const to   = new Date(`${selectedDate}T${slot.to_time}`);

                        options.forEach(option => {
                            if (option.value === "") return;
                            const optTime = new Date(`${selectedDate}T${option.value}:00`);
                            if (optTime >= from && optTime < to) {
                                option.disabled = true;
                                option.style.color = '#f39c12';
                                option.style.backgroundColor = '#fffbeb';
                            }
                        });
                    });

                } catch (err) {
                    console.error("Error fetching unavailable slots:", err);
                }
            }

            // Re-enable time select
            appointmentTime.disabled = false;
            updateProgress();
        }

        // Form validation
        function validateForm() {
            const name = document.querySelector('input[name="patient_name"]').value.trim();
            const phone = document.querySelector('input[name="patient_phone"]').value.trim();
            const email = document.querySelector('input[name="patient_email"]').value.trim();
            const date = appointmentDate.value;
            const time = appointmentTime.value;

            if (!name || !phone || !email) {
                alert('Please fill in all personal information fields.');
                return false;
            }

            if (!date || !time) {
                alert('Please select your preferred date and time.');
                return false;
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                return false;
            }

            // Phone validation (basic)
            const phoneRegex = /^[\d\s\-\+\(\)]+$/;
            if (!phoneRegex.test(phone)) {
                alert('Please enter a valid phone number.');
                return false;
            }

            return true;
        }

        // Event listeners
        document.addEventListener("DOMContentLoaded", () => {
            const today = getMalaysiaTimeNow().toISOString().split("T")[0];
            appointmentDate.setAttribute("min", today);
            appointmentDate.value = today;
            
            // Select "No Preference" by default
            const noPreferenceOption = document.querySelector('.doctor-option.no-preference');
            if (noPreferenceOption) {
                selectDoctor(noPreferenceOption);
            }
            
            updateTimeOptions();

            // Add input listeners for progress tracking
            const personalInputs = document.querySelectorAll('input[name="patient_name"], input[name="patient_phone"], input[name="patient_email"]');
            personalInputs.forEach(input => {
                input.addEventListener('input', updateProgress);
            });
        });

        appointmentDate.addEventListener("change", updateTimeOptions);
        // Remove the old doctorSelect event listener since we're using onclick now
        appointmentTime.addEventListener("change", updateProgress);

        // Form submission
        appointmentForm.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }

            // Show loading state
            const submitBtn = this.querySelector('.btn-appointment');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            submitBtn.disabled = true;

            // Re-enable after 5 seconds as fallback
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });

        // Auto-resize textarea
        const messageTextarea = document.querySelector('textarea[name="message"]');
        if (messageTextarea) {
            messageTextarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 200) + 'px';
            });
        }
    </script>

</body>
</html>
