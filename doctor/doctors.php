<?php
session_start();
include "../db.php"; // 连接数据库

$dept = isset($_GET['dept']) ? $_GET['dept'] : '';
$search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';

// 获取该科室的医生（支持搜索）
$sql = "SELECT * FROM doctors WHERE department = ? AND (name LIKE ? OR specialty LIKE ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $dept, $search, $search);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($dept) ?> Department Doctors</title>
    <style>
        /* 页面样式 */
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
            max-width: 1000px;
            margin: 80px auto 20px;
            padding: 20px;
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 20px;
        }

        /* 搜索框 */
        .search-form {
            text-align: center;
            margin-bottom: 20px;
        }

        .search-form input {
            padding: 10px;
            width: 300px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
        }

        .search-form button {
            padding: 10px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-left: 5px;
            transition: background 0.3s;
        }

        .search-form button:hover {
            background: #0056b3;
        }

        /* 医生列表 */
        .doctor-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
        }

        /* 医生卡片 */
        .doctor-card {
            display: flex;
            align-items: center;
            background: white;
            padding: 20px;
            width: 700px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease-in-out;
        }

        .doctor-card:hover {
            transform: scale(1.02);
        }

        .doctor-card img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
        }

        .doctor-info {
            flex: 1;
        }

        /* 按钮 */
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            text-align: center;
            transition: background 0.3s, transform 0.2s;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: scale(1.05);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: scale(1.05);
        }

        .btn-secondary {
            background: #e74c3c;
            color: white;
        }

        .btn-secondary:hover {
            background: #c0392b;
            transform: scale(1.05);
        }

        /* 无医生提示 */
        .no-doctors {
            text-align: center;
            color: #777;
            font-size: 18px;
            font-weight: bold;
            padding: 50px;
        }
    </style>
</head>
<body>

<header class="navbar">
    <div class="logo">🌿 Green Life Hospital</div>
    <nav>
        <ul>
            <li><a href="../index.php">Home</a></li>
            <li><a href="../book_appointment.php">Book Appointment</a></li>
            <li><a href="../index.php#departments">Departments</a></li>
            <li><a href="../index.php#about">About Us</a></li>
            <li><a href="../index.php#contact">Contact</a></li>
        </ul>
    </nav>
</header>

<div class="container">
    <h2><?= ucfirst($dept) ?> Department Doctors</h2>

    <!-- 搜索表单 -->
    <form method="GET" action="" class="search-form">
        <input type="hidden" name="dept" value="<?= htmlspecialchars($dept) ?>">
        <input type="text" name="search" placeholder="Search by name or specialty" 
               value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
        <button type="submit">Search</button>
    </form>

    <div class="doctor-list">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($doctor = $result->fetch_assoc()): ?>
                <div class="doctor-card">
                    <img src="<?= htmlspecialchars($doctor['image']) ?>" alt="<?= htmlspecialchars($doctor['name']) ?>">
                    <div class="doctor-info">
                        <h3><?= htmlspecialchars($doctor['name']) ?></h3>
                        <p><strong>Specialty:</strong> <?= htmlspecialchars($doctor['specialty']) ?></p>
                        <p><strong>Experience:</strong> <?= htmlspecialchars($doctor['experience']) ?> years</p>
                        <p><strong>Location:</strong> <?= htmlspecialchars($doctor['location']) ?></p>
                    </div>
                    
                    <div class="button-group">
                        <a href="doctor_details.php?id=<?= $doctor['id'] ?>" class="btn btn-primary">View Details</a>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="appointment.php?doctor=<?= $doctor['id'] ?>" class="btn btn-success">Book</a>
                        <?php else: ?>
                            <a href="../patient/login.php" class="btn btn-secondary">Login to Book</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-doctors">No upcoming doctors</div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
