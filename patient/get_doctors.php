<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域请求（如果前端和后端不在同一域）
header('Access-Control-Allow-Methods: GET');

include '../includes/db.php';

$sql = "SELECT id, name FROM doctors"; // 确保表名正确
$result = $conn->query($sql);

$doctors = [];
while ($row = $result->fetch_assoc()) {
    $doctors[] = $row;
}

// 确保没有额外的输出
echo json_encode($doctors, JSON_UNESCAPED_UNICODE);
exit; // 避免 PHP 继续执行
?>
