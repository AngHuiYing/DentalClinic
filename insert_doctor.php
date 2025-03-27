<?php
include "db.php"; // 连接数据库

// 获取医生列表
$sql = "SELECT id, name, email FROM doctors WHERE user_id IS NULL";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $doctor_id = $row['id'];
        $name = $row['name'];
        $email = $row['email'];
        $password = password_hash("doctor123", PASSWORD_DEFAULT); // 默认密码
        $role = "doctor";

        // 插入医生账号到 users 表
        $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $name, $email, $password, $role);
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            // 更新 doctors 表，将 user_id 关联到医生
            $update_sql = "UPDATE doctors SET user_id = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $user_id, $doctor_id);
            $update_stmt->execute();
            echo "Doctor account created for: $name ($email)<br>";
        }
    }
} else {
    echo "All doctors already have accounts.";
}

$conn->close();
?>
