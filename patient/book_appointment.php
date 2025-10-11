<?php
session_start();
include "../db.php"; 
require '../vendor/autoload.php'; // 確保已經安裝 phpmailer (composer require phpmailer/phpmailer)
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
date_default_timezone_set("Asia/Kuala_Lumpur");

// 驗證身份
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: ../patient/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 撈取病患資料
$user_sql = "SELECT name, email, phone FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// 撈取醫生清單
$doctor_list = $conn->query("SELECT id, name, specialty FROM doctors ORDER BY name ASC");

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $patient_name = $_POST['patient_name'];
    $patient_phone = $_POST['patient_phone'];
    $patient_email = $_POST['patient_email'];
    $doctor_id = !empty($_POST['doctor_id']) ? intval($_POST['doctor_id']) : null;
    $message = !empty($_POST['message']) ? trim($_POST['message']) : null;

    if ($doctor_id) {
    // 檢查是否有時間衝突（±1.5小時）
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

    // 🔹 檢查是否在醫生不可用時間內
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

    // 插入新預約（doctor_id 可能為 NULL）
    $sql = "INSERT INTO appointments 
    (patient_id, doctor_id, appointment_date, appointment_time, status, patient_name, patient_phone, patient_email, message) 
    VALUES (?, ?, ?, ?, 'Confirmed', ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iissssss", $user_id, $doctor_id, $appointment_date, $appointment_time, $patient_name, $patient_phone, $patient_email, $message);

    if ($stmt->execute()) {
    $appointment_id = $stmt->insert_id;

    // 🔹 產生 queue_number
    if ($doctor_id) {
        $q_sql = "SELECT COUNT(*) AS cnt 
                  FROM appointments 
                  WHERE appointment_date = ? AND doctor_id = ?";
        $q_stmt = $conn->prepare($q_sql);
        $q_stmt->bind_param("si", $appointment_date, $doctor_id);
    } else {
        $q_sql = "SELECT COUNT(*) AS cnt 
                  FROM appointments 
                  WHERE appointment_date = ? AND doctor_id IS NULL";
        $q_stmt = $conn->prepare($q_sql);
        $q_stmt->bind_param("s", $appointment_date);
    }
    $q_stmt->execute();
    $q_res = $q_stmt->get_result();
    $q_row = $q_res->fetch_assoc();
    $queue_number = intval($q_row['cnt']);

    // 更新 queue_number 到該 appointment
    $update_sql = "UPDATE appointments SET queue_number = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ii", $queue_number, $appointment_id);
    $update_stmt->execute();

    // 🔹 取醫生名字
    $doctor_name = 'No preference';
    if ($doctor_id) {
        $doc_stmt = $conn->prepare("SELECT name FROM doctors WHERE id = ?");
        $doc_stmt->bind_param("i", $doctor_id);
        $doc_stmt->execute();
        $doc_res = $doc_stmt->get_result();
        if ($doc_row = $doc_res->fetch_assoc()) {
            $doctor_name = $doc_row['name'];
        }
    }

    // 📧 發送郵件通知（這裡才能放 queue_number）
    $mailConfirm = new PHPMailer(true);
    try {
        $mailConfirm->isSMTP();
        $mailConfirm->Host = 'smtp.gmail.com';
        $mailConfirm->SMTPAuth = true;
        $mailConfirm->Username = 'huiyingsyzz@gmail.com';
        $mailConfirm->Password = 'exjs cyot yibs cgya'; 
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
    echo "<script>alert('Appointment booked successfully! A confirmation email has been sent.'); window.location.href='my_appointments.php';</script>";
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
    <title>Book Appointment - Green Life Dental Clinic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #059669;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-gray: #f8fafc;
            --border-color: #e5e7eb;
            --text-muted: #6b7280;
            --gradient-primary: linear-gradient(135deg, #2563eb, #1d4ed8);
            --gradient-secondary: linear-gradient(135deg, #059669, #047857);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f0fdf4 100%);
            min-height: 100vh;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .page-header {
            background: var(--gradient-primary);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="medical" patternUnits="userSpaceOnUse" width="20" height="20"><circle cx="10" cy="10" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23medical)"/></svg>');
        }

        .page-header .container {
            position: relative;
            z-index: 1;
        }

        .page-header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .appointment-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .appointment-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header-custom {
            background: var(--gradient-secondary);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .card-header-custom i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .card-header-custom h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .card-body-custom {
            padding: 2.5rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--primary-color);
            font-size: 1.25rem;
        }

        .form-floating {
            margin-bottom: 1.5rem;
        }

        .form-floating > .form-control {
            height: 3.5rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-floating > .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15);
        }

        .form-floating > .form-control:disabled,
        .form-floating > .form-control[readonly] {
            background-color: var(--light-gray);
            border-color: var(--border-color);
            opacity: 0.8;
        }

        .form-floating > label {
            color: var(--text-muted);
            font-weight: 500;
        }

        .form-select {
            height: 3.5rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15);
        }

        .form-select option:disabled {
            color: var(--text-muted);
            background-color: var(--light-gray);
        }

        .textarea-floating {
            position: relative;
        }

        .textarea-floating textarea {
            width: 100%;
            min-height: 120px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            resize: vertical;
        }

        .textarea-floating textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15);
        }

        .btn-appointment {
            width: 100%;
            height: 3.5rem;
            background: var(--gradient-primary);
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            margin-top: 1rem;
            position: relative;
            overflow: hidden;
        }

        .btn-appointment:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
            background: var(--gradient-primary);
        }

        .btn-appointment:active {
            transform: translateY(0);
        }

        .appointment-info {
            background: linear-gradient(135deg, #dbeafe, #dcfce7);
            border: 1px solid rgba(37, 99, 235, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .appointment-info h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }

        .info-item i {
            color: var(--secondary-color);
            font-size: 0.9rem;
            width: 16px;
        }

        .time-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .time-slot {
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            font-weight: 500;
        }

        .time-slot:hover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .time-slot:disabled {
            background: var(--light-gray);
            color: var(--text-muted);
            cursor: not-allowed;
            border-color: var(--border-color);
        }

        .time-slot.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
            }

            .appointment-container {
                padding: 0 0.5rem;
            }

            .card-body-custom {
                padding: 1.5rem;
            }

            .page-header {
                padding: 2rem 0;
            }

            .time-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
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
            
            .doctor-option .doctor-info {
                min-width: auto;
            }
            
            .selection-indicator {
                top: 0.5rem;
                right: 0.5rem;
                width: 25px;
                height: 25px;
            }
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .required-label::after {
            content: ' *';
            color: var(--danger-color);
        }

        .doctor-card {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .doctor-card:hover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .doctor-card.selected {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.1);
        }

        .doctor-info h6 {
            margin-bottom: 0.25rem;
            color: var(--dark-color);
            font-weight: 600;
        }

        .doctor-info p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* Enhanced Doctor Selection Styles */
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .doctor-option {
            border: 2px solid var(--border-color);
            border-radius: 16px;
            background: white;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .doctor-option:hover {
            border-color: var(--primary-color);
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.15);
        }

        .doctor-option.selected {
            border-color: var(--success-color);
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
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
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
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
            border-radius: 50%;
        }

        .no-preference-avatar {
            background: linear-gradient(135deg, var(--accent-color), var(--success-color)) !important;
        }

        .doctor-option .doctor-info {
            flex: 1;
            min-width: 0;
        }

        .doctor-option .doctor-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0 0 0.5rem 0;
        }

        .doctor-option .doctor-specialty {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1rem;
            margin: 0 0 0.75rem 0;
            background: rgba(37, 99, 235, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            display: inline-block;
        }

        .doctor-experience {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
        }

        .doctor-experience i {
            color: var(--accent-color);
        }

        .doctor-option .doctor-bio {
            color: var(--text-muted);
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
            background: var(--success-color);
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
            border: 2px dashed var(--accent-color) !important;
            background: rgba(6, 182, 212, 0.05) !important;
        }

        .no-preference:hover {
            border-color: var(--success-color) !important;
            background: rgba(16, 185, 129, 0.1) !important;
        }

        .no-preference.selected {
            border-color: var(--success-color) !important;
            background: rgba(16, 185, 129, 0.05) !important;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="text-center">
                <h1><i class="fas fa-calendar-plus me-3"></i>Book Your Appointment</h1>
                <p>Schedule your dental care with our experienced professionals</p>
            </div>
        </div>
    </div>

    <div class="appointment-container">
        <div class="appointment-card">
            <div class="card-header-custom">
                <i class="fas fa-tooth"></i>
                <h3>Appointment Details</h3>
                <p>Fill in your information and preferred schedule</p>
            </div>
            
            <div class="card-body-custom">
                <!-- Important Information -->
                <div class="appointment-info">
                    <h5><i class="fas fa-info-circle me-2"></i>Important Information</h5>
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <span>Please arrive 10 minutes before your scheduled time</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <span>We will contact you to confirm the schedule</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <span>A confirmation email will be sent after booking</span>
                    </div>
                </div>

                <form method="POST" id="appointmentForm">
                    <!-- Patient Information -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user"></i>
                            <span>Patient Information</span>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-floating">
                                <input type="text" name="patient_name" id="patient_name" class="form-control" 
                                       placeholder="Patient Name" required readonly value="<?= htmlspecialchars($user['name']) ?>">
                                <label for="patient_name" class="required-label">Patient Name</label>
                            </div>
                            
                            <div class="form-floating">
                                <input type="tel" name="patient_phone" id="patient_phone" class="form-control" 
                                       placeholder="Phone Number" required readonly value="<?= htmlspecialchars($user['phone']) ?>">
                                <label for="patient_phone" class="required-label">Phone Number</label>
                            </div>
                        </div>
                        
                        <div class="form-floating">
                            <input type="email" name="patient_email" id="patient_email" class="form-control" 
                                   placeholder="Email Address" required readonly value="<?= htmlspecialchars($user['email']) ?>">
                            <label for="patient_email" class="required-label">Email Address</label>
                        </div>
                    </div>

                    <!-- Appointment Details -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Appointment Details</span>
                        </div>
                        
                        <div class="mb-4">
                            <label for="doctor_id" class="form-label">
                                <i class="fas fa-user-md me-2"></i>Preferred Dentist (Optional)
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
                        
                        <div class="form-row">
                            <div>
                                <label for="appointment_date" class="form-label required-label">
                                    <i class="fas fa-calendar me-2"></i>Appointment Date
                                </label>
                                <input type="date" name="appointment_date" id="appointment_date" 
                                       class="form-control" style="height: 3.5rem;" required>
                            </div>
                            
                            <div>
                                <label for="appointment_time" class="form-label required-label">
                                    <i class="fas fa-clock me-2"></i>Preferred Time
                                </label>
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
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-notes-medical"></i>
                            <span>Additional Information</span>
                        </div>
                        
                        <div class="textarea-floating">
                            <textarea name="message" id="message" placeholder="Describe your dental concern, symptoms, or any special requests..."></textarea>
                            <label for="message" class="form-label">
                                <i class="fas fa-comment-medical me-2"></i>Message (Optional)
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-appointment" id="submitBtn">
                        <i class="fas fa-check-circle me-2"></i>
                        Confirm Appointment
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

    <script>
        function getMalaysiaTimeNow() {
            const now = new Date();
            const utc = now.getTime() + now.getTimezoneOffset() * 60000;
            return new Date(utc + (8 * 60 * 60000)); // GMT+8
        }

        const appointmentDate = document.getElementById("appointment_date");
        const appointmentTime = document.getElementById("appointment_time");
        const selectedDoctorId = document.getElementById("selected_doctor_id");
        const submitBtn = document.getElementById("submitBtn");
        const loadingOverlay = document.getElementById("loadingOverlay");
        const appointmentForm = document.getElementById("appointmentForm");

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

        // Form submission with loading state
        appointmentForm.addEventListener('submit', function(e) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            loadingOverlay.style.display = 'flex';
        });

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
                    const res = await fetch(`../get_unavailable_slots.php?doctor_id=${doctorId}&date=${selectedDate}`);
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
                                option.style.color = '#ef4444';
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
                                option.style.color = '#f59e0b';
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
        }

        // Initialize page
        window.addEventListener("DOMContentLoaded", () => {
            const today = getMalaysiaTimeNow().toISOString().split("T")[0];
            appointmentDate.setAttribute("min", today);
            appointmentDate.value = today;
            
            // Select "No Preference" by default
            const noPreferenceOption = document.querySelector('.doctor-option.no-preference');
            if (noPreferenceOption) {
                selectDoctor(noPreferenceOption);
            }
            
            updateTimeOptions();

            // Add smooth animations
            const formElements = document.querySelectorAll('.form-control, .form-select, textarea');
            formElements.forEach(element => {
                element.addEventListener('focus', function() {
                    this.style.transform = 'scale(1.02)';
                    this.style.transition = 'all 0.3s ease';
                });
                
                element.addEventListener('blur', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Enhanced time slot styling
            appointmentTime.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    this.style.borderColor = 'var(--success-color)';
                    this.style.backgroundColor = 'rgba(16, 185, 129, 0.05)';
                } else {
                    this.style.borderColor = 'var(--border-color)';
                    this.style.backgroundColor = '';
                }
            });

            // Date picker enhancement
            appointmentDate.addEventListener('change', function() {
                this.style.borderColor = 'var(--primary-color)';
                this.style.backgroundColor = 'rgba(37, 99, 235, 0.05)';
            });
        });

        // Event listeners
        appointmentDate.addEventListener("change", updateTimeOptions);

        // Form validation enhancement
        appointmentForm.addEventListener('submit', function(e) {
            const requiredFields = ['patient_name', 'patient_phone', 'patient_email', 'appointment_date', 'appointment_time'];
            let isValid = true;

            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'var(--danger-color)';
                    field.focus();
                } else {
                    field.style.borderColor = 'var(--success-color)';
                }
            });

            if (!isValid) {
                e.preventDefault();
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Confirm Appointment';
                loadingOverlay.style.display = 'none';
                
                // Show error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger';
                errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Please fill in all required fields.';
                appointmentForm.insertBefore(errorDiv, appointmentForm.firstChild);
                
                setTimeout(() => {
                    errorDiv.remove();
                }, 3000);
            }
        });

        // Auto-hide loading after timeout
        setTimeout(() => {
            if (loadingOverlay.style.display === 'flex') {
                loadingOverlay.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Confirm Appointment';
            }
        }, 10000);
    </script>

</body>
</html>
