<?php
session_start();
include_once('../includes/db.php');

date_default_timezone_set('Asia/Kuala_Lumpur'); // 设置为马来西亚时区

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

    echo "<div class='alert alert-success'>Password reset successfully! <a href='login.php'>Login now</a></div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Health Care Clinic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="d-flex align-items-center min-vh-100 py-5">
    <div class="container">
        <div class="login-container">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <h2 class="text-center">Reset Password</h2>
                    <p class="text-center text-muted">Enter your new password</p>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" name="reset_password" class="btn btn-success w-100">
                            Reset Password
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
