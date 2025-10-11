<?php
session_start();
include_once('../includes/db.php');

date_default_timezone_set('Asia/Kuala_Lumpur'); // 设置为马来西亚时区

$message = "";

if (!isset($_GET['token'])) {
    die("Invalid request.");
}

$token = $_GET['token'];
$sql = "SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Invalid or expired token.");
}

$row = $result->fetch_assoc();
$email = $row['email'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // 更新密码
    $sql = "UPDATE users SET password = ? WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $new_password, $email);
    $stmt->execute();

    // 删除 token
    $sql = "DELETE FROM password_resets WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $message = "<div class='alert alert-success'>Password reset successfully! <a href='../patient/login.php'>Login now</a></div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Dental Clinic</title>
    
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
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--clinic-light) 0%, #e6f4ea 50%, var(--clinic-warm) 100%);
            color: var(--clinic-text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
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
                radial-gradient(circle at 20% 20%, rgba(45, 90, 160, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(74, 147, 150, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(132, 198, 155, 0.03) 0%, transparent 50%);
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
            color: rgba(45, 90, 160, 0.08);
            font-size: 2rem;
            animation: float 8s ease-in-out infinite;
        }

        .floating-icon:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; }
        .floating-icon:nth-child(2) { top: 20%; right: 20%; animation-delay: 2s; }
        .floating-icon:nth-child(3) { top: 70%; left: 15%; animation-delay: 4s; }
        .floating-icon:nth-child(4) { top: 80%; right: 10%; animation-delay: 6s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.1; }
            50% { transform: translateY(-20px) rotate(180deg); opacity: 0.05; }
        }

        .reset-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }

        .reset-card {
            background: var(--clinic-white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--clinic-shadow-hover);
            border: 1px solid rgba(45, 90, 160, 0.1);
            position: relative;
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

        .security-icon {
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
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        .card-body-custom {
            padding: 2rem;
        }

        .security-notice {
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.1) 0%, rgba(74, 147, 150, 0.1) 100%);
            border: 1px solid rgba(39, 174, 96, 0.2);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .security-notice i {
            color: var(--clinic-success);
            font-size: 1.2rem;
        }

        .security-notice p {
            color: var(--clinic-text);
            font-size: 0.9rem;
            font-weight: 500;
            margin: 0;
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

        .form-control:focus + .input-icon {
            color: var(--clinic-primary);
            transform: translateY(-50%) scale(1.1);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--clinic-muted);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--clinic-primary);
            background: rgba(45, 90, 160, 0.1);
        }

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }

        .strength-bar {
            height: 4px;
            background: #e8ecef;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { background: var(--clinic-danger); width: 25%; }
        .strength-medium { background: var(--clinic-warning); width: 60%; }
        .strength-strong { background: var(--clinic-success); width: 100%; }

        .btn-reset {
            width: 100%;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--clinic-primary) 0%, var(--clinic-secondary) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-reset::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-reset:hover::before {
            left: 100%;
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: var(--clinic-shadow-hover);
        }

        .alert {
            border-radius: 12px;
            border: none;
            margin-bottom: 1.5rem;
            padding: 1rem 1.25rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.1) 0%, rgba(74, 147, 150, 0.1) 100%);
            color: var(--clinic-success);
            border-left: 4px solid var(--clinic-success);
        }

        .footer-links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(45, 90, 160, 0.1);
        }

        .footer-links a {
            color: var(--clinic-primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--clinic-secondary);
            text-decoration: none;
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem 0.5rem;
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

            .security-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
        }

        .reset-card {
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
    </style>
</head>
<body>
    <!-- Floating medical elements -->
    <div class="floating-elements">
        <i class="fas fa-shield-alt floating-icon"></i>
        <i class="fas fa-lock floating-icon"></i>
        <i class="fas fa-key floating-icon"></i>
        <i class="fas fa-user-shield floating-icon"></i>
    </div>

    <div class="reset-container">
        <div class="reset-card">
            <div class="card-header-custom">
                <div class="header-content">
                    <div class="security-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h1 class="card-title">Reset Password</h1>
                    <p class="card-subtitle">Create a new secure password for your account</p>
                </div>
            </div>
            
            <div class="card-body-custom">
                <div class="security-notice">
                    <i class="fas fa-info-circle"></i>
                    <p>Choose a strong password with at least 8 characters including uppercase, lowercase, numbers and special characters.</p>
                </div>

                <?php if (!empty($message)) echo $message; ?>

                <form method="POST" action="" id="resetForm">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-key"></i>
                            New Password
                        </label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   name="password" 
                                   id="password"
                                   class="form-control" 
                                   placeholder="Enter your new password"
                                   required
                                   minlength="6">
                            <i class="fas fa-lock input-icon"></i>
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="password-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="password-strength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strength-fill"></div>
                            </div>
                            <small id="strength-text" class="text-muted mt-1">Enter password to check strength</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-check-circle"></i>
                            Confirm Password
                        </label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   name="confirm_password" 
                                   id="confirm_password"
                                   class="form-control" 
                                   placeholder="Confirm your new password"
                                   required>
                            <i class="fas fa-shield-check input-icon"></i>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="confirm_password-eye"></i>
                            </button>
                        </div>
                        <small id="match-text" class="mt-1"></small>
                    </div>

                    <button type="submit" name="reset_password" class="btn-reset" id="submitBtn">
                        <i class="fas fa-check-circle me-2"></i>
                        Reset Password
                    </button>
                </form>

                <div class="footer-links">
                    <a href="login.php">
                        <i class="fas fa-arrow-left"></i>
                        Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(fieldId + '-eye');
            
            if (field.type === 'password') {
                field.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }

        function checkPasswordStrength(password) {
            let strength = 0;
            let feedback = [];

            // Length check
            if (password.length >= 8) strength += 1;
            else feedback.push('at least 8 characters');

            // Uppercase check
            if (/[A-Z]/.test(password)) strength += 1;
            else feedback.push('uppercase letter');

            // Lowercase check
            if (/[a-z]/.test(password)) strength += 1;
            else feedback.push('lowercase letter');

            // Number check
            if (/\d/.test(password)) strength += 1;
            else feedback.push('number');

            // Special character check
            if (/[^A-Za-z\d]/.test(password)) strength += 1;
            else feedback.push('special character');

            return { strength, feedback };
        }

        function updatePasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthFill = document.getElementById('strength-fill');
            const strengthText = document.getElementById('strength-text');

            if (password.length === 0) {
                strengthFill.className = 'strength-fill';
                strengthText.textContent = 'Enter password to check strength';
                strengthText.className = 'text-muted mt-1';
                return;
            }

            const { strength, feedback } = checkPasswordStrength(password);

            // Update strength bar
            strengthFill.className = 'strength-fill';
            if (strength <= 2) {
                strengthFill.classList.add('strength-weak');
                strengthText.textContent = 'Weak - Add: ' + feedback.slice(0, 2).join(', ');
                strengthText.className = 'text-danger mt-1';
            } else if (strength <= 3) {
                strengthFill.classList.add('strength-medium');
                strengthText.textContent = 'Medium - Consider adding: ' + feedback.join(', ');
                strengthText.className = 'text-warning mt-1';
            } else {
                strengthFill.classList.add('strength-strong');
                strengthText.textContent = 'Strong password!';
                strengthText.className = 'text-success mt-1';
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('match-text');
            const submitBtn = document.getElementById('submitBtn');

            if (confirmPassword.length === 0) {
                matchText.textContent = '';
                return;
            }

            if (password === confirmPassword) {
                matchText.textContent = '✓ Passwords match';
                matchText.className = 'text-success mt-1';
                submitBtn.disabled = false;
            } else {
                matchText.textContent = '✗ Passwords do not match';
                matchText.className = 'text-danger mt-1';
                submitBtn.disabled = true;
            }
        }

        // Event listeners
        document.getElementById('password').addEventListener('input', function() {
            updatePasswordStrength();
            checkPasswordMatch();
        });

        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

        // Form validation
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }

            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return;
            }
        });
    </script>
</body>
</html>
