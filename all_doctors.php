<?php
session_start();
include "db.php"; // 连接数据库

$search = ""; // 默认搜索为空
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// 分页设置
$limit = 8; // 每页显示8个医生
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $limit;

// 计算总医生数量
$count_sql = "SELECT COUNT(*) as total FROM doctors WHERE (name LIKE ? OR specialty LIKE ? OR location LIKE ?)";
$count_stmt = $conn->prepare($count_sql);
$searchTerm = "%$search%";
$count_stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
$count_stmt->execute();
$total_doctors = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_doctors / $limit);

// 查询当前页的医生，如果有搜索则筛选，同時獲取真實評分和評論數
$sql = "SELECT d.*, 
        COALESCE(AVG(dr.rating), 0) as avg_rating,
        COUNT(dr.id) as review_count
        FROM doctors d 
        LEFT JOIN doctor_reviews dr ON d.id = dr.doctor_id AND dr.is_approved = 1
        WHERE (d.name LIKE ? OR d.specialty LIKE ? OR d.location LIKE ?)
        GROUP BY d.id
        ORDER BY d.name
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssii", $searchTerm, $searchTerm, $searchTerm, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

// 获取统计数据
// 计算医生平均经验年数
$avgExperienceQuery = "SELECT AVG(experience) as avg_experience FROM doctors";
$avgExperienceResult = $conn->query($avgExperienceQuery);
$avgExperience = $avgExperienceResult->fetch_assoc()['avg_experience'];
$avgExperience = round($avgExperience);

// 计算总患者数量（所有注册的患者用户）
$totalPatientsQuery = "SELECT COUNT(*) as total_patients FROM users WHERE role = 'patient'";
$totalPatientsResult = $conn->query($totalPatientsQuery);
$totalPatients = $totalPatientsResult->fetch_assoc()['total_patients'];

