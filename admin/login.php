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
            $error = "密码不正确。请重试。";
            // 调试信息
            error_log("Password verification failed. Input: " . $password);
            error_log("Stored hash: " . $admin['password']);
        }
    } else {
        $error = "管理员账号不存在。";
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
    <title>Admin Login - Health Care Clinic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 450px;
            margin: 0 auto;
            padding: 2rem;
        }
        .clinic-logo {
            width: 100px;
            height: 100px;
            margin-bottom: 1rem;
        }
        .admin-login-card {
            border-top: 4px solid #dc3545;
        }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100 py-5">
    <div class="container">
        <div class="login-container">
            <div class="card shadow-lg admin-login-card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <img src="../assets/images/clinic-logo.png" alt="Clinic Logo" class="clinic-logo" onerror="this.style.display='none'">
                        <h2 class="mb-2">Administrator Login</h2>
                        <p class="text-muted">Access clinic administration</p>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">
                        <div class="mb-3">
                            <label class="form-label">Username or Email</label>
                            <input type="text" name="username" class="form-control" required 
                                placeholder="Enter username or email">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required 
                                placeholder="Enter password">
                        </div>
                        <button type="submit" name="login" class="btn btn-danger w-100 mb-3">
                            Login as Administrator
                        </button>

                        <div class="text-center mt-2">
                            <a href="../patient/forgot_password.php" class="text-decoration-none">Forgot Password?</a>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <a href="../index.php" class="text-decoration-none">Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 