<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 获取表单数据
    $name = htmlspecialchars(trim($_POST['name']));
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $message = htmlspecialchars(trim($_POST['message']));

    // 验证数据
    if (empty($name) || empty($email) || empty($message)) {
        echo "<script>alert('All fields are required!'); history.back();</script>";
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email format!'); history.back();</script>";
        exit;
    }

    // 连接数据库
    include 'db.php';

    // 存入数据库
    $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $name, $email, $message);

    if ($stmt->execute()) {
        // 发送邮件通知管理员
        $to = "huiyingsyzz@gmail.com"; // 替换为管理员邮箱
        $subject = "New Contact Form Submission";
        $body = "You have received a new message from $name ($email):\n\n$message";
        $headers = "From: noreply@clinic.com";

        mail($to, $subject, $body, $headers);

        echo "<script>alert('Message sent successfully! We will get back to you soon.'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Failed to send message. Please try again.'); history.back();</script>";
    }

    $stmt->close();
    $conn->close();
}
?>
