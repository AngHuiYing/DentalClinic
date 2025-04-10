<?php
session_start();
include "../db.php"; // 连接数据库

$doctorId = isset($_GET['id']) ? $_GET['id'] : 0;

// 查询医生信息
$sql = "SELECT * FROM doctors WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctorId);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Details</title>
    <style>
        /* 全局样式 */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }

        /* 导航栏 */
        .navbar {
            background: #005a8d;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .navbar .logo {
            font-size: 20px;
            font-weight: bold;
        }

        .navbar nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
        }

        .navbar nav ul li {
            margin: 0 10px;
        }

        .navbar nav ul li a {
            text-decoration: none;
            color: white;
            font-weight: bold;
            transition: color 0.3s;
        }

        .navbar nav ul li a:hover {
            color: #ffd700;
        }

        /* 容器 */
        .container {
            max-width: 800px;
            margin: 80px auto 20px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        h2 {
            color: #2c3e50;
            font-size: 26px;
            margin-bottom: 10px;
        }

        /* 医生头像 */
        .doctor-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 4px solid #3498db;
        }

        /* 医生信息 */
        .doctor-info {
            font-size: 16px;
            color: #555;
            line-height: 1.6;
            margin-bottom: 15px;
            text-align: left;
        }

        .doctor-info p {
            margin: 8px 0;
        }

        /* 让 "Book Appointment" 和 "Login to Book" 按钮居中 */
        .btn-container {
            margin-top: 20px;
        }

        /* 按钮样式 */
        .btn {
            display: inline-block;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            color: white;
            background: #2980b9;
            border-radius: 5px;
            transition: background 0.3s;
            margin: 5px;
        }

        .btn:hover {
            background: #1a5276;
        }

        .btn-secondary {
            background: #e74c3c;
        }

        .btn-secondary:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>

<?php 
if (isset($_SESSION['user_id'])) {
    include "../includes/navbar.php"; // 用户已登录，加载 ../includes/navbar.php
} else { ?>
    <header class="navbar">
        <div class="logo">🌿 Green Life Hospital</div>
        <nav>
            <ul>
                <li><a href="../index.php">Home</a></li>
                <li><a href="../book_appointment.php">Book Appointment</a></li>
                <li><a href="departments.php">Departments</a></li>
                <li><a href="about.php">About Us</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </nav>
    </header>
<?php } ?>
<br>
<br>

<div class="container">
    <?php if ($doctor): ?>
        <h2><?= $doctor['name'] ?></h2>
        <img src="<?= $doctor['image'] ?>" alt="<?= $doctor['name'] ?>" class="doctor-img">
        <div class="doctor-info">
            <p><strong>Specialty:</strong> <?= $doctor['specialty'] ?></p>
            <p><strong>Experience:</strong> <?= $doctor['experience'] ?> years</p>
            <p><strong>Location:</strong> <?= $doctor['location'] ?></p>
            <p><?= $doctor['bio'] ?></p>
        </div>

        <div class="btn-container">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="../patient/book_appointment.php?doctor=<?= $doctor['id'] ?>" class="btn">Book Appointment</a>
            <?php else: ?>
                <a href="../patient/login.php" class="btn btn-secondary">Login to Book</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p>Doctor not found.</p>
    <?php endif; ?>
</div>

</body>
</html>
