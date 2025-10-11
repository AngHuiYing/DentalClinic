<?php
// 简单测试 send_message.php 是否工作
session_start();

// 模拟表单提交
$_POST['name'] = 'Test User';
$_POST['email'] = 'test@example.com';
$_POST['tel'] = '123456789';
$_POST['message'] = 'This is a test message';
$_SERVER['REQUEST_METHOD'] = 'POST';

echo "Testing send_message.php functionality...<br>";

// 包含 send_message.php
include 'send_message.php';

echo "Test completed.";
?>