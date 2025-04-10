<?php
session_start();
include "db.php"; // 连接数据库

$search = ""; // 默认搜索为空
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// 查询所有医生，如果有搜索则筛选
$sql = "SELECT * FROM doctors WHERE name LIKE ? OR specialty LIKE ? OR location LIKE ?";
$stmt = $conn->prepare($sql);
$searchTerm = "%$search%";
$stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Doctors</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
        }
        h2 {
            text-align: center;
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .search-box {
            text-align: center;
            margin-bottom: 20px;
        }
        .search-box input {
            width: 60%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .search-box button {
            padding: 10px 15px;
            border: none;
            background: #2980b9;
            color: white;
            border-radius: 5px;
            cursor: pointer;
        }
        .search-box button:hover {
            background: #1a5276;
        }
        .doctor-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
        }
        .doctor-card {
            display: flex;
            align-items: center;
            background: white;
            padding: 20px;
            width: 500px;
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
        .doctor-info h3 {
            font-size: 20px;
            margin-bottom: 5px;
        }
        .doctor-info p {
            font-size: 14px;
            color: #555;
        }
        .btn {
            display: inline-block;
            background: #2980b9;
            color: #fff;
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            margin-top: 10px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #1a5276;
        }
    </style>
</head>
<body>

<?php include '../Hospital_Management_System/includes/navbar.php'; ?>

<div class="container">
    <h2>All Doctors</h2>

    <!-- 搜索框 -->
    <div class="search-box">
        <form method="GET">
            <input type="text" name="search" placeholder="Search by name, specialty, or location" value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="doctor-list">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($doctor = $result->fetch_assoc()): ?>
                <div class="doctor-card">
                    <img src="uploads/<?= $doctor['image'] ?>" alt="<?= htmlspecialchars($doctor['name']) ?>">
                    <div class="doctor-info">
                        <h3>Dr. <?= htmlspecialchars($doctor['name']) ?></h3>
                        <p><strong>Specialty:</strong> <?= htmlspecialchars($doctor['specialty']) ?></p>
                        <p><strong>Experience:</strong> <?= htmlspecialchars($doctor['experience']) ?> years</p>
                        <p><strong>Location:</strong> <?= htmlspecialchars($doctor['location']) ?></p>

                        <a href="../Hospital_Management_System/doctor/doctor_details.php?id=<?= $doctor['id'] ?>" class="btn">View Details</a>

                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="../Hospital_Management_System/patient/book_appointment.php?doctor=<?= $doctor['id'] ?>" class="btn">Book Appointment</a>
                        <?php else: ?>
                            <a href="../Hospital_Management_System/patient/login.php" class="btn">Login to Book</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align: center; color: red;">No doctors found.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
