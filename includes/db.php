<?php
// Set timezone for Malaysia
date_default_timezone_set("Asia/Kuala_Lumpur");

// 用于调试
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";
$password = "";
$database = "hospital_management_system";

// 创建连接
$conn = new mysqli($servername, $username, $password, $database);

// 检查连接
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 设置字符集
$conn->set_charset("utf8mb4");

// 设置MySQL时区为马来西亚时区 (UTC+8)
$conn->query("SET time_zone = '+08:00'");

// 调试用
// echo "数据库连接成功！";
?>