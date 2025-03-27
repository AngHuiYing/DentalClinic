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
        $reset_link = "http://localhost/Hospital_Management_System/patient/reset_password.php?token=" . $token;

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
            $mail->setFrom('huiyingsyzz@gmail.com', 'Hospital Management System');
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
    <title>Forgot Password - Health Care Clinic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="d-flex align-items-center min-vh-100 py-5">
    <div class="container">
        <div class="login-container">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <h2 class="text-center">Forgot Password</h2>
                    <p class="text-center text-muted">Enter your email to reset your password.</p>

                    <?php echo $message; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" required placeholder="Enter your email">
                        </div>
                        <button type="submit" name="submit" class="btn btn-primary w-100">
                            Send Reset Link
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
