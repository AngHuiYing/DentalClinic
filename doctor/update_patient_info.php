<?php
session_start();
include '../includes/db.php';

// 检查是否为医生用户
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    http_response_code(403);
    echo "Unauthorized access";
    exit;
}

// 检查是否为POST请求且包含必要的参数
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'update_patient_info') {
    http_response_code(400);
    echo "Invalid request";
    exit;
}

// 获取并验证输入数据
$patient_email = trim($_POST['patient_email'] ?? '');
$name = trim($_POST['name'] ?? '');
$date_of_birth = trim($_POST['date_of_birth'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$phone = trim($_POST['phone'] ?? '');

// 验证必填字段
if (empty($patient_email) || empty($name) || empty($date_of_birth) || empty($gender) || empty($phone)) {
    echo "All fields are required";
    exit;
}

// 验证邮箱格式
if (!filter_var($patient_email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email format";
    exit;
}

// 验证日期格式
$date_obj = DateTime::createFromFormat('Y-m-d', $date_of_birth);
if (!$date_obj || $date_obj->format('Y-m-d') !== $date_of_birth) {
    echo "Invalid date format";
    exit;
}

// 验证性别值
if (!in_array($gender, ['male', 'female', 'other'])) {
    echo "Invalid gender value";
    exit;
}

// 验证医生权限 - 确保该患者是医生的患者
$user_id = $_SESSION['user_id'];
$doctor_stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
$doctor_stmt->bind_param("i", $user_id);
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();
$doctor = $doctor_result->fetch_assoc();

if (!$doctor) {
    echo "Doctor record not found";
    exit;
}

$doctor_id = $doctor['id'];

// 验证该患者是医生的确诊患者
$auth_stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE doctor_id = ? AND patient_email = ? AND status = 'confirmed'
");
$auth_stmt->bind_param("is", $doctor_id, $patient_email);
$auth_stmt->execute();
$auth_result = $auth_stmt->get_result()->fetch_assoc();

if ($auth_result['count'] == 0) {
    echo "Unauthorized: Patient is not assigned to this doctor";
    exit;
}

try {
    // 开始事务
    $conn->begin_transaction();
    
    // 更新用户信息
    $update_stmt = $conn->prepare("
        UPDATE users 
        SET name = ?, date_of_birth = ?, gender = ?, phone = ?, updated_at = NOW() 
        WHERE email = ?
    ");
    $update_stmt->bind_param("sssss", $name, $date_of_birth, $gender, $phone, $patient_email);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update user information");
    }
    
    // 检查是否有行被更新
    if ($update_stmt->affected_rows == 0) {
        // 如果没有找到用户，可能需要创建用户记录
        $insert_stmt = $conn->prepare("
            INSERT INTO users (name, email, date_of_birth, gender, phone, user_role, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, 'patient', NOW(), NOW())
        ");
        $insert_stmt->bind_param("sssss", $name, $patient_email, $date_of_birth, $gender, $phone);
        
        if (!$insert_stmt->execute()) {
            throw new Exception("Failed to create user record");
        }
    }
    
    // 提交事务
    $conn->commit();
    echo "success";
    
} catch (Exception $e) {
    // 回滚事务
    $conn->rollback();
    echo "Database error: " . $e->getMessage();
}
?>