
<?php
// 設置 PHP 時區
date_default_timezone_set("Asia/Kuala_Lumpur");

$servername = "localhost";
$username = "root";
$password = "";
$database = "hospital_management_system";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 設置 MySQL 時區為馬來西亞吉隆坡時區 (UTC+8)
$conn->query("SET time_zone = '+08:00'");
?>