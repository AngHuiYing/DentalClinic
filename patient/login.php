<?php
session_start();
include_once('../includes/db.php');

// 如果病人已登錄，直接跳轉到儀表板
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'patient') {
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
        // 檢查用戶是否存在且是病人
        $sql = "SELECT id, name, password FROM users WHERE email = ? AND role = 'patient'";
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
                    $_SESSION['user_role'] = 'patient';
                    
                    // 重定向到dashboard
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Incorrect password. Please try again.";
                }
            } else {
                $error = "No patient account found with this email address.";
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
    <title>Patient Portal - Dental Care Clinic</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Medical Theme Colors */
            --primary-color: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #7dd3fc;
            --secondary-color: #06d6a0;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            
            /* Neutrals */
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
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            
            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-2xl: 2rem;
            --radius-full: 9999px;
            
            /* Spacing */
            --space-xs: 0.5rem;
            --space-sm: 1rem;
            --space-md: 1.5rem;
            --space-lg: 2rem;
            --space-xl: 3rem;
            --space-2xl: 4rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 25%, #0369a1 50%, #075985 75%, #0c4a6e 100%);
            min-height: 100vh;
            line-height: 1.6;
            position: relative;
            overflow-x: hidden;
        }

        /* Background Pattern */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.03) 0%, transparent 50%);
            z-index: 1;
            pointer-events: none;
        }

        /* Floating Medical Icons */
        .floating-icons {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 2;
            pointer-events: none;
            overflow: hidden;
        }

        .floating-icon {
            position: absolute;
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite linear;
        }

        .floating-icon:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; }
        .floating-icon:nth-child(2) { top: 20%; right: 15%; animation-delay: -5s; }
        .floating-icon:nth-child(3) { top: 60%; left: 5%; animation-delay: -10s; }
        .floating-icon:nth-child(4) { top: 80%; right: 10%; animation-delay: -15s; }
        .floating-icon:nth-child(5) { top: 40%; right: 25%; animation-delay: -3s; }
        .floating-icon:nth-child(6) { top: 70%; left: 20%; animation-delay: -8s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.1; }
            25% { transform: translateY(-20px) rotate(90deg); opacity: 0.2; }
            50% { transform: translateY(-10px) rotate(180deg); opacity: 0.15; }
            75% { transform: translateY(-30px) rotate(270deg); opacity: 0.1; }
        }

        /* Login Container */
        .login-wrapper {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-lg);
        }

        .login-container {
            width: 100%;
            max-width: 480px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
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
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .card-header {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.1), rgba(6, 214, 160, 0.05));
            border-bottom: 1px solid rgba(14, 165, 233, 0.1);
            padding: var(--space-xl) var(--space-lg) var(--space-lg);
            text-align: center;
        }

        .logo-container {
            margin-bottom: var(--space-md);
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-md);
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .logo-icon::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: rotate(45deg) translateX(-100%); }
            100% { transform: rotate(45deg) translateX(100%); }
        }

        .logo-icon i {
            font-size: 2.5rem;
            color: white;
            z-index: 1;
        }

        .login-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: var(--space-xs);
        }

        .login-subtitle {
            color: var(--gray-600);
            font-size: 1rem;
            font-weight: 400;
        }

        .card-body {
            padding: var(--space-xl) var(--space-lg);
        }

        /* Alert Styles */
        .alert {
            border-radius: var(--radius-lg);
            border: none;
            box-shadow: var(--shadow-sm);
            margin-bottom: var(--space-lg);
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: var(--space-lg);
        }

        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: var(--space-xs);
            font-size: 0.95rem;
        }

        .input-group {
            position: relative;
        }

        .input-group-icon {
            position: absolute;
            left: var(--space-md);
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 1.1rem;
            z-index: 5;
            pointer-events: none;
        }

        .form-control {
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--space-md) var(--space-md);
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .input-group .form-control {
            padding-left: calc(var(--space-md) * 2.5);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(14, 165, 233, 0.15);
            background: var(--white);
        }

        .form-control::placeholder {
            color: var(--gray-400);
            font-weight: 400;
        }

        /* Hide browser default password toggle */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }
        
        input[type="password"]::-webkit-credentials-auto-fill-button {
            display: none !important;
        }
        
        input[type="password"]::-webkit-strong-password-auto-fill-button {
            display: none !important;
        }

        /* Custom Password Toggle */
        .password-toggle {
            position: absolute;
            right: var(--space-md);
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            z-index: 10;
            padding: 0.5rem;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:hover {
            color: var(--primary-color);
            background: rgba(14, 165, 233, 0.1);
        }
        
        .password-toggle:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 1px;
        }

        /* Remember & Forgot Section */
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: var(--space-lg) 0;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .form-check-input {
            border-radius: var(--radius-sm);
            border: 2px solid var(--gray-300);
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-label {
            color: var(--gray-600);
            font-size: 0.95rem;
        }

        .text-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .text-link:hover {
            color: var(--primary-dark);
            text-decoration: none;
        }

        /* Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: var(--radius-lg);
            padding: var(--space-md) var(--space-lg);
            font-weight: 600;
            font-size: 1.05rem;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: var(--shadow-md);
        }

        /* Divider */
        .divider {
            text-align: center;
            margin: var(--space-lg) 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--gray-200);
            z-index: 1;
        }

        .divider span {
            background: var(--white);
            padding: 0 var(--space-md);
            color: var(--gray-500);
            font-size: 0.9rem;
            font-weight: 500;
            z-index: 2;
            position: relative;
        }

        /* Register Link */
        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            border-radius: var(--radius-lg);
            padding: var(--space-md) var(--space-lg);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Footer */
        .login-footer {
            text-align: center;
            padding: var(--space-lg);
            background: var(--gray-50);
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        /* Animations */
        .animate-fade-in {
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

        /* Loading State */
        .btn-loading {
            pointer-events: none;
            opacity: 0.8;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-wrapper {
                padding: var(--space-md);
            }
            
            .card-header {
                padding: var(--space-lg);
            }
            
            .card-body {
                padding: var(--space-lg);
            }
            
            .login-title {
                font-size: 1.5rem;
            }
            
            .remember-forgot {
                flex-direction: column;
                gap: var(--space-md);
                align-items: flex-start;
            }
        }

        /* Form Validation Styles */
        .form-control.is-invalid {
            border-color: var(--danger-color);
            animation: shake 0.5s ease-in-out;
        }

        .form-control.is-valid {
            border-color: var(--success-color);
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        /* Accessibility */
        .form-control:focus,
        .btn:focus,
        .text-link:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .login-card {
                background: rgba(15, 23, 42, 0.95);
                color: var(--gray-100);
            }
            
            .login-title {
                color: var(--gray-100);
            }
            
            .login-subtitle {
                color: var(--gray-400);
            }
            
            .form-control {
                background: rgba(30, 41, 59, 0.5);
                border-color: var(--gray-600);
                color: var(--gray-100);
            }
            
            .form-control::placeholder {
                color: var(--gray-500);
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <!-- Floating Medical Icons -->
    <div class="floating-icons">
        <i class="fas fa-tooth floating-icon"></i>
        <i class="fas fa-stethoscope floating-icon"></i>
        <i class="fas fa-heartbeat floating-icon"></i>
        <i class="fas fa-user-md floating-icon"></i>
        <i class="fas fa-pills floating-icon"></i>
        <i class="fas fa-syringe floating-icon"></i>
    </div>

    <!-- Login Form -->
    <div class="login-wrapper">
        <div class="login-container animate-fade-in">
            <div class="login-card">
                <!-- Header -->
                <div class="card-header">
                    <div class="logo-container">
                        <div class="logo-icon">
                            <i class="fas fa-tooth"></i>
                        </div>
                    </div>
                    <h1 class="login-title">Patient Portal</h1>
                    <p class="login-subtitle">Access your dental care information</p>
                </div>

                <!-- Body -->
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php" id="loginForm">
                        <!-- Email Field -->
                        <div class="form-group">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-1"></i>
                                Email Address
                            </label>
                            <div class="input-group">
                                <i class="fas fa-envelope input-group-icon"></i>
                                <input type="email" 
                                       name="email" 
                                       id="email"
                                       class="form-control" 
                                       required 
                                       placeholder="Enter your registered email"
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="form-group">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-1"></i>
                                Password
                            </label>
                            <div class="input-group">
                                <i class="fas fa-lock input-group-icon"></i>
                                <input type="password" 
                                       name="password" 
                                       id="password"
                                       class="form-control" 
                                       required 
                                       placeholder="Enter your password"
                                       autocomplete="current-password"
                                       data-lpignore="true"
                                       data-form-type="other">
                                <button type="button" class="password-toggle" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="remember-forgot">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="" id="rememberMe">
                                <label class="form-check-label" for="rememberMe">
                                    Remember me
                                </label>
                            </div>
                            <a href="forgot_password.php" class="text-link">
                                <i class="fas fa-question-circle me-1"></i>
                                Forgot Password?
                            </a>
                        </div>

                        <!-- Login Button -->
                        <button type="submit" name="login" class="btn btn-primary w-100 mb-3" id="loginBtn">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Sign In to Patient Portal
                        </button>
                    </form>

                    <!-- Divider -->
                    <div class="divider">
                        <span>New Patient?</span>
                    </div>

                    <!-- Register Link -->
                    <a href="register.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-user-plus me-2"></i>
                        Create Patient Account
                    </a>
                </div>

                <!-- Footer -->
                <div class="login-footer">
                    <i class="fas fa-shield-alt me-1"></i>
                    Your health information is protected and secure
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

        // Enhanced form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const inputs = form.querySelectorAll('input[required]');
            
            // Real-time validation feedback
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

            // Form submission handler
            form.addEventListener('submit', function(e) {
                const loginBtn = document.getElementById('loginBtn');
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;

                // Basic validation
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
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
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
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${message}
                `;

                // Insert alert
                const form = document.getElementById('loginForm');
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
            // Alt + R to go to register
            if (e.altKey && e.key === 'r') {
                e.preventDefault();
                window.location.href = 'register.php';
            }
        });

        // Auto-focus first input
        window.addEventListener('load', function() {
            document.getElementById('email').focus();
        });

        // Smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';
    </script>
</body>
</html>