// 紧急服务 - 24小时内的预约数量
$emergencyQuery = "SELECT COUNT(*) as emergency_appointments FROM appointments WHERE DATE(created_at) = CURDATE()";
$emergencyResult = $conn->query($emergencyQuery);
$emergencyAppointments = $emergencyResult->fetch_assoc()['emergency_appointments'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Doctors - Green Life Dental Clinic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #059669;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-gray: #f8fafc;
            --border-color: #e5e7eb;
            --text-muted: #6b7280;
            --gradient-primary: linear-gradient(135deg, #2563eb, #1d4ed8);
            --gradient-secondary: linear-gradient(135deg, #059669, #047857);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f0fdf4 100%);
            min-height: 100vh;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .page-header {
            background: var(--gradient-primary);
            color: white;
            padding: 4rem 0;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="medical" patternUnits="userSpaceOnUse" width="20" height="20"><circle cx="10" cy="10" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23medical)"/></svg>');
        }

        .page-header .container {
            position: relative;
            z-index: 1;
        }

        .page-header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .page-header p {
            font-size: 1.25rem;
            opacity: 0.9;
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }

        .search-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin: -2rem auto 3rem;
            max-width: 600px;
            box-shadow: var(--shadow-xl);
            position: relative;
            z-index: 10;
        }

        .search-form {
            display: flex;
            gap: 1rem;
        }

        .search-input {
            flex: 1;
            height: 3.5rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0 1.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15);
        }

        .search-btn {
            height: 3.5rem;
            padding: 0 2rem;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }

        .doctors-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .doctor-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .doctor-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-secondary);
        }

        .doctor-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }

        .doctor-content {
            display: flex;
            gap: 1.5rem;
            align-items: flex-start;
        }

        .doctor-avatar {
            flex-shrink: 0;
            position: relative;
        }

        .doctor-avatar img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--light-gray);
            transition: all 0.3s ease;
        }

        .doctor-card:hover .doctor-avatar img {
            border-color: var(--primary-color);
        }

        .doctor-status {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 20px;
            height: 20px;
            background: var(--success-color);
            border: 3px solid white;
            border-radius: 50%;
        }

        .doctor-info {
            flex: 1;
        }

        .doctor-name {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .doctor-specialty {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 1rem;
            padding: 0.25rem 0.75rem;
            background: rgba(37, 99, 235, 0.1);
            border-radius: 20px;
            display: inline-block;
        }

        .doctor-details {
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
        }

        .detail-item i {
            color: var(--secondary-color);
            width: 16px;
            font-size: 0.9rem;
        }

        .rating-stars {
            margin-right: 0.5rem;
        }

        .rating-excellent { color: #10b981; } /* 4.5-5.0 */
        .rating-good { color: #3b82f6; }      /* 3.5-4.4 */
        .rating-average { color: #f59e0b; }   /* 2.5-3.4 */
        .rating-poor { color: #ef4444; }      /* 1.0-2.4 */

        .rating-text {
            font-weight: 500;
        }

        .no-rating {
            color: var(--text-muted);
            font-style: italic;
        }

        .doctor-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-primary-action {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-primary-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(37, 99, 235, 0.3);
            color: white;
            text-decoration: none;
        }

        .btn-secondary-action {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary-action:hover {
            background: var(--primary-color);
            color: white;
            text-decoration: none;
        }

        .no-doctors {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
        }

        .no-doctors i {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .no-doctors h3 {
            font-family: 'Poppins', sans-serif;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .no-doctors p {
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
            }

            .doctors-grid {
                grid-template-columns: 1fr;
            }

            .search-form {
                flex-direction: column;
            }

            .doctor-content {
                flex-direction: column;
                text-align: center;
            }

            .doctor-actions {
                justify-content: center;
            }

            .doctor-details {
                text-align: left;
            }
        }

        @media (max-width: 480px) {
            .page-header {
                padding: 2rem 0;
            }

            .search-section {
                margin: -1rem 1rem 2rem;
                padding: 1.5rem;
            }

            .doctor-card {
                padding: 1.5rem;
            }

            .doctors-container {
                padding: 0 0.5rem;
            }
        }

        .stats-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: var(--shadow-lg);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-muted);
            font-weight: 500;
        }

        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .floating-icon {
            position: absolute;
            color: rgba(37, 99, 235, 0.1);
            animation: float 6s ease-in-out infinite;
        }

        .floating-icon:nth-child(1) { top: 20%; left: 10%; animation-delay: 0s; }
        .floating-icon:nth-child(2) { top: 60%; left: 80%; animation-delay: 2s; }
        .floating-icon:nth-child(3) { top: 30%; left: 70%; animation-delay: 4s; }
        .floating-icon:nth-child(4) { top: 80%; left: 20%; animation-delay: 1s; }
        .floating-icon:nth-child(5) { top: 10%; left: 50%; animation-delay: 3s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        /* Pagination Styles */
        .pagination-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-top: 3rem;
            box-shadow: var(--shadow-lg);
            position: relative;
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        
        .page-btn {
            background: white;
            border: 2px solid var(--border-color);
            color: var(--primary-color);
            padding: 0.75rem 1rem;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            min-width: 45px;
            justify-content: center;
        }
        
        .page-btn:hover {
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);
        }
        
        .page-btn.active {
            background: var(--gradient-primary);
            color: #1a202c;
            border-color: var(--primary-color);
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
            text-shadow: none;
        }
        
        .page-btn.disabled {
            background: var(--light-gray);
            color: var(--text-muted);
            cursor: not-allowed;
            pointer-events: none;
            opacity: 0.6;
        }
        
        .pagination-info {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.95rem;
            font-weight: 500;
            padding: 1rem;
            background: var(--light-gray);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        
        .pagination-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .pagination-summary .current-page-info {
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .pagination-summary .total-pages-info {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .pagination-nav {
                gap: 0.25rem;
            }
            
            .page-btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
                min-width: 38px;
            }
            
            .pagination-container {
                padding: 1.5rem;
                margin-top: 2rem;
            }
            
            .pagination-summary {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Floating Medical Elements -->
    <div class="floating-elements">
        <i class="fas fa-tooth floating-icon" style="font-size: 2rem;"></i>
        <i class="fas fa-stethoscope floating-icon" style="font-size: 1.5rem;"></i>
        <i class="fas fa-user-md floating-icon" style="font-size: 2rem;"></i>
        <i class="fas fa-heartbeat floating-icon" style="font-size: 1.8rem;"></i>
        <i class="fas fa-syringe floating-icon" style="font-size: 1.5rem;"></i>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-user-md me-3"></i>Our Expert Doctors</h1>
            <p>Meet our team of experienced dental professionals committed to providing exceptional care</p>
        </div>
    </div>

    <!-- Search Section -->
    <div class="container">
        <div class="search-section">
            <form method="GET" class="search-form">
                <input type="text" name="search" class="search-input" 
                       placeholder="🔍 Search by name, specialty, or location..." 
                       value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="page" value="1">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search me-2"></i>Search
                </button>
            </form>
        </div>
    </div>

    <div class="doctors-container">
        <?php if ($result->num_rows > 0): ?>
            <!-- Stats Section -->
            <div class="stats-section">
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?= $result->num_rows ?></div>
                        <div class="stat-label">Doctors on This Page</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= $total_doctors ?></div>
                        <div class="stat-label">Total Available Doctors</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= $avgExperience ?>+</div>
                        <div class="stat-label">Years Experience</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= $emergencyAppointments ?></div>
                        <div class="stat-label">Today's Appointments</div>
                    </div>
                </div>
            </div>

            <!-- Doctors Grid -->
            <div class="doctors-grid">
                <?php while ($doctor = $result->fetch_assoc()): ?>
                    <div class="doctor-card">
                        <div class="doctor-content">
                            <div class="doctor-avatar">
                                <img src="<?= htmlspecialchars($doctor['image']) ?>" 
                                     alt="Dr. <?= htmlspecialchars($doctor['name']) ?>"
                                     onerror="this.src='https://via.placeholder.com/120x120/2563eb/ffffff?text=Dr'">
                                <div class="doctor-status" title="Available"></div>
                            </div>
                            
                            <div class="doctor-info">
                                <h3 class="doctor-name">Dr. <?= htmlspecialchars($doctor['name']) ?></h3>
                                <div class="doctor-specialty">
                                    <i class="fas fa-tooth me-1"></i>
                                    <?= htmlspecialchars($doctor['specialty']) ?>
                                </div>
                                
                                <div class="doctor-details">
                                    <div class="detail-item">
                                        <i class="fas fa-graduation-cap"></i>
                                        <span><?= htmlspecialchars($doctor['experience']) ?> years of experience</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?= htmlspecialchars($doctor['location']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-star"></i>
                                        <span>
                                            <?php 
                                            $rating = round($doctor['avg_rating'], 1);
                                            $reviewCount = $doctor['review_count'];
                                            
                                            if ($rating > 0): 
                                                // 根據評分確定顏色類別
                                                $ratingClass = '';
                                                if ($rating >= 4.5) $ratingClass = 'rating-excellent';
                                                elseif ($rating >= 3.5) $ratingClass = 'rating-good';
                                                elseif ($rating >= 2.5) $ratingClass = 'rating-average';
                                                else $ratingClass = 'rating-poor';
                                                
                                                // 顯示星星評分
                                                $fullStars = floor($rating);
                                                $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                                
                                                echo '<span class="rating-stars ' . $ratingClass . '">';
                                                // 滿星
                                                for ($i = 0; $i < $fullStars; $i++) {
                                                    echo '<i class="fas fa-star"></i>';
                                                }
                                                // 半星
                                                if ($hasHalfStar) {
                                                    echo '<i class="fas fa-star-half-alt"></i>';
                                                    $fullStars++;
                                                }
                                                // 空星
                                                for ($i = $fullStars; $i < 5; $i++) {
                                                    echo '<i class="far fa-star"></i>';
                                                }
                                                echo '</span>';
                                                
                                                echo '<span class="rating-text ' . $ratingClass . '">' . $rating . '/5.0';
                                                if ($reviewCount > 0): 
                                                    echo " • " . $reviewCount . " Review" . ($reviewCount != 1 ? "s" : "");
                                                endif;
                                                echo '</span>';
                                            else: 
                                                echo '<span class="no-rating">No reviews yet</span>';
                                            endif; 
                                            ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="doctor-actions">
                                    <a href="doctor/doctor_details.php?id=<?= $doctor['id'] ?>" class="btn-action btn-secondary-action">
                                        <i class="fas fa-info-circle"></i>View Details
                                    </a>
                                    
                                    <?php if ($doctor['review_count'] > 0): ?>
                                        <a href="doctor_reviews.php?doctor_id=<?= $doctor['id'] ?>" class="btn-action btn-secondary-action">
                                            <i class="fas fa-star"></i>View Reviews (<?= $doctor['review_count'] ?>)
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <a href="patient/book_appointment.php?doctor=<?= $doctor['id'] ?>" class="btn-action btn-primary-action">
                                            <i class="fas fa-calendar-plus"></i>Book Appointment
                                        </a>
                                    <?php else: ?>
                                        <a href="patient/login.php" class="btn-action btn-primary-action">
                                            <i class="fas fa-sign-in-alt"></i>Login to Book
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Pagination Navigation -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-summary">
                    <div class="current-page-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_doctors) ?> of <?= number_format($total_doctors) ?> doctors
                    </div>
                    <div class="total-pages-info">
                        Page <?= $current_page ?> of <?= $total_pages ?>
                    </div>
                </div>
                
                <div class="pagination-nav">
                    <!-- Previous Page -->
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?= $current_page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-btn">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="page-btn disabled">
                            <i class="fas fa-chevron-left"></i> Previous
                        </span>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    // Show first page if not in range
                    if ($start_page > 1) {
                        echo '<a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . '" class="page-btn">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="page-btn disabled">...</span>';
                        }
                    }
                    
                    // Show page numbers in range
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $current_page) {
                            echo '<span class="page-btn active">' . $i . '</span>';
                        } else {
                            echo '<a href="?page=' . $i . (!empty($search) ? '&search=' . urlencode($search) : '') . '" class="page-btn">' . $i . '</a>';
                        }
                    }
                    
                    // Show last page if not in range
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="page-btn disabled">...</span>';
                        }
                        echo '<a href="?page=' . $total_pages . (!empty($search) ? '&search=' . urlencode($search) : '') . '" class="page-btn">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <!-- Next Page -->
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?= $current_page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-btn disabled">
                            Next <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="pagination-info">
                    <i class="fas fa-users me-2"></i>
                    Browse through our network of <?= number_format($total_doctors) ?> qualified dental professionals
                    <?php if (!empty($search)): ?>
                        <br><i class="fas fa-search me-1"></i>
                        Filtered by: "<strong><?= htmlspecialchars($search) ?></strong>"
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-doctors">
                <i class="fas fa-user-md"></i>
                <h3>No doctors found</h3>
                <p>Sorry, we couldn't find any doctors matching your search criteria. Please try a different search term.</p>
                <a href="?" class="btn-action btn-primary-action" style="margin-top: 1rem;">
                    <i class="fas fa-refresh"></i>Show All Doctors
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enhanced search with debouncing
        let searchTimeout;
        const searchInput = document.querySelector('.search-input');
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // You can add real-time search functionality here
                console.log('Searching for:', this.value);
            }, 500);
        });

        // Add smooth animations on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe doctor cards for animation
        document.addEventListener('DOMContentLoaded', () => {
            const doctorCards = document.querySelectorAll('.doctor-card');
            doctorCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(50px)';
                card.style.transition = `all 0.6s ease ${index * 0.1}s`;
                observer.observe(card);
            });

            // Add hover sound effect (optional)
            doctorCards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Search form enhancement
            const searchForm = document.querySelector('.search-form');
            searchForm.addEventListener('submit', (e) => {
                const btn = searchForm.querySelector('.search-btn');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Searching...';
                btn.disabled = true;
                
                // Re-enable after form submits
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-search me-2"></i>Search';
                    btn.disabled = false;
                }, 1000);
            });

            // Add floating animation to stats
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const finalNumber = parseInt(stat.textContent);
                if (!isNaN(finalNumber)) {
                    let currentNumber = 0;
                    const increment = finalNumber / 50;
                    const timer = setInterval(() => {
                        currentNumber += increment;
                        if (currentNumber >= finalNumber) {
                            stat.textContent = finalNumber + (stat.textContent.includes('+') ? '+' : '');
                            clearInterval(timer);
                        } else {
                            stat.textContent = Math.floor(currentNumber);
                        }
                    }, 30);
                }
            });
        });
        
        // Pagination enhancements
        document.addEventListener('DOMContentLoaded', () => {
            // Add smooth transitions for pagination
            const pageLinks = document.querySelectorAll('.page-btn:not(.disabled)');
            pageLinks.forEach(link => {
                if (link.tagName === 'A') {
                    link.addEventListener('click', function(e) {
                        // Show loading state
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                        this.style.pointerEvents = 'none';
                        
                        // Smooth scroll to top
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    });
                }
            });
            
            // Highlight current page with animation
            const activePage = document.querySelector('.page-btn.active');
            if (activePage) {
                activePage.style.animation = 'pulse 1s ease-in-out';
            }
            
            // Add keyboard navigation for pagination
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                    const currentPageBtn = document.querySelector('.page-btn.active');
                    if (currentPageBtn) {
                        let targetBtn;
                        if (e.key === 'ArrowLeft') {
                            targetBtn = currentPageBtn.previousElementSibling;
                            while (targetBtn && (!targetBtn.classList.contains('page-btn') || targetBtn.classList.contains('disabled'))) {
                                targetBtn = targetBtn.previousElementSibling;
                            }
                        } else {
                            targetBtn = currentPageBtn.nextElementSibling;
                            while (targetBtn && (!targetBtn.classList.contains('page-btn') || targetBtn.classList.contains('disabled'))) {
                                targetBtn = targetBtn.nextElementSibling;
                            }
                        }
                        if (targetBtn && targetBtn.tagName === 'A') {
                            e.preventDefault();
                            targetBtn.click();
                        }
                    }
                }
            });
        });
        
        // Add pulse animation for active page
        const pulseStyle = document.createElement('style');
        pulseStyle.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(pulseStyle);
    </script>
</body>
</html>
