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

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: var(--space-md);
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            z-index: 5;
            padding: 0.25rem;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--primary-color);
            background: rgba(14, 165, 233, 0.1);
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
                                       placeholder="Enter your password">
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

// 如果病人已登錄，直接跳轉到儀表板
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'patient') {
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Login - Green Life Dental Clinic</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Medical Color Palette */
            --primary-color: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #7dd3fc;
            --secondary-color: #22d3ee;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            
            /* Neutral Colors */
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
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

        body {
            font-family: 'Inter', sans-serif;
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
        }

        /* Floating Medical Icons */
        .floating-icons {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 2;
        }

        .floating-icon {
            position: absolute;
            color: rgba(255, 255, 255, 0.1);
            font-size: 2rem;
            animation: float 6s ease-in-out infinite;
        }

        .floating-icon:nth-child(1) {
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-icon:nth-child(2) {
            top: 20%;
            right: 15%;
            animation-delay: -1s;
        }

        .floating-icon:nth-child(3) {
            top: 50%;
            left: 5%;
            animation-delay: -2s;
        }

        .floating-icon:nth-child(4) {
            top: 70%;
            right: 10%;
            animation-delay: -3s;
        }

        .floating-icon:nth-child(5) {
            bottom: 20%;
            left: 20%;
            animation-delay: -4s;
        }

        .floating-icon:nth-child(6) {
            bottom: 10%;
            right: 25%;
            animation-delay: -5s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(5deg);
            }
        }

        /* Main Container */
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

        /* Login Card */
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
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color), var(--success-color));
        }

        .card-header {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.1), rgba(34, 211, 238, 0.05));
            padding: var(--space-xl) var(--space-lg) var(--space-lg);
            text-align: center;
            border-bottom: 1px solid rgba(229, 231, 235, 0.3);
        }

        .clinic-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-md);
            color: white;
            font-size: 2rem;
            box-shadow: var(--shadow-lg);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: var(--shadow-lg);
            }
            50% {
                transform: scale(1.05);
                box-shadow: var(--shadow-xl);
            }
        }

        .clinic-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: var(--space-xs);
        }

        .clinic-subtitle {
            color: var(--gray-600);
            font-size: 1rem;
            font-weight: 400;
            margin-bottom: var(--space-sm);
        }

        .login-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: var(--space-xs) var(--space-md);
            border-radius: var(--radius-full);
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }

        .card-body {
            padding: var(--space-xl) var(--space-lg);
        }

        /* Form Styling */
        .form-group {
            margin-bottom: var(--space-lg);
        }

        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: var(--space-sm);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
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

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1), var(--shadow-md);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--gray-400);
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
            z-index: 5;
            font-size: 1.1rem;
        }

        .input-group .form-control {
            padding-left: calc(var(--space-md) * 2.5);
        }

        .password-toggle {
            position: absolute;
            right: var(--space-md);
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            z-index: 5;
            padding: var(--space-xs);
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--primary-color);
            background: rgba(14, 165, 233, 0.1);
        }

        /* Buttons */
        .btn {
            border-radius: var(--radius-lg);
            padding: var(--space-md) var(--space-lg);
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), #0369a1);
            box-shadow: var(--shadow-lg);
            transform: translateY(-1px);
            color: white;
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: var(--shadow-sm);
        }

        .btn-loading {
            position: relative;
            color: transparent;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Alert Styling */
        .alert {
            border: none;
            border-radius: var(--radius-lg);
            padding: var(--space-md);
            margin-bottom: var(--space-lg);
            font-weight: 500;
            border-left: 4px solid;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
            color: #991b1b;
            border-left-color: var(--danger-color);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
            color: #065f46;
            border-left-color: var(--success-color);
        }

        /* Links */
        .text-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .text-link:hover {
            color: var(--primary-dark);
            text-decoration: none;
            transform: translateX(2px);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: var(--space-lg) 0;
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--gray-200);
        }

        .divider span {
            padding: 0 var(--space-md);
            background: rgba(255, 255, 255, 0.95);
        }

        /* Footer Links */
        .footer-links {
            text-align: center;
            margin-top: var(--space-lg);
            padding-top: var(--space-lg);
            border-top: 1px solid rgba(229, 231, 235, 0.3);
        }

        .footer-links .text-link {
            font-size: 0.9rem;
            margin: 0 var(--space-sm);
        }

        /* Responsive Design */
        @media (max-width: 640px) {
            .login-wrapper {
                padding: var(--space-md);
            }
            
            .card-header {
                padding: var(--space-lg) var(--space-md) var(--space-md);
            }
            
            .card-body {
                padding: var(--space-lg) var(--space-md);
            }
            
            .clinic-title {
                font-size: 1.5rem;
            }
            
            .floating-icon {
                font-size: 1.5rem;
            }
            
            .footer-links .text-link {
                display: block;
                margin: var(--space-xs) 0;
            }
        }

        /* Animation for page load */
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

        /* Focus States */
        .form-control:focus + .input-group-icon {
            color: var(--primary-color);
        }

        /* Custom Checkbox */
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(14, 165, 233, 0.25);
        }

        /* Remember me */
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
        }

        .form-check-label {
            font-size: 0.9rem;
            color: var(--gray-600);
        }

        /* Success animation */
        @keyframes checkmark {
            0% {
                height: 0;
                width: 0;
                opacity: 1;
            }
            20% {
                height: 0;
                width: 7px;
                opacity: 1;
            }
            40% {
                height: 16px;
                width: 7px;
                opacity: 1;
            }
            100% {
                height: 16px;
                width: 7px;
                opacity: 1;
            }
        }

        .success-checkmark {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: var(--success-color);
            stroke-miterlimit: 10;
            margin: 10px auto;
            box-shadow: inset 0px 0px 0px var(--success-color);
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }
    </style>
