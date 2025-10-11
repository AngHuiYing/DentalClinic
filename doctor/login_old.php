<?php
session_start();
include_once('../includes/db.php');

// 如果醫生已登錄，直接跳轉到儀表板
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'doctor') {
    header("Location: dashboard.php");
    exit();
}

// 處理登錄表單提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // 輸入驗證
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // 檢查用戶是否存在且是醫生
        $sql = "SELECT id, name, password FROM users WHERE email = ? AND role = 'doctor'";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // 驗證密碼
                if (password_verify($password, $user['password'])) {
                    // 登錄成功，設置session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = 'doctor';
                    
                    // 重定向到dashboard
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Incorrect password. Please try again.";
                }
            } else {
                $error = "No doctor account found with this email address.";
            }
            $stmt->close();
        } else {
            $error = "Database error occurred. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Portal - Dental Care Clinic</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Medical Professional Color Palette */
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary: #059669;
            --accent: #7c3aed;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            
            /* Sophisticated Grays */
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            
            /* Professional Shadows */
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px rgba(0, 0, 0, 0.25);
            
            /* Modern Border Radius */
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-2xl: 2rem;
            --radius-full: 9999px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #667eea 50%, #764ba2 75%, #667eea 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            min-height: 100vh;
            line-height: 1.6;
            position: relative;
            overflow-x: hidden;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Dynamic Background Pattern */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            z-index: 1;
            pointer-events: none;
        }

        /* Floating Medical Icons */
        .floating-medical {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 2;
            pointer-events: none;
            overflow: hidden;
        }

        .medical-icon {
            position: absolute;
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.08);
            animation: floatMedical 25s infinite linear;
        }

        .medical-icon:nth-child(1) { top: 15%; left: 10%; animation-delay: 0s; }
        .medical-icon:nth-child(2) { top: 25%; right: 20%; animation-delay: -5s; }
        .medical-icon:nth-child(3) { top: 65%; left: 15%; animation-delay: -10s; }
        .medical-icon:nth-child(4) { top: 75%; right: 15%; animation-delay: -15s; }
        .medical-icon:nth-child(5) { top: 35%; right: 30%; animation-delay: -7s; }
        .medical-icon:nth-child(6) { top: 55%; left: 25%; animation-delay: -12s; }
        .medical-icon:nth-child(7) { top: 85%; left: 35%; animation-delay: -3s; }
        .medical-icon:nth-child(8) { top: 45%; right: 10%; animation-delay: -8s; }

        @keyframes floatMedical {
            0%, 100% { 
                transform: translateY(0px) translateX(0px) rotate(0deg); 
                opacity: 0.08; 
            }
            25% { 
                transform: translateY(-20px) translateX(10px) rotate(90deg); 
                opacity: 0.12; 
            }
            50% { 
                transform: translateY(-10px) translateX(-10px) rotate(180deg); 
                opacity: 0.1; 
            }
            75% { 
                transform: translateY(-30px) translateX(15px) rotate(270deg); 
                opacity: 0.06; 
            }
        }

        /* Main Login Container */
        .login-wrapper {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .login-container {
            width: 100%;
            max-width: 520px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-2xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            position: relative;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--accent), var(--secondary));
            background-size: 200% 100%;
            animation: shimmerBorder 3s ease-in-out infinite;
        }

        @keyframes shimmerBorder {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Header Section */
        .card-header {
            background: linear-gradient(135deg, 
                rgba(37, 99, 235, 0.1) 0%, 
                rgba(124, 58, 237, 0.1) 50%,
                rgba(5, 150, 105, 0.1) 100%
            );
            border-bottom: 1px solid rgba(37, 99, 235, 0.1);
            padding: 3rem 2rem 2rem;
            text-align: center;
            position: relative;
        }

        .header-decoration {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.02'%3E%3Ccircle cx='30' cy='30' r='4'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
        }

        .logo-container {
            position: relative;
            z-index: 2;
            margin-bottom: 1.5rem;
        }

        .doctor-logo {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }

        .doctor-logo::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: logoShimmer 4s infinite;
        }

        @keyframes logoShimmer {
            0% { transform: rotate(45deg) translateX(-100%); }
            100% { transform: rotate(45deg) translateX(100%); }
        }

        .doctor-logo i {
            font-size: 3rem;
            color: white;
            z-index: 1;
            position: relative;
        }

        .portal-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .portal-subtitle {
            color: var(--gray-600);
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 2;
        }

        .professional-badge {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.15), rgba(124, 58, 237, 0.15));
            border: 2px solid rgba(37, 99, 235, 0.2);
            color: var(--primary);
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-full);
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 2;
        }

        /* Body Section */
        .card-body {
            padding: 2.5rem;
        }

        /* Security Notice */
        .security-notice {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(6, 182, 212, 0.1));
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .security-icon {
            color: var(--success);
            font-size: 1.25rem;
        }

        .security-text {
            color: var(--gray-700);
            font-size: 0.9rem;
            font-weight: 500;
            margin: 0;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 1.2rem;
            z-index: 5;
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .form-control {
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1.25rem 1.25rem 3.5rem;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--gray-50);
            height: auto;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15);
            background: var(--white);
            transform: translateY(-2px);
        }

        .form-control:focus + .input-icon {
            color: var(--primary);
            transform: translateY(-50%) scale(1.1);
        }

        .form-control::placeholder {
            color: var(--gray-500);
            font-weight: 400;
        }

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            z-index: 5;
            padding: 0.5rem;
            border-radius: var(--radius-sm);
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--primary);
            background: rgba(37, 99, 235, 0.1);
        }

        /* Alert Styles */
        .alert {
            border-radius: var(--radius-lg);
            border: none;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        /* Remember & Forgot Section */
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1.5rem 0;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-check-input {
            border-radius: var(--radius-sm);
            border: 2px solid var(--gray-400);
            width: 1.25rem;
            height: 1.25rem;
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .form-check-label {
            color: var(--gray-700);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .forgot-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .forgot-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Button Styles */
        .btn-login {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: var(--radius-lg);
            padding: 1.25rem 2rem;
            font-weight: 700;
            font-size: 1.1rem;
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
            background: linear-gradient(135deg, var(--primary-dark), var(--accent));
        }

        .btn-login:active {
            transform: translateY(-1px);
        }

        /* Footer */
        .login-footer {
            text-align: center;
            padding: 1.5rem;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1rem;
        }

        .footer-link {
            color: var(--gray-600);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .footer-link:hover {
            color: var(--primary);
            text-decoration: none;
            transform: translateY(-1px);
        }

        /* Loading State */
        .btn-loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .btn-loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-wrapper {
                padding: 1rem;
            }
            
            .card-header {
                padding: 2rem 1.5rem 1.5rem;
            }
            
            .card-body {
                padding: 2rem 1.5rem;
            }
            
            .portal-title {
                font-size: 1.75rem;
            }
            
            .doctor-logo {
                width: 80px;
                height: 80px;
            }
            
            .doctor-logo i {
                font-size: 2.5rem;
            }
            
            .login-options {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .footer-links {
                flex-direction: column;
                gap: 1rem;
            }
        }

        /* Accessibility */
        .form-control:focus,
        .btn-login:focus,
        .forgot-link:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Animation Classes */
        .fade-in-up {
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
    </style>
</head>
<body>
    <!-- Floating Medical Icons Background -->
    <div class="floating-medical">
        <i class="fas fa-stethoscope medical-icon"></i>
        <i class="fas fa-user-md medical-icon"></i>
        <i class="fas fa-heartbeat medical-icon"></i>
        <i class="fas fa-tooth medical-icon"></i>
        <i class="fas fa-syringe medical-icon"></i>
        <i class="fas fa-pills medical-icon"></i>
        <i class="fas fa-microscope medical-icon"></i>
        <i class="fas fa-x-ray medical-icon"></i>
    </div>

    <!-- Main Login Container -->
    <div class="login-wrapper">
        <div class="login-container fade-in-up">
            <div class="login-card">
                <!-- Header Section -->
                <div class="card-header">
                    <div class="header-decoration"></div>
                    <div class="logo-container">
                        <div class="doctor-logo">
                            <i class="fas fa-user-md"></i>
                        </div>
                    </div>
                    <h1 class="portal-title">Doctor Portal</h1>
                    <p class="portal-subtitle">Professional Healthcare Access</p>
                    <div class="professional-badge">
                        <i class="fas fa-shield-alt"></i>
                        Medical Professional
                    </div>
                </div>

                <!-- Body Section -->
                <div class="card-body">
                    <!-- Security Notice -->
                    <div class="security-notice">
                        <i class="fas fa-lock security-icon"></i>
                        <p class="security-text">Secure login protected by advanced encryption</p>
                    </div>

                    <!-- Error Alert -->
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form method="POST" action="login.php" id="doctorLoginForm">
                        <!-- Email Field -->
                        <div class="form-group">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope"></i>
                                Medical Email
                            </label>
                            <div class="input-wrapper">
                                <input type="email" 
                                       name="email" 
                                       id="email"
                                       class="form-control" 
                                       required 
                                       placeholder="Enter your professional email"
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                <i class="fas fa-envelope input-icon"></i>
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="form-group">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock"></i>
                                Secure Password
                            </label>
                            <div class="input-wrapper">
                                <input type="password" 
                                       name="password" 
                                       id="password"
                                       class="form-control" 
                                       required 
                                       placeholder="Enter your secure password">
                                <i class="fas fa-lock input-icon"></i>
                                <button type="button" class="password-toggle" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Login Options -->
                        <div class="login-options">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="" id="rememberMe">
                                <label class="form-check-label" for="rememberMe">
                                    Keep me signed in
                                </label>
                            </div>
                            <a href="../patient/forgot_password.php" class="forgot-link">
                                <i class="fas fa-question-circle me-1"></i>
                                Forgot Password?
                            </a>
                        </div>

                        <!-- Login Button -->
                        <button type="submit" name="login" class="btn btn-login w-100 mb-3" id="loginBtn">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Access Doctor Portal
                        </button>
                    </form>
                </div>

                <!-- Footer -->
                <div class="login-footer">
                    <div class="footer-links">
                        <a href="../index.php" class="footer-link">
                            <i class="fas fa-home"></i>
                            Back to Home
                        </a>
                        <a href="../patient/login.php" class="footer-link">
                            <i class="fas fa-user"></i>
                            Patient Portal
                        </a>
                        <a href="../admin/login.php" class="footer-link">
                            <i class="fas fa-cog"></i>
                            Admin Access
                        </a>
                    </div>
                    <p>
                        <i class="fas fa-shield-alt me-2"></i>
                        Your professional data is protected and secure
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password visibility toggle
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Enhanced form handling
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('doctorLoginForm');
            const inputs = form.querySelectorAll('input[required]');
            
            // Real-time validation
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    validateField(this);
                });
                
                input.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid')) {
                        validateField(this);
                    }
                });
            });

            function validateField(field) {
                if (field.checkValidity()) {
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                } else {
                    field.classList.remove('is-valid');
                    field.classList.add('is-invalid');
                }
            }

            // Form submission
            form.addEventListener('submit', function(e) {
                const loginBtn = document.getElementById('loginBtn');
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;

                // Validation
                if (!email || !password) {
                    e.preventDefault();
                    showAlert('Please fill in all required fields.', 'danger');
                    return;
                }

                if (!isValidEmail(email)) {
                    e.preventDefault();
                    showAlert('Please enter a valid email address.', 'danger');
                    return;
                }

                // Show loading state
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Authenticating...';
                loginBtn.classList.add('btn-loading');
            });

            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }

            function showAlert(message, type) {
                // Remove existing alerts
                const existingAlerts = document.querySelectorAll('.alert');
                existingAlerts.forEach(alert => alert.remove());

                // Create new alert
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type}`;
                alertDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    ${message}
                `;

                // Insert alert
                const form = document.getElementById('doctorLoginForm');
                form.parentNode.insertBefore(alertDiv, form);

                // Auto-hide after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + H to go home
            if (e.altKey && e.key === 'h') {
                e.preventDefault();
                window.location.href = '../index.php';
            }
            // Alt + P for patient portal
            if (e.altKey && e.key === 'p') {
                e.preventDefault();
                window.location.href = '../patient/login.php';
            }
            // Alt + A for admin portal
            if (e.altKey && e.key === 'a') {
                e.preventDefault();
                window.location.href = '../admin/login.php';
            }
        });

        // Auto-focus first input
        window.addEventListener('load', function() {
            document.getElementById('email').focus();
        });

        // Add dynamic interaction effects
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
                this.parentElement.style.transition = 'all 0.3s ease';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>