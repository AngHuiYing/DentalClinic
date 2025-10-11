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

// 获取医生的评价统计
$review_stats = [];
if ($doctor) {
    $stats_sql = "SELECT 
        COUNT(*) as total_reviews,
        AVG(rating) as avg_rating
        FROM doctor_reviews 
        WHERE doctor_id = ? AND is_approved = 1";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("i", $doctorId);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    $review_stats = $stats_result->fetch_assoc();
}

$avg_rating = isset($review_stats['avg_rating']) && $review_stats['avg_rating'] ? round($review_stats['avg_rating'], 1) : 0;
$total_reviews = isset($review_stats['total_reviews']) ? $review_stats['total_reviews'] : 0;

// 估算患者数量 (基于预约数据)
$patients_count_sql = "SELECT COUNT(DISTINCT patient_id) as patient_count FROM appointments WHERE doctor_id = ?";
$patients_stmt = $conn->prepare($patients_count_sql);
$patients_stmt->bind_param("i", $doctorId);
$patients_stmt->execute();
$patients_result = $patients_stmt->get_result();
$patients_data = $patients_result->fetch_assoc();
$patients_treated = $patients_data['patient_count'] ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Details - Dental Clinic</title>
    
    <!-- Bootstrap 5.3.0 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6.4.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --clinic-primary: #2d5aa0;
            --clinic-secondary: #4a9396;
            --clinic-accent: #84c69b;
            --clinic-light: #f1f8e8;
            --clinic-warm: #f9f7ef;
            --clinic-text: #2c3e50;
            --clinic-muted: #7f8c8d;
            --clinic-success: #27ae60;
            --clinic-warning: #f39c12;
            --clinic-danger: #e74c3c;
            --clinic-white: #ffffff;
            --clinic-shadow: 0 2px 10px rgba(45, 90, 160, 0.1);
            --clinic-shadow-hover: 0 8px 25px rgba(45, 90, 160, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--clinic-light);
            color: var(--clinic-text);
            line-height: 1.6;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--clinic-primary) 0%, var(--clinic-secondary) 100%);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2rem;
            position: relative;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
                radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.1) 2px, transparent 2px);
            background-size: 40px 40px;
        }

        .page-header .container {
            position: relative;
            z-index: 1;
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-align: center;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .page-header p {
            font-size: 1rem;
            opacity: 0.95;
            text-align: center;
        }

        .doctor-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .doctor-card {
            background: var(--clinic-white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--clinic-shadow);
            border: 1px solid rgba(45, 90, 160, 0.08);
            margin-bottom: 2rem;
        }

        .doctor-profile-section {
            background: linear-gradient(135deg, var(--clinic-secondary) 0%, var(--clinic-accent) 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .doctor-profile-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 2px, transparent 2px);
            background-size: 30px 30px;
        }

        .doctor-profile-content {
            position: relative;
            z-index: 1;
        }

        .doctor-image-container {
            position: relative;
            display: inline-block;
            margin-bottom: 1.5rem;
        }

        .doctor-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .doctor-image:hover {
            transform: scale(1.05);
        }

        .doctor-status-badge {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 25px;
            height: 25px;
            background: var(--clinic-success);
            border-radius: 50%;
            border: 3px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .doctor-status-badge i {
            font-size: 0.7rem;
            color: white;
        }

        .doctor-name {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .doctor-specialty {
            font-size: 1.1rem;
            opacity: 0.95;
            margin-bottom: 1rem;
        }

        .doctor-info-section {
            padding: 2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: var(--clinic-warm);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid rgba(45, 90, 160, 0.08);
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--clinic-shadow-hover);
            background: white;
        }

        .info-card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            color: var(--clinic-primary);
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .info-title {
            font-weight: 600;
            font-size: 1rem;
        }

        .info-content {
            color: var(--clinic-text);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .bio-section {
            background: linear-gradient(135deg, rgba(45, 90, 160, 0.05) 0%, rgba(74, 147, 150, 0.05) 100%);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(45, 90, 160, 0.1);
        }

        .bio-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            color: var(--clinic-primary);
        }

        .bio-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
        }

        .bio-content {
            font-size: 1rem;
            color: var(--clinic-text);
            line-height: 1.7;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .btn-primary-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--clinic-primary) 0%, var(--clinic-secondary) 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(45, 90, 160, 0.3);
            color: white;
            text-decoration: none;
        }

        .btn-primary-action::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-primary-action:hover::before {
            left: 100%;
        }

        .btn-secondary-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            background: var(--clinic-warm);
            color: var(--clinic-primary);
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            border: 2px solid var(--clinic-primary);
            transition: all 0.3s ease;
        }

        .btn-secondary-action:hover {
            background: var(--clinic-primary);
            color: white;
            transform: translateY(-2px);
            text-decoration: none;
        }

        .not-found-section {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--clinic-white);
            border-radius: 15px;
            box-shadow: var(--clinic-shadow);
        }

        .not-found-section i {
            font-size: 4rem;
            color: var(--clinic-muted);
            margin-bottom: 1rem;
            opacity: 0.7;
        }

        .not-found-section h3 {
            color: var(--clinic-text);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .not-found-section p {
            color: var(--clinic-muted);
            margin-bottom: 2rem;
        }

        .stats-row {
            display: flex;
            justify-content: space-around;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.8rem;
            }

            .doctor-container {
                padding: 0 0.5rem;
            }

            .doctor-info-section {
                padding: 1.5rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-primary-action,
            .btn-secondary-action {
                width: 100%;
                justify-content: center;
            }

            .stats-row {
                flex-direction: column;
                gap: 1rem;
            }
        }

        /* Animation */
        .doctor-card {
            animation: fadeInUp 0.6s ease forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <?php 
    if (isset($_SESSION['user_id'])) {
        include "../includes/navbar.php"; 
    } else { 
        // For non-logged in users, we'll show a simple header or include the navbar
        include "../includes/navbar.php";
    } 
    ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-user-md"></i> Doctor Profile</h1>
            <p>Meet our experienced medical professional</p>
        </div>
    </div>

    <div class="doctor-container">
        <?php if ($doctor): ?>
            <div class="doctor-card">
                <!-- Doctor Profile Section -->
                <div class="doctor-profile-section">
                    <div class="doctor-profile-content">
                        <div class="doctor-image-container">
                            <?php 
                            $image_path = '../' . $doctor['image']; // 添加相对路径前缀
                            // 检查图片文件是否存在
                            if (!file_exists($image_path) || empty($doctor['image'])) {
                                $image_path = 'https://img.freepik.com/free-photo/woman-doctor-wearing-lab-coat-with-stethoscope-isolated_1303-29791.jpg';
                            }
                            ?>
                            <img src="<?= htmlspecialchars($image_path) ?>" 
                                 alt="<?= htmlspecialchars($doctor['name']) ?>" 
                                 class="doctor-image">
                            <div class="doctor-status-badge">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                        
                        <div class="doctor-name">
                            Dr. <?= htmlspecialchars($doctor['name']) ?>
                        </div>
                        
                        <div class="doctor-specialty">
                            <i class="fas fa-tooth"></i>
                            <?= htmlspecialchars($doctor['specialty']) ?>
                        </div>

                        <div class="stats-row">
                            <div class="stat-item">
                                <div class="stat-number"><?= htmlspecialchars($doctor['experience']) ?></div>
                                <div class="stat-label">Years Experience</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">
                                    <i class="fas fa-star"></i> <?php echo $avg_rating > 0 ? $avg_rating : 'New'; ?>
                                </div>
                                <div class="stat-label">Patient Rating</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $patients_treated; ?>+</div>
                                <div class="stat-label">Patients Treated</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Doctor Information Section -->
                <div class="doctor-info-section">
                    <div class="info-grid">
                        <div class="info-card">
                            <div class="info-card-header">
                                <div class="info-icon">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div class="info-title">Experience</div>
                            </div>
                            <div class="info-content">
                                <?= htmlspecialchars($doctor['experience']) ?> years of professional dental practice with expertise in modern treatment methods.
                            </div>
                        </div>

                        <div class="info-card">
                            <div class="info-card-header">
                                <div class="info-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="info-title">Location</div>
                            </div>
                            <div class="info-content">
                                <?= htmlspecialchars($doctor['location']) ?>
                            </div>
                        </div>

                        <div class="info-card">
                            <div class="info-card-header">
                                <div class="info-icon">
                                    <i class="fas fa-stethoscope"></i>
                                </div>
                                <div class="info-title">Specialty</div>
                            </div>
                            <div class="info-content">
                                Specialized in <?= htmlspecialchars($doctor['specialty']) ?> with advanced training and certifications.
                            </div>
                        </div>

                        <div class="info-card">
                            <div class="info-card-header">
                                <div class="info-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="info-title">Availability</div>
                            </div>
                            <div class="info-content">
                                Available for appointments Monday to Saturday, 9:00 AM - 6:00 PM.
                            </div>
                        </div>
                    </div>

                    <!-- Bio Section -->
                    <?php if (!empty($doctor['bio'])): ?>
                        <div class="bio-section">
                            <div class="bio-header">
                                <div class="info-icon">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <h3>About Dr. <?= htmlspecialchars($doctor['name']) ?></h3>
                            </div>
                            <div class="bio-content">
                                <?= htmlspecialchars($doctor['bio']) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="../patient/book_appointment.php?doctor=<?= $doctor['id'] ?>" class="btn-primary-action">
                                <i class="fas fa-calendar-plus"></i>
                                Book Appointment
                            </a>
                            <a href="../doctor_reviews.php?doctor_id=<?= $doctor['id'] ?>" class="btn-secondary-action">
                                <i class="fas fa-star"></i>
                                View Reviews (<?php echo $total_reviews; ?>)
                            </a>
                        <?php else: ?>
                            <a href="../book_appointment.php" class="btn-primary-action">
                                <i class="fas fa-calendar-plus"></i>
                                Book Appointment
                            </a>
                            <a href="../doctor_reviews.php?doctor_id=<?= $doctor['id'] ?>" class="btn-secondary-action">
                                <i class="fas fa-star"></i>
                                View Reviews (<?php echo $total_reviews; ?>)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="not-found-section">
                <i class="fas fa-user-times"></i>
                <h3>Doctor Not Found</h3>
                <p>The doctor profile you're looking for could not be found. Please check the URL or return to our doctors directory.</p>
                <a href="../all_doctors.php" class="btn-primary-action">
                    <i class="fas fa-users"></i>
                    View All Doctors
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth scroll for any anchor links
            const links = document.querySelectorAll('a[href^="#"]');
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Add loading animation for buttons
            const actionButtons = document.querySelectorAll('.btn-primary-action, .btn-secondary-action');
            actionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    if (this.href && this.href.includes('book_appointment')) {
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                    }
                });
            });
        });
    </script>
</body>
</html>
