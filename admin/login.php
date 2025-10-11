<?php
session_start();
include '../includes/db.php';

// 用于调试的错误显示
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 如果管理员已登录，直接跳转到仪表板
if (isset($_SESSION['admin_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // 检查管理员是否存在
    $sql = "SELECT * FROM admin WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        // 如果是默认密码 'admin123'，则重新生成哈希
        if ($password === 'admin123') {
            $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
            // 更新数据库中的密码哈希
            $update_sql = "UPDATE admin SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $hashed_password, $admin['id']);
            $update_stmt->execute();
            $admin['password'] = $hashed_password;
        }
        
        if (password_verify($password, $admin['password'])) {
            // 设置管理员会话
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['user_role'] = 'admin';
            
            // 更新最后登录时间
            $update_sql = "UPDATE admin SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $admin['id']);
            $update_stmt->execute();
            
            // 记录登录操作
            $admin_id = $admin['id'];
            $action = "Admin logged in: " . $admin['name'];
            $conn->query("INSERT INTO user_logs (admin_id, action) VALUES ('$admin_id', '$action')");
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Incorrect password. Please try again.";
            // 调试信息
            error_log("Password verification failed. Input: " . $password);
            error_log("Stored hash: " . $admin['password']);
        }
    } else {
        $error = "The admin account does not exist.";
        // 调试信息
        error_log("Admin not found. Username/Email: " . $username);
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Dental Clinic Administration</title>
    
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
            --admin-primary: #c0392b;
            --admin-secondary: #e74c3c;
            --admin-dark: #8b2635;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fdeaea 0%, var(--clinic-light) 50%, var(--clinic-warm) 100%);
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
                radial-gradient(circle at 20% 20%, rgba(192, 57, 43, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(45, 90, 160, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(74, 147, 150, 0.03) 0%, transparent 50%);
            z-index: -1;
            pointer-events: none;
        }

        /* Floating admin elements */
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
            color: rgba(192, 57, 43, 0.08);
            font-size: 2rem;
            animation: float 8s ease-in-out infinite;
        }

        .floating-icon:nth-child(1) { top: 15%; left: 10%; animation-delay: 0s; }
        .floating-icon:nth-child(2) { top: 25%; right: 15%; animation-delay: 2s; }
        .floating-icon:nth-child(3) { top: 65%; left: 20%; animation-delay: 4s; }
        .floating-icon:nth-child(4) { top: 75%; right: 10%; animation-delay: 6s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.1; }
            50% { transform: translateY(-20px) rotate(180deg); opacity: 0.05; }
        }

        .login-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }

        .admin-login-card {
            background: var(--clinic-white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--clinic-shadow-hover);
            border: 1px solid rgba(192, 57, 43, 0.1);
            position: relative;
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
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

        .admin-icon {
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

        .admin-notice {
            background: linear-gradient(135deg, rgba(192, 57, 43, 0.1) 0%, rgba(231, 76, 60, 0.1) 100%);
            border: 1px solid rgba(192, 57, 43, 0.2);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .admin-notice i {
            color: var(--admin-primary);
            font-size: 1.2rem;
        }

        .admin-notice p {
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
            border-color: var(--admin-primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(192, 57, 43, 0.1);
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
            color: var(--admin-primary);
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
            z-index: 15;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Hide browser default password toggles */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }
        
        input[type="password"]::-webkit-contacts-auto-fill-button,
        input[type="password"]::-webkit-credentials-auto-fill-button {
            visibility: hidden;
            display: none !important;
            pointer-events: none;
            height: 0;
            width: 0;
            margin: 0;
        }

        .password-toggle:hover {
            color: var(--admin-primary);
            background: rgba(192, 57, 43, 0.1);
        }

        .btn-admin {
            width: 100%;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
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

        .btn-admin::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-admin:hover::before {
            left: 100%;
        }

        .btn-admin:hover {
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

        .alert-danger {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.1) 0%, rgba(192, 57, 43, 0.1) 100%);
            color: var(--admin-primary);
            border-left: 4px solid var(--admin-primary);
        }

        .footer-links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(192, 57, 43, 0.1);
        }

        .footer-links a {
            color: var(--admin-primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            margin: 0 1rem;
        }

        .footer-links a:hover {
            color: var(--admin-secondary);
            text-decoration: none;
            transform: translateY(-1px);
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-top: 0.5rem;
            backdrop-filter: blur(10px);
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

            .admin-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .footer-links a {
                display: block;
                margin: 0.5rem 0;
            }
        }

        .admin-login-card {
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
<?php include '../includes/navbar.php'; ?>
<body>
    <!-- Floating admin elements -->
    <div class="floating-elements">
        <i class="fas fa-user-cog floating-icon"></i>
        <i class="fas fa-shield-alt floating-icon"></i>
        <i class="fas fa-cogs floating-icon"></i>
        <i class="fas fa-chart-line floating-icon"></i>
    </div>

    <div class="login-container">
        <div class="admin-login-card">
            <div class="card-header-custom">
                <div class="header-content">
                    <div class="admin-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h1 class="card-title">Administrator Portal</h1>
                    <p class="card-subtitle">Secure access to clinic management</p>
                    <div class="role-badge">
                        <i class="fas fa-crown"></i>
                        Admin Access Only
                    </div>
                </div>
            </div>
            
            <div class="card-body-custom">
                <div class="admin-notice">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>This is a restricted area. Only authorized administrators can access this portal.</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" id="adminLoginForm">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i>
                            Username or Email
                        </label>
                        <div class="input-wrapper">
                            <input type="text" 
                                   name="username" 
                                   class="form-control" 
                                   placeholder="Enter administrator username or email"
                                   required 
                                   autocomplete="username">
                            <i class="fas fa-user-tie input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-key"></i>
                            Password
                        </label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   name="password" 
                                   id="adminPassword"
                                   class="form-control" 
                                   placeholder="Enter administrator password"
                                   required
                                   autocomplete="current-password"
                                   data-lpignore="true"
                                   data-form-type="other">
                            <i class="fas fa-lock input-icon"></i>
                            <button type="button" class="password-toggle" onclick="togglePassword('adminPassword')">
                                <i class="fas fa-eye" id="adminPassword-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="login" class="btn-admin">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Login as Administrator
                    </button>
                </form>

                <div class="footer-links">
                    <a href="../patient/forgot_password.php">
                        <i class="fas fa-question-circle"></i>
                        Forgot Password?
                    </a>
                    <a href="../index.php">
                        <i class="fas fa-home"></i>
                        Back to Home
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

        // Form validation and security
        document.getElementById('adminLoginForm').addEventListener('submit', function(e) {
            const username = this.username.value.trim();
            const password = this.password.value;

            if (username.length < 3) {
                e.preventDefault();
                alert('Please enter a valid username or email address.');
                return;
            }

            // if (password.length < 6) {
            //     e.preventDefault();
            //     alert('Password must be at least 6 characters long.');
            //     return;
            // }
        });

        // Security: Clear form data on page unload
        window.addEventListener('beforeunload', function() {
            const form = document.getElementById('adminLoginForm');
            if (form) {
                form.reset();
            }
        });

        // Auto-focus first input
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[name="username"]');
            if (firstInput) {
                firstInput.focus();
            }
        });
    </script>
</body>
</html> 