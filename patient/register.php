<?php
session_start();
include_once('../includes/db.php');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match("/^[0-9]{8,15}$/", $phone)) {
        $error = "Invalid phone number. Please enter 8-15 digits.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Email is already registered. Please use a different email or login.";
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'patient')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $name, $email, $phone, $hashed_password);

            if ($stmt->execute()) {
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = 'patient';
                $success = "Registration successful! Redirecting to your dashboard...";
                header("refresh:2;url=dashboard.php");
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Patient Account - Dental Care Clinic</title>
    
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
            
            /* Spacing */
            --space-xs: 0.5rem;
            --space-sm: 1rem;
            --space-md: 1.5rem;
            --space-lg: 2rem;
            --space-xl: 3rem;
            --space-2xl: 4rem;
            
            /* Border Radius */
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
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.08);
            animation: float 25s infinite linear;
        }

        .floating-icon:nth-child(1) { top: 15%; left: 5%; animation-delay: 0s; }
        .floating-icon:nth-child(2) { top: 25%; right: 10%; animation-delay: -5s; }
        .floating-icon:nth-child(3) { top: 45%; left: 15%; animation-delay: -10s; }
        .floating-icon:nth-child(4) { top: 65%; right: 5%; animation-delay: -15s; }
        .floating-icon:nth-child(5) { top: 35%; right: 30%; animation-delay: -3s; }
        .floating-icon:nth-child(6) { top: 75%; left: 25%; animation-delay: -8s; }
        .floating-icon:nth-child(7) { top: 55%; left: 35%; animation-delay: -12s; }
        .floating-icon:nth-child(8) { top: 85%; right: 25%; animation-delay: -18s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.08; }
            25% { transform: translateY(-15px) rotate(90deg); opacity: 0.12; }
            50% { transform: translateY(-8px) rotate(180deg); opacity: 0.1; }
            75% { transform: translateY(-20px) rotate(270deg); opacity: 0.06; }
        }

        /* Register Container */
        .register-wrapper {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-lg);
        }

        .register-container {
            width: 100%;
            max-width: 520px;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-2xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            position: relative;
        }

        .register-card::before {
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
            width: 70px;
            height: 70px;
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
            font-size: 2rem;
            color: white;
            z-index: 1;
        }

        .register-title {
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: var(--space-xs);
        }

        .register-subtitle {
            color: var(--gray-600);
            font-size: 0.95rem;
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

        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #bbf7d0);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
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
            padding: var(--space-md);
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

        .form-control.is-invalid {
            border-color: var(--danger-color);
            animation: shake 0.5s ease-in-out;
        }

        .form-control.is-valid {
            border-color: var(--success-color);
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-3px); }
            20%, 40%, 60%, 80% { transform: translateX(3px); }
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

        /* Login Link */
        .btn-outline-secondary {
            border: 2px solid var(--gray-400);
            color: var(--gray-600);
            background: transparent;
            border-radius: var(--radius-lg);
            padding: var(--space-md) var(--space-lg);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: var(--gray-600);
            color: white;
            border-color: var(--gray-600);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Footer */
        .register-footer {
            text-align: center;
            padding: var(--space-lg);
            background: var(--gray-50);
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        /* Password Strength Indicator */
        .password-strength {
            margin-top: var(--space-xs);
            height: 4px;
            background: var(--gray-200);
            border-radius: 2px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .strength-bar {
            height: 100%;
            border-radius: 2px;
            transition: all 0.3s ease;
            width: 0%;
        }

        .strength-weak { width: 33%; background: var(--danger-color); }
        .strength-medium { width: 66%; background: var(--warning-color); }
        .strength-strong { width: 100%; background: var(--success-color); }

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

        /* Row spacing for two-column layout */
        .form-row {
            display: flex;
            gap: var(--space-md);
        }

        .form-row .form-group {
            flex: 1;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .register-wrapper {
                padding: var(--space-md);
            }
            
            .card-header {
                padding: var(--space-lg);
            }
            
            .card-body {
                padding: var(--space-lg);
            }
            
            .register-title {
                font-size: 1.4rem;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

        /* Terms and Privacy Links */
        .terms-privacy {
            font-size: 0.85rem;
            color: var(--gray-500);
            text-align: center;
            margin-top: var(--space-md);
            line-height: 1.4;
        }

        .terms-privacy a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .terms-privacy a:hover {
            text-decoration: underline;
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
        <i class="fas fa-clipboard-check floating-icon"></i>
        <i class="fas fa-thermometer floating-icon"></i>
    </div>

    <!-- Register Form -->
    <div class="register-wrapper">
        <div class="register-container animate-fade-in">
            <div class="register-card">
                <!-- Header -->
                <div class="card-header">
                    <div class="logo-container">
                        <div class="logo-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                    <h1 class="register-title">Create Patient Account</h1>
                    <p class="register-subtitle">Join our dental care family today</p>
                </div>

                <!-- Body -->
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($success)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="register.php" id="registerForm">
                        <!-- Full Name Field -->
                        <div class="form-group">
                            <label for="name" class="form-label">
                                <i class="fas fa-user me-1"></i>
                                Full Name
                            </label>
                            <div class="input-group">
                                <i class="fas fa-user input-group-icon"></i>
                                <input type="text" 
                                       name="name" 
                                       id="name"
                                       class="form-control" 
                                       required 
                                       placeholder="Enter your full name"
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                            </div>
                        </div>

                        <!-- Email and Phone Row -->
                        <div class="form-row">
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
                                           placeholder="your@email.com"
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                            </div>

                            <!-- Phone Field -->
                            <div class="form-group">
                                <label for="phone" class="form-label">
                                    <i class="fas fa-phone me-1"></i>
                                    Phone Number
                                </label>
                                <div class="input-group">
                                    <i class="fas fa-phone input-group-icon"></i>
                                    <input type="tel" 
                                           name="phone" 
                                           id="phone"
                                           class="form-control" 
                                           required 
                                           placeholder="1234567890"
                                           pattern="[0-9]{8,15}"
                                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                </div>
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
                                       placeholder="Create a secure password"
                                       minlength="6"
                                       autocomplete="new-password"
                                       data-lpignore="true"
                                       data-form-type="other">
                                <button type="button" class="password-toggle" onclick="togglePassword('password', 'toggleIcon1')">
                                    <i class="fas fa-eye" id="toggleIcon1"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                        </div>

                        <!-- Confirm Password Field -->
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-lock me-1"></i>
                                Confirm Password
                            </label>
                            <div class="input-group">
                                <i class="fas fa-lock input-group-icon"></i>
                                <input type="password" 
                                       name="confirm_password" 
                                       id="confirm_password"
                                       class="form-control" 
                                       required 
                                       placeholder="Confirm your password"
                                       minlength="6"
                                       autocomplete="new-password"
                                       data-lpignore="true"
                                       data-form-type="other">
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                                    <i class="fas fa-eye" id="toggleIcon2"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Register Button -->
                        <button type="submit" name="register" class="btn btn-primary w-100 mb-3" id="registerBtn">
                            <i class="fas fa-user-plus me-2"></i>
                            Create Account
                        </button>
                    </form>

                    <!-- Terms and Privacy -->
                    <div class="terms-privacy">
                        By creating an account, you agree to our 
                        <a href="../terms.php">Terms of Service</a> and 
                        <a href="../privacy.php">Privacy Policy</a>
                    </div>

                    <!-- Divider -->
                    <div class="divider">
                        <span>Already have an account?</span>
                    </div>

                    <!-- Login Link -->
                    <a href="login.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Sign In to Your Account
                    </a>
                </div>

                <!-- Footer -->
                <div class="register-footer">
                    <i class="fas fa-shield-alt me-1"></i>
                    Your personal information is protected and secure
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password visibility toggle
        function togglePassword(fieldId, iconId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(iconId);
            
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

        // Password strength checker
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('strengthBar');
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            
            // Character variety checks
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Apply strength class
            strengthBar.className = 'strength-bar';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }

        // Enhanced form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const inputs = form.querySelectorAll('input[required]');
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirm_password');
            
            // Real-time validation feedback
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    validateField(this);
                });
                
                input.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid')) {
                        validateField(this);
                    }
                    
                    // Special handling for password strength
                    if (this.id === 'password') {
                        checkPasswordStrength(this.value);
                    }
                    
                    // Check password match
                    if (this.id === 'confirm_password' || this.id === 'password') {
                        checkPasswordMatch();
                    }
                });
            });

            function validateField(field) {
                let isValid = field.checkValidity();
                
                // Additional phone validation
                if (field.id === 'phone') {
                    const phonePattern = /^[0-9]{8,15}$/;
                    isValid = phonePattern.test(field.value);
                }
                
                if (isValid) {
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                } else {
                    field.classList.remove('is-valid');
                    field.classList.add('is-invalid');
                }
                
                return isValid;
            }

            function checkPasswordMatch() {
                const password = passwordField.value;
                const confirmPassword = confirmPasswordField.value;
                
                if (confirmPassword && password !== confirmPassword) {
                    confirmPasswordField.classList.add('is-invalid');
                    confirmPasswordField.classList.remove('is-valid');
                } else if (confirmPassword) {
                    confirmPasswordField.classList.remove('is-invalid');
                    confirmPasswordField.classList.add('is-valid');
                }
            }

            // Form submission handler
            form.addEventListener('submit', function(e) {
                const registerBtn = document.getElementById('registerBtn');
                let isFormValid = true;
                
                // Validate all fields
                inputs.forEach(input => {
                    if (!validateField(input)) {
                        isFormValid = false;
                    }
                });
                
                // Check password match
                if (passwordField.value !== confirmPasswordField.value) {
                    isFormValid = false;
                    showAlert('Passwords do not match.', 'danger');
                    e.preventDefault();
                    return;
                }
                
                // Check password strength
                if (passwordField.value.length < 6) {
                    isFormValid = false;
                    showAlert('Password must be at least 6 characters long.', 'danger');
                    e.preventDefault();
                    return;
                }
                
                if (!isFormValid) {
                    e.preventDefault();
                    showAlert('Please correct the errors in the form.', 'danger');
                    return;
                }

                // Show loading state
                registerBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
                registerBtn.classList.add('btn-loading');
            });

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
                const form = document.getElementById('registerForm');
                form.parentNode.insertBefore(alertDiv, form);

                // Auto-hide after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }
        });

        // Auto-focus first input
        window.addEventListener('load', function() {
            document.getElementById('name').focus();
        });

        // Format phone number input
        document.getElementById('phone').addEventListener('input', function(e) {
            // Remove any non-digit characters
            this.value = this.value.replace(/\D/g, '');
        });
    </script>
</body>
</html>
?>

