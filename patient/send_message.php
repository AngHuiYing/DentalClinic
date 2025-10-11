<?php
// 启用错误报告用于调试
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 检查所有必需的 POST 数据是否存在
    if (!isset($_POST['name']) || !isset($_POST['email']) || !isset($_POST['tel']) || !isset($_POST['message'])) {
        echo "<script>
            alert('Missing required form data. Please try again.'); 
            window.history.back();
        </script>";
        exit;
    }

    // 使用现有的数据库连接文件
    include "../includes/db.php";

    // 获取表单数据，并防止 SQL 注入
    $name = $conn->real_escape_string(trim($_POST['name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $tel = $conn->real_escape_string(trim($_POST['tel']));
    $message = $conn->real_escape_string(trim($_POST['message']));

    // 验证数据不为空
    if (empty($name) || empty($email) || empty($message)) {
        echo "<script>
            alert('Please fill in all required fields.'); 
            window.history.back();
        </script>";
        exit;
    }

    // 验证邮箱格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>
            alert('Please enter a valid email address.'); 
            window.history.back();
        </script>";
        exit;
    }

    // 插入数据到 messages 表
    $sql = "INSERT INTO messages (name, email, tel, message) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("ssss", $name, $email, $tel, $message);
        
        if ($stmt->execute()) {
            echo "<script>
                alert('Message sent successfully!'); 
                window.location.href='dashboard.php';
            </script>";
        } else {
            echo "<script>
                alert('Database error: " . addslashes($stmt->error) . "'); 
                window.history.back();
            </script>";
        }
        
        $stmt->close();
    } else {
        echo "<script>
            alert('Database prepare error: " . addslashes($conn->error) . "'); 
            window.history.back();
        </script>";
    }

    $conn->close();
} else {
    // 如果不是 POST 请求，重定向回消息页面
    header("Location: message.php");
    exit;
}
?>