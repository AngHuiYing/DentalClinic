<?php
session_start();
include_once('../includes/db.php');

// 如果病人已登录，直接跳转到仪表板
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'patient') {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // 检查用户是否存在且是病人
    $sql = "SELECT id, name, password FROM users WHERE email = ? AND role = 'patient'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = 'patient';
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Password uncorrect, please try again.";
        }
    } else {
        $error = "This email did not register yet or it's not patient account.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Login - Health Care Clinic</title>
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
    </style>
</head>
<body class="d-flex align-items-center min-vh-100 py-5">
    <div class="container">
        <div class="login-container">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <img src="../assets/images/clinic-logo.png" alt="Clinic Logo" class="clinic-logo" onerror="this.style.display='none'">
                        <h2 class="mb-2">Patient Login</h2>
                        <p class="text-muted">Access your patient portal</p>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">
                        <div class="mb-3">
                            <label class="form-label">Patient Email</label>
                            <input type="email" name="email" class="form-control" required 
                                placeholder="Enter your email">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required 
                                placeholder="Enter your password">
                        </div>
                        <button type="submit" name="login" class="btn btn-primary w-100 mb-3">
                            Login as Patient
                        </button>
                        <div class="text-center mt-2">
                            <a href="forgot_password.php" class="text-decoration-none">Forgot Password?</a>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="register.php" class="text-decoration-none">New patient? Register here</a>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <a href="../doctor/login.php" class="text-decoration-none">Are you a doctor? Login here</a>
                        <br>
                        <br>
                        <a href="../index.php" class="text-decoration-none">Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 