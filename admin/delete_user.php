<?php
session_start();
include '../includes/db.php';

// 检查管理员是否登录
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 仅允许 POST 请求
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];

    // 防止管理员删除自己
    if ($user_id == $_SESSION['admin_id']) {
        echo "<script>alert('You cannot delete your own account!'); window.location.href='manage_users.php';</script>";
        exit;
    }

    // 执行删除
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        // 记录删除日志
        $admin_id = $_SESSION['admin_id'];
        $action = "Deleted user with ID: $user_id";
        $conn->query("INSERT INTO user_logs (admin_id, action) VALUES ('$admin_id', '$action')");

        echo "<script>alert('User deleted successfully!'); window.location.href='manage_users.php';</script>";
    } else {
        echo "<script>alert('Failed to delete user.'); window.location.href='manage_users.php';</script>";
    }
} else {
    echo "<script>alert('Invalid request!'); window.location.href='manage_users.php';</script>";
}
?>
