<?php
session_start();
include_once('../includes/db.php');
require '../vendor/autoload.php'; // 引入 PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Kuala_Lumpur'); // 设置为马来西亚时区

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $email = trim($_POST['email']);

    // 检查邮箱是否存在
    $sql = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // 生成唯一的 token
        $token = bin2hex(random_bytes(32));
        $expires_at = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // 插入到 password_resets 表
        $sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $email, $token, $expires_at);
        $stmt->execute();

        // 发送重置邮件
        $reset_link = "http://localhost/Dental_Clinic/patient/reset_password.php?token=" . $token;

        $mail = new PHPMailer(true);
        try {
            // 配置 SMTP 服务器
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Gmail SMTP 服务器
            $mail->SMTPAuth = true;
            $mail->Username = 'huiyingsyzz@gmail.com'; // 你的 Gmail 账号
            $mail->Password = 'exjs cyot yibs cgya'; // **注意：不能直接使用 Gmail 密码**
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // 发件人和收件人
            $mail->setFrom('huiyingsyzz@gmail.com', 'Green Life Dental Clinic');
            $mail->addAddress($email);

            // 邮件内容
            $mail->isHTML(true);
            $mail->Subject = "Password Reset Request";
            $mail->Body    = "Click the link below to reset your password:<br><a href='$reset_link'>$reset_link</a>";

            $mail->send();
            $message = "<div class='alert alert-success'>A password reset link has been sent to your email.</div>";
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>Email sending failed: {$mail->ErrorInfo}</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Email not found. Please try again.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Green Life Dental Clinic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .forgot-container {
            max-width: 400px;
            margin: 0 auto;
            margin-top: 10vh;
        }

        .forgot-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #2563eb, #059669);
            color: white;
            text-align: center;
            padding: 2.5rem 2rem;
        }

        .card-header i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .card-header h2 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .card-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .card-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            height: 3rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .btn-primary {
            width: 100%;
            height: 3rem;
            background: linear-gradient(135deg, #2563eb, #059669);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }

        .back-link {
            display: inline-block;
            color: #6b7280;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            margin-top: 1rem;
        }

        .back-link:hover {
            color: #2563eb;
            text-decoration: none;
        }

        .alert {
            border: none;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-danger {
            background-color: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        @media (max-width: 576px) {
            .forgot-container {
                margin-top: 5vh;
            }
            
            .card-header {
                padding: 2rem 1.5rem;
            }
            
            .card-body {
                padding: 1.5rem;
            }
            
            .card-header h2 {
                font-size: 1.5rem;
            }
            
            .card-header i {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-card">
            <div class="card-header">
                <i class="fas fa-key"></i>
                <h2>Forgot Password</h2>
                <p>Enter your email to reset your password</p>
            </div>
            
            <div class="card-body">
                <?php echo $message; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-2"></i>Email Address
                        </label>
                        <input type="email" name="email" id="email" class="form-control" 
                               placeholder="Enter your email address" required>
                    </div>

                    <button type="submit" name="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>
                        Send Reset Link
                    </button>
                </form>

                <div class="text-center">
                    <a href="login.php" class="back-link">
                        <i class="fas fa-arrow-left me-2"></i>
                        Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
