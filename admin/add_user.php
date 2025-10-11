<?php
session_start();
include '../includes/db.php';
require __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Kuala_Lumpur');

// 檢查 admin 是否登入
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role  = $_POST['role'];

    if (empty($name) || empty($email) || empty($phone) || empty($role)) {
        echo "<script>alert('All fields are required!');</script>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email format!');</script>";
    } else {
        // 檢查 email 是否存在
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();

        if ($check_email->num_rows > 0) {
            echo "<script>alert('Email already exists!');</script>";
        } else {
            // 1️⃣ 產生隨機密碼（不會告訴 user，只是確保有值）
            $plain_password  = bin2hex(random_bytes(4)); // 8 字元
            $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

            // 2️⃣ 建立 user
            $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $phone, $hashed_password, $role);

            if ($stmt->execute()) {
                // 取得 user id
                $user_id = $stmt->insert_id;

                // 3️⃣ 建立 reset token
                $token = bin2hex(random_bytes(32));
                $expires_at = date("Y-m-d H:i:s", strtotime("+1 hour"));

                $reset_sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
                $reset_stmt = $conn->prepare($reset_sql);
                $reset_stmt->bind_param("sss", $email, $token, $expires_at);
                $reset_stmt->execute();

                $reset_link = "http://localhost/Dental_Clinic/patient/reset_password.php?token=" . $token;

                // 4️⃣ 發送 Email
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'huiyingsyzz@gmail.com';
                    $mail->Password = 'exjs cyot yibs cgya'; 
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('huiyingsyzz@gmail.com', 'Green Life Dental Clinic');
                    $mail->addAddress($email);

                    $mail->isHTML(true);
                    $mail->Subject = "Welcome - Set Your Password";
                    $mail->Body = "
                        <p>Dear $name,</p>
                        <p>Your account has been created successfully.</p>
                        <p>Please set your password using the link below (valid for 1 hour):</p>
                        <p><a href='$reset_link'>$reset_link</a></p>
                        <p>Thank you,<br>Green Life Dental Clinic</p>
                    ";
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Email error: {$mail->ErrorInfo}");
                }

                // 5️⃣ 管理員操作紀錄
                $admin_id = $_SESSION['admin_id'];
                $action = "Added new user: $name ($email)";
                $conn->query("INSERT INTO user_logs (admin_id, action) VALUES ('$admin_id', '$action')");

                echo "<script>alert('User added successfully and reset link sent!'); window.location.href='manage_users.php';</script>";
            } else {
                echo "<script>alert('Failed to add user.');</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User - Green Life Dental Clinic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #10b981;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --shadow-light: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-large: 0 10px 25px rgba(0, 0, 0, 0.1);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            --gradient-danger: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Main Container */
        .main-container {
            background: var(--light-bg);
            margin: 95px 20px 20px 20px;
            border-radius: 2rem;
            box-shadow: var(--shadow-large);
            overflow: hidden;
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 1;
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Header Section */
        .page-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #1e40af 100%);
            color: white;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            transform: translate(150px, -150px);
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .page-title i {
            font-size: 2.5rem;
            opacity: 0.9;
            color: white !important;
        }

        .page-title h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: white !important;
        }

        .page-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            color: white !important;
        }

        .breadcrumb-custom {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            padding: 0.75rem 1.5rem;
            display: inline-block;
        }

        .breadcrumb-custom a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .breadcrumb-custom a:hover {
            color: white;
        }

        /* Form Section */
        .form-section {
            padding: 2rem;
        }

        .form-container {
            background: var(--card-bg);
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            border-radius: 1.5rem 1.5rem 0 0;
        }

        .form-title {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--light-bg);
        }

        .form-title h2 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-title p {
            color: var(--text-secondary);
            margin: 0;
        }

        /* Form Elements */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            position: relative;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-label i {
            color: var(--primary-color);
            font-size: 1rem;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--light-bg);
            color: var(--text-primary);
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
            transform: translateY(-1px);
        }

        .form-control:hover, .form-select:hover {
            border-color: var(--primary-color);
            background: white;
        }

        /* Role Selection */
        .role-selection {
            margin-bottom: 1.5rem;
        }

        .role-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .role-card {
            border: 2px solid var(--border-color);
            border-radius: 1rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--border-color);
            transition: all 0.3s ease;
        }

        .role-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }

        .role-card.selected {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.02);
        }

        .role-card.selected::before {
            background: var(--gradient-primary);
        }

        .role-card.admin:hover::before { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .role-card.doctor:hover::before { background: linear-gradient(135deg, #10b981, #059669); }
        .role-card.patient:hover::before { background: linear-gradient(135deg, #3b82f6, #2563eb); }

        .role-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }

        .role-card:hover .role-icon,
        .role-card.selected .role-icon {
            color: var(--primary-color);
            transform: scale(1.1);
        }

        .role-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .role-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* Hidden radio inputs */
        .role-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        /* Email Notification Info */
        .notification-info {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 1rem;
            padding: 1.5rem;
            margin: 1.5rem 0;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .notification-info i {
            color: var(--success-color);
            font-size: 1.5rem;
            margin-top: 0.25rem;
        }

        .notification-info div h4 {
            color: var(--success-color);
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .notification-info div p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Action Buttons */
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }

        .btn {
            padding: 0.875rem 2rem;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-large);
            color: white;
            text-decoration: none;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #9ca3af);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            color: white;
            text-decoration: none;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Loading Animation */
        .loading {
            position: relative;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .main-container {
                margin: 95px 15px 15px 15px;
            }
        }

        @media (max-width: 992px) {
            .main-container {
                margin: 90px 10px 10px 10px;
                border-radius: 1.5rem;
            }
            
            .page-header {
                padding: 1.8rem;
            }
            
            .form-section {
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                margin: 85px 8px 8px 8px;
                border-radius: 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
                text-align: center;
            }
            
            .page-title {
                flex-direction: column;
                text-align: center;
            }
            
            .page-title h1 {
                font-size: 1.7rem;
            }
            
            .form-section {
                padding: 1rem;
            }
            
            .form-container {
                padding: 1.5rem;
                border-radius: 1rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .role-cards {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column-reverse;
            }
        }

        @media (max-width: 576px) {
            .main-container {
                margin: 80px 5px 5px 5px;
                border-radius: 0.8rem;
            }
            
            .page-header {
                padding: 1rem;
            }
            
            .form-section {
                padding: 0.8rem;
            }
            
            .form-container {
                padding: 1rem;
                border-radius: 0.8rem;
            }
            
            .page-title h1 {
                font-size: 1.5rem;
            }
            
            .btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.95rem;
            }
        }

        /* Form Validation Styles */
        .form-control.invalid {
            border-color: var(--danger-color);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .form-control.valid {
            border-color: var(--success-color);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .field-feedback {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .field-feedback.error {
            color: var(--danger-color);
        }

        .field-feedback.success {
            color: var(--success-color);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="page-title">
                    <i class="bi bi-person-plus-fill"></i>
                    <div>
                        <h1>Add New User</h1>
                        <p class="page-subtitle">Create a new account and send setup instructions</p>
                    </div>
                </div>
                
                <nav class="breadcrumb-custom">
                    <a href="dashboard.php">Dashboard</a>
                    <span class="mx-2">/</span>
                    <a href="manage_users.php">Manage Users</a>
                    <span class="mx-2">/</span>
                    <span>Add New User</span>
                </nav>
            </div>
        </div>

        <!-- Form Section -->
        <div class="form-section">
            <div class="form-container">
                <div class="form-title">
                    <h2>User Information</h2>
                    <p>Fill in the details below to create a new user account</p>
                </div>

                <form method="POST" id="addUserForm" novalidate>
                    <!-- Basic Information -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-person-fill"></i>
                                Full Name
                            </label>
                            <input type="text" name="name" class="form-control" placeholder="Enter full name" required>
                            <div class="field-feedback" id="name-feedback"></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-envelope-fill"></i>
                                Email Address
                            </label>
                            <input type="email" name="email" class="form-control" placeholder="Enter email address" required>
                            <div class="field-feedback" id="email-feedback"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-telephone-fill"></i>
                                Phone Number
                            </label>
                            <input type="tel" name="phone" class="form-control" placeholder="Enter phone number" required>
                            <div class="field-feedback" id="phone-feedback"></div>
                        </div>
                    </div>

                    <!-- Role Selection -->
                    <div class="role-selection">
                        <label class="form-label">
                            <i class="bi bi-shield-check"></i>
                            Select User Role
                        </label>
                        
                        <div class="role-cards">
                            <div class="role-card admin" onclick="selectRole('admin')">
                                <input type="radio" name="role" value="admin" id="role-admin">
                                <div class="role-icon">
                                    <i class="bi bi-shield-fill"></i>
                                </div>
                                <div class="role-title">Administrator</div>
                                <div class="role-description">Full system access and user management capabilities</div>
                            </div>
                            
                            <div class="role-card doctor" onclick="selectRole('doctor')">
                                <input type="radio" name="role" value="doctor" id="role-doctor">
                                <div class="role-icon">
                                    <i class="bi bi-heart-pulse-fill"></i>
                                </div>
                                <div class="role-title">Doctor/Dentist</div>
                                <div class="role-description">Patient management and medical records access</div>
                            </div>
                            
                            <div class="role-card patient" onclick="selectRole('patient')">
                                <input type="radio" name="role" value="patient" id="role-patient">
                                <div class="role-icon">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                                <div class="role-title">Patient</div>
                                <div class="role-description">Appointment booking and personal health records</div>
                            </div>
                        </div>
                        <div class="field-feedback" id="role-feedback"></div>
                    </div>

                    <!-- Email Notification Info -->
                    <div class="notification-info">
                        <i class="bi bi-envelope-check"></i>
                        <div>
                            <h4>Automatic Email Notification</h4>
                            <p>The user will receive an email with instructions to set up their password. The setup link will be valid for 1 hour from account creation.</p>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="manage_users.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i>
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="bi bi-person-plus"></i>
                            Create User Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Form validation and interaction
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('addUserForm');
            const submitBtn = document.getElementById('submitBtn');
            const roleCards = document.querySelectorAll('.role-card');

            // Field validation functions
            function validateName(name) {
                return name.trim().length >= 2;
            }

            function validateEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }

            function validatePhone(phone) {
                const phoneRegex = /^[\+]?[(]?[\+]?\d{2,3}[)]?[-\s\.]?\d{2,4}[-\s\.]?\d{3,6}$/;
                return phoneRegex.test(phone.replace(/\s/g, ''));
            }

            // Show field feedback
            function showFieldFeedback(fieldName, isValid, message) {
                const field = document.querySelector(`input[name="${fieldName}"]`);
                const feedback = document.getElementById(`${fieldName}-feedback`);
                
                field.classList.remove('valid', 'invalid');
                field.classList.add(isValid ? 'valid' : 'invalid');
                
                feedback.className = `field-feedback ${isValid ? 'success' : 'error'}`;
                feedback.innerHTML = `<i class="bi bi-${isValid ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            }

            // Role selection
            window.selectRole = function(role) {
                roleCards.forEach(card => card.classList.remove('selected'));
                document.querySelector(`.role-card.${role}`).classList.add('selected');
                document.getElementById(`role-${role}`).checked = true;
                
                const feedback = document.getElementById('role-feedback');
                feedback.className = 'field-feedback success';
                feedback.innerHTML = '<i class="bi bi-check-circle"></i> Role selected successfully';
            };

            // Real-time validation
            document.querySelector('input[name="name"]').addEventListener('blur', function() {
                const isValid = validateName(this.value);
                showFieldFeedback('name', isValid, 
                    isValid ? 'Name looks good' : 'Name must be at least 2 characters long');
            });

            document.querySelector('input[name="email"]').addEventListener('blur', function() {
                const isValid = validateEmail(this.value);
                showFieldFeedback('email', isValid, 
                    isValid ? 'Email format is valid' : 'Please enter a valid email address');
            });

            document.querySelector('input[name="phone"]').addEventListener('blur', function() {
                const isValid = validatePhone(this.value);
                showFieldFeedback('phone', isValid, 
                    isValid ? 'Phone number is valid' : 'Please enter a valid phone number');
            });

            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const name = form.querySelector('input[name="name"]').value;
                const email = form.querySelector('input[name="email"]').value;
                const phone = form.querySelector('input[name="phone"]').value;
                const role = form.querySelector('input[name="role"]:checked');

                let isValid = true;

                // Validate all fields
                if (!validateName(name)) {
                    showFieldFeedback('name', false, 'Name is required and must be at least 2 characters');
                    isValid = false;
                }

                if (!validateEmail(email)) {
                    showFieldFeedback('email', false, 'Please enter a valid email address');
                    isValid = false;
                }

                if (!validatePhone(phone)) {
                    showFieldFeedback('phone', false, 'Please enter a valid phone number');
                    isValid = false;
                }

                if (!role) {
                    const feedback = document.getElementById('role-feedback');
                    feedback.className = 'field-feedback error';
                    feedback.innerHTML = '<i class="bi bi-exclamation-circle"></i> Please select a user role';
                    isValid = false;
                }

                if (isValid) {
                    // Show loading state
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Creating Account...';
                    
                    // Submit form
                    setTimeout(() => {
                        form.submit();
                    }, 500);
                }
            });

            // Add hover effects to form elements
            const formControls = document.querySelectorAll('.form-control');
            formControls.forEach(control => {
                control.addEventListener('focus', function() {
                    this.parentElement.querySelector('.form-label').style.color = 'var(--primary-color)';
                });
                
                control.addEventListener('blur', function() {
                    this.parentElement.querySelector('.form-label').style.color = 'var(--text-primary)';
                });
            });
        });
    </script>
</body>
</html>
