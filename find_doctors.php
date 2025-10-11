<?php
session_start();
include 'includes/db.php';

// Helper function to check user role safely
function isPatient() {
    return isset($_SESSION['user_id']) && 
           ((isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'patient') || 
            (isset($_SESSION['role']) && $_SESSION['role'] === 'patient'));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find a Doctor - Health Care Clinic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #2c4964;
            --secondary-color: #1977cc;
            --accent-color: #3fbbc0;
        }

        body {
            font-family: "Open Sans", sans-serif;
            color: #444444;
            margin: 0;
            padding: 0;
            padding-top: 80px;
        }

        /* Navigation Bar */
        .main-navbar {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 15px 0;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 24px;
            padding: 0;
        }

        .nav-link {
            color: var(--primary-color) !important;
            font-weight: 500;
            padding: 10px 15px !important;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            color: var(--secondary-color) !important;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--secondary-color);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after {
            width: 70%;
        }

        .appointment-btn {
            background: var(--secondary-color);
            color: white !important;
            border-radius: 50px;
            padding: 10px 25px !important;
            white-space: nowrap;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        .appointment-btn:hover {
            background: var(--primary-color);
            transform: scale(1.05);
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(rgba(44, 73, 100, 0.8), rgba(44, 73, 100, 0.8)),
                        url('https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            padding: 80px 0;
            text-align: center;
            color: white;
            margin-bottom: 50px;
        }

        .page-header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        /* Doctor Cards Styling */
        .doctor-card {
            text-align: center;
            padding: 30px 20px;
            transition: all 0.3s ease;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            background-color: white;
        }

        .doctor-card:hover {
            background: rgba(25, 119, 204, 0.1);
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        }

        .doctor-image img {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border: 5px solid rgba(25, 119, 204, 0.2);
            padding: 5px;
            transition: all 0.3s ease;
        }

        .doctor-card:hover .doctor-image img {
            border-color: var(--secondary-color);
        }

        .doctor-card h4 {
            color: var(--primary-color);
            font-size: 22px;
            margin: 20px 0 10px;
        }

        .doctor-card .specialty {
            color: var(--secondary-color);
            font-weight: 600;
            margin-bottom: 15px;
        }

        .doctor-card .description {
            color: #666;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .doctor-card .btn-book {
            background-color: var(--secondary-color);
            color: white;
            padding: 8px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .doctor-card .btn-book:hover {
            background-color: var(--primary-color);
            transform: scale(1.05);
        }

        /* Doctor Search Section */
        .doctor-search-section {
            background-color: #f8f9fa;
            padding: 40px 0;
            margin-bottom: 50px;
            border-radius: 10px;
        }

        .search-form {
            max-width: 800px;
            margin: 0 auto;
        }

        .search-form .form-control {
            border-radius: 50px;
            padding: 12px 25px;
            height: auto;
            box-shadow: none;
            border: 1px solid #ddd;
        }

        .search-form .btn-search {
            background-color: var(--secondary-color);
            color: white;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            border: none;
        }

        .search-form .btn-search:hover {
            background-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>Find a Doctor</h1>
            <p>Connect with our experienced healthcare professionals</p>
        </div>
    </div>

    <!-- Doctor Search Section -->
    <section class="doctor-search-section">
        <div class="container">
            <h2 class="text-center mb-4" style="color: var(--primary-color);">Search for a Doctor</h2>
            <div class="search-form">
                <form action="" method="GET">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <select class="form-select" name="specialty">
                                <option value="">Any Specialty</option>
                                <option value="General Practitioner">General Practitioner</option>
                                <option value="Internal Medicine">Internal Medicine</option>
                                <option value="Pediatrician">Pediatrician</option>
                                <option value="Professional Specialist">Professional Specialist</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="name" placeholder="Doctor Name">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-search w-100">
                                <i class="bi bi-search me-2"></i> Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Our Doctors Section -->
    <section class="container mb-5">
        <h2 class="text-center mb-4" style="color: var(--primary-color);">Our Doctors</h2>
        <div class="row">
            <?php
            // Build search query
            $search_conditions = [];
            $params = [];
            $types = "";
            
            if (!empty($_GET['specialty'])) {
                $search_conditions[] = "d.specialty = ?";
                $params[] = $_GET['specialty'];
                $types .= "s";
            }
            
            if (!empty($_GET['name'])) {
                $search_conditions[] = "d.name LIKE ?";
                $search_term = "%" . $_GET['name'] . "%";
                $params[] = $search_term;
                $types .= "s";
            }
            
            $where_clause = !empty($search_conditions) ? "WHERE " . implode(" AND ", $search_conditions) : "";
            
            // Get doctors with their ratings
            $doctors_query = "SELECT 
                d.*,
                COALESCE(AVG(dr.rating), 0) as avg_rating,
                COUNT(dr.id) as review_count
                FROM doctors d 
                LEFT JOIN doctor_reviews dr ON d.id = dr.doctor_id AND dr.is_approved = 1
                $where_clause
                GROUP BY d.id
                ORDER BY avg_rating DESC, review_count DESC";
            
            if (!empty($params)) {
                $doctors_stmt = $conn->prepare($doctors_query);
                if (!empty($types)) {
                    $doctors_stmt->bind_param($types, ...$params);
                }
                $doctors_stmt->execute();
                $doctors_result = $doctors_stmt->get_result();
            } else {
                $doctors_result = $conn->query($doctors_query);
            }
            
            function renderStars($rating) {
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $rating) {
                        $stars .= '<i class="bi bi-star-fill text-warning"></i>';
                    } elseif ($i - 0.5 <= $rating) {
                        $stars .= '<i class="bi bi-star-half text-warning"></i>';
                    } else {
                        $stars .= '<i class="bi bi-star text-muted"></i>';
                    }
                }
                return $stars;
            }
            
            if ($doctors_result->num_rows > 0):
                while ($doctor = $doctors_result->fetch_assoc()):
                    $avg_rating = round($doctor['avg_rating'], 1);
                    $review_count = $doctor['review_count'];
                    // 修复图片路径
                    $profile_image = !empty($doctor['image']) ? $doctor['image'] : 'https://img.freepik.com/free-photo/woman-doctor-wearing-lab-coat-with-stethoscope-isolated_1303-29791.jpg';
                    
                    // 如果是数据库中的相对路径，检查文件是否存在
                    if (!empty($doctor['image']) && !filter_var($doctor['image'], FILTER_VALIDATE_URL)) {
                        if (!file_exists($doctor['image'])) {
                            $profile_image = 'https://img.freepik.com/free-photo/woman-doctor-wearing-lab-coat-with-stethoscope-isolated_1303-29791.jpg';
                        }
                    }
            ?>
            <div class="col-lg-3 col-md-6">
                <div class="doctor-card">
                    <div class="doctor-image">
                        <img src="<?php echo htmlspecialchars($profile_image); ?>" 
                             alt="Dr. <?php echo htmlspecialchars($doctor['name']); ?>"
                             class="img-fluid rounded-circle mb-3"
                             onerror="this.src='https://img.freepik.com/free-photo/woman-doctor-wearing-lab-coat-with-stethoscope-isolated_1303-29791.jpg'">
                    </div>
                    <h4>Dr. <?php echo htmlspecialchars($doctor['name']); ?></h4>
                    <p class="specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                    
                    <!-- Rating Display -->
                    <div class="rating-display mb-3">
                        <div class="stars">
                            <?php echo renderStars($avg_rating); ?>
                        </div>
                        <div class="rating-text">
                            <?php if ($review_count > 0): ?>
                                <span class="text-muted small">
                                    <?php echo $avg_rating; ?>/5 (<?php echo $review_count; ?> review<?php echo $review_count != 1 ? 's' : ''; ?>)
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">No reviews yet</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($doctor['bio'])): ?>
                    <p class="description"><?php echo htmlspecialchars(substr($doctor['bio'], 0, 100) . (strlen($doctor['bio']) > 100 ? '...' : '')); ?></p>
                    <?php else: ?>
                    <p class="description">Experienced healthcare professional dedicated to providing quality care.</p>
                    <?php endif; ?>
                    
                    <div class="doctor-actions">
                        <?php if (isPatient()): ?>
                            <a href="patient/book_appointment.php?doctor_id=<?php echo $doctor['id']; ?>" class="btn-book me-2">Book Appointment</a>
                        <?php else: ?>
                            <a href="patient/login.php" class="btn-book me-2">Book Appointment</a>
                        <?php endif; ?>
                        <a href="doctor_reviews.php?doctor_id=<?php echo $doctor['id']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-star me-1"></i>Reviews
                        </a>
                    </div>
                </div>
            </div>
            <?php 
                endwhile;
            else:
            ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-search display-1 text-muted"></i>
                    <h4 class="mt-3 text-muted">No doctors found</h4>
                    <p class="text-muted">Try adjusting your search criteria</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <!-- <?php include 'includes/footer.php'; ?> -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 