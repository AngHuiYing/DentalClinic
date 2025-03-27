<?php
include "db.php"; // 连接数据库

$name = "Admin";
$email = "admin@example.com";
$password = password_hash("123", PASSWORD_DEFAULT); // **PHP 先加密密码**
$role = "admin";

$sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $name, $email, $password, $role);

if ($stmt->execute()) {
    echo "Admin inserted successfully!";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
