<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 连接数据库
    $conn = new mysqli("localhost", "root", "", "hospital_management_system");

    if ($conn->connect_error) {
        die("Database Connection Failed: " . $conn->connect_error);
    }

    // 获取表单数据，并防止 SQL 注入
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $tel = $conn->real_escape_string($_POST['tel']);
    $message = $conn->real_escape_string($_POST['message']);

    // 插入数据到 messages 表（tel 不能加引号）
    $sql = "INSERT INTO messages (name, email, tel, message) VALUES ('$name', '$email', '$tel', '$message')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Message sent successfully!'); window.location.href='index.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }

    $conn->close();
}
?>