</head>
<body>
    <!-- Floating Medical Icons Background -->
    <div class="floating-icons">
        <i class="bi bi-heart-pulse floating-icon"></i>
        <i class="bi bi-shield-check floating-icon"></i>
        <i class="bi bi-hospital floating-icon"></i>
        <i class="bi bi-person-hearts floating-icon"></i>
        <i class="bi bi-capsule floating-icon"></i>
        <i class="bi bi-bandaid floating-icon"></i>
    </div>

    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-card animate-fade-in">
                <!-- Card Header -->
                <div class="card-header">
                    <div class="clinic-logo">
                        <i class="bi bi-hospital"></i>
                    </div>
                    <h1 class="clinic-title">Green Life Dental Clinic</h1>
                    <p class="clinic-subtitle">Professional Dental Care & Treatment</p>
                    <div class="login-badge">
                        <i class="bi bi-person-check"></i>
                        Patient Portal
                    </div>
                </div>

                <!-- Card Body -->
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php" id="loginForm">
                        <!-- Email Field -->
                        <div class="form-group">
                            <label class="form-label" for="email">
                                <i class="bi bi-envelope"></i>
                                Email Address
                            </label>
                            <div class="input-group">
                                <i class="bi bi-at input-group-icon"></i>
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
                            <label class="form-label" for="password">
                                <i class="bi bi-shield-lock"></i>
                                Password
                            </label>
                            <div class="input-group">
                                <i class="bi bi-key input-group-icon"></i>
                                <input type="password" 
                                       name="password" 
                                       id="password"
                                       class="form-control" 
                                       required 
                                       placeholder="Enter your password">
                                <button type="button" class="password-toggle" onclick="togglePassword()">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
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
                                <i class="bi bi-question-circle"></i>
                                Forgot Password?
                            </a>
                        </div>

                        <!-- Login Button -->
                        <button type="submit" name="login" class="btn btn-primary w-100 mb-3" id="loginBtn">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            Sign In to Patient Portal
                        </button>
                    </form>

                    <!-- Divider -->
                    <div class="divider">
                        <span>New Patient?</span>
                    </div>

                    <!-- Register Link -->
                    <div class="text-center mb-3">
                        <a href="register.php" class="text-link">
                            <i class="bi bi-person-plus"></i>
                            Create Patient Account
                        </a>
                    </div>

                    <!-- Footer Links -->
                    <div class="footer-links">
                        <a href="../doctor/login.php" class="text-link">
                            <i class="bi bi-stethoscope"></i>
                            Doctor Login
                        </a>
                        <a href="../admin/login.php" class="text-link">
                            <i class="bi bi-gear"></i>
                            Admin Portal
                        </a>
                        <a href="../index.php" class="text-link">
                            <i class="bi bi-house"></i>
                            Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password visibility toggle
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        }

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            if (!email || !password) {
                e.preventDefault();
                showAlert('Please fill in all required fields.', 'danger');
                return;
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                showAlert('Please enter a valid email address.', 'danger');
                return;
            }

            // Show loading state
            loginBtn.classList.add('btn-loading');
            loginBtn.disabled = true;
        });

        // Show alert function
        function showAlert(message, type) {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());

            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                ${message}
            `;

            // Insert alert before form
            const form = document.getElementById('loginForm');
            form.parentNode.insertBefore(alertDiv, form);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Auto-focus email field
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            if (emailField && !emailField.value) {
                emailField.focus();
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

        // Form validation feedback
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.checkValidity()) {
                    this.classList.add('is-valid');
                    this.classList.remove('is-invalid');
                } else {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                }
            });

            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid') || this.classList.contains('is-valid')) {
                    if (this.checkValidity()) {
                        this.classList.add('is-valid');
                        this.classList.remove('is-invalid');
                    } else {
                        this.classList.add('is-invalid');
                        this.classList.remove('is-valid');
                    }
                }
            });
        });

        // Remember me functionality
        document.getElementById('rememberMe').addEventListener('change', function() {
            if (this.checked) {
                localStorage.setItem('rememberMe', 'true');
            } else {
                localStorage.removeItem('rememberMe');
            }
        });

        // Check if remember me was previously set
        if (localStorage.getItem('rememberMe') === 'true') {
            document.getElementById('rememberMe').checked = true;
        }

        // Add subtle animations
        const card = document.querySelector('.login-card');
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 32px 64px -12px rgba(0, 0, 0, 0.35)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 25px 50px -12px rgba(0, 0, 0, 0.25)';
        });
    </script>
</body>
</html> 