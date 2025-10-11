<?php
session_start();
require_once 'includes/db.php';

// Helper function to check user role safely
function isPatient() {
    return isset($_SESSION['user_id']) && 
           ((isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'patient') || 
            (isset($_SESSION['role']) && $_SESSION['role'] === 'patient'));
}

// Helper function to check if patient has had appointments with this doctor
function hasAppointmentWithDoctor($conn, $patient_id, $doctor_id) {
    $query = "SELECT COUNT(*) as count FROM appointments 
              WHERE (patient_id = ? OR patient_email = (SELECT email FROM users WHERE id = ?)) 
              AND doctor_id = ? 
              AND status IN ('completed', 'confirmed')
              AND appointment_date <= CURDATE()";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $patient_id, $patient_id, $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
}

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$rating_filter = isset($_GET['rating']) ? intval($_GET['rating']) : 0;

// Pagination setup
$reviews_per_page = 5; // Number of reviews per page
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $reviews_per_page;

// Get doctor information
$doctor_query = "SELECT id, name, specialty, bio, image FROM doctors WHERE id = ?";
$doctor_stmt = $conn->prepare($doctor_query);
$doctor_stmt->bind_param("i", $doctor_id);
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();
$doctor = $doctor_result->fetch_assoc();

if (!$doctor) {
    header("Location: index.php");
    exit();
}

// Get reviews and rating statistics with pagination
$rating_condition = "";
$params = array($doctor_id);
$param_types = "i";

if ($rating_filter > 0 && $rating_filter <= 5) {
    $rating_condition = " AND dr.rating = ?";
    $params[] = $rating_filter;
    $param_types .= "i";
}

$reviews_query = "SELECT 
    dr.*,
    CASE 
        WHEN dr.is_anonymous = 1 THEN 'Anonymous Patient'
        ELSE dr.patient_name
    END as display_name,
    a.name as admin_name
    FROM doctor_reviews dr 
    LEFT JOIN admin a ON dr.admin_id = a.id
    WHERE dr.doctor_id = ? AND dr.is_approved = 1" . $rating_condition . "
    ORDER BY dr.created_at DESC
    LIMIT ? OFFSET ?";

$params[] = $reviews_per_page;
$params[] = $offset;
$param_types .= "ii";

$reviews_stmt = $conn->prepare($reviews_query);
$reviews_stmt->bind_param($param_types, ...$params);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();

// Get rating statistics
$stats_query = "SELECT 
    COUNT(*) as total_reviews,
    AVG(rating) as avg_rating,
    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
    FROM doctor_reviews 
    WHERE doctor_id = ? AND is_approved = 1";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $doctor_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

$avg_rating = $stats['avg_rating'] ? round($stats['avg_rating'], 1) : 0;
$total_reviews = $stats['total_reviews'];

// Get filtered review count for pagination
$filtered_rating_condition = "";
if ($rating_filter > 0 && $rating_filter <= 5) {
    $filtered_rating_condition = " AND rating = ?";
}

$filtered_count_query = "SELECT COUNT(*) as filtered_total FROM doctor_reviews WHERE doctor_id = ? AND is_approved = 1" . $filtered_rating_condition;
$filtered_params = array($doctor_id);
$filtered_param_types = "i";

if ($rating_filter > 0 && $rating_filter <= 5) {
    $filtered_params[] = $rating_filter;
    $filtered_param_types .= "i";
}

$filtered_stmt = $conn->prepare($filtered_count_query);
$filtered_stmt->bind_param($filtered_param_types, ...$filtered_params);
$filtered_stmt->execute();
$filtered_result = $filtered_stmt->get_result();
$filtered_data = $filtered_result->fetch_assoc();
$filtered_total = $filtered_data['filtered_total'];

// Calculate pagination
$total_pages = ceil($filtered_total / $reviews_per_page);
$start_review = ($current_page - 1) * $reviews_per_page + 1;
$end_review = min($current_page * $reviews_per_page, $filtered_total);

// Check if current patient can review this doctor (only if they have appointments)
$can_review = false;
if (isPatient()) {
    $can_review = hasAppointmentWithDoctor($conn, $_SESSION['user_id'], $doctor_id);
}

function renderStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '<i class="fas fa-star text-warning"></i>';
        } elseif ($i - 0.5 <= $rating) {
            $stars .= '<i class="fas fa-star-half-alt text-warning"></i>';
        } else {
            $stars .= '<i class="far fa-star text-muted"></i>';
        }
    }
    return $stars;
}

function getPercentage($count, $total) {
    return $total > 0 ? round(($count / $total) * 100) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dr. <?php echo htmlspecialchars($doctor['name']); ?> - Reviews</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .glass-container {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 2rem 3rem;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            margin-bottom: 2rem;
        }
        
        .review-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }
        
        .review-card:hover {
            transform: translateY(-2px);
        }
        
        .rating-bar {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            height: 8px;
            margin: 0.25rem 0;
        }
        
        .rating-bar-fill {
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            border-radius: 10px;
            height: 100%;
            transition: width 0.8s ease;
        }
        
        .rating-row {
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .rating-row:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .rating-row.active {
            background: rgba(255, 215, 0, 0.2);
            border: 2px solid rgba(255, 215, 0, 0.5);
        }
        
        .filter-badge {
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            color: #333;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 1rem;
            display: inline-block;
        }
        
        .clear-filter {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            text-decoration: none;
            font-size: 0.875rem;
            margin-left: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .clear-filter:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            box-shadow: 0 4px 15px 0 rgba(116, 75, 162, 0.3);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px 0 rgba(116, 75, 162, 0.4);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        
        h1, h2, h3, h4, h5 {
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .text-white {
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .badge {
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 600;
        }
        
        .doctor-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        
        .no-reviews {
            text-align: center;
            padding: 3rem 1rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .review-meta {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .review-title {
            color: white;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .review-text {
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
        }
        
        .admin-reply {
            background: rgba(59, 130, 246, 0.15);
            border-left: 4px solid #3b82f6;
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 0 8px 8px 0;
        }
        
        .admin-reply-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            color: #3b82f6;
            font-weight: 600;
        }
        
        .reply-date {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
            font-weight: normal;
        }
        
        .admin-reply-content {
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
            margin-bottom: 0.5rem;
        }
        
        .admin-signature {
            text-align: right;
            font-style: italic;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.875rem;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 1rem;
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 2rem 0;
            gap: 1rem;
        }
        
        .pagination-info {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .page-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 8px;
            padding: 8px 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .page-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-1px);
        }
        
        .page-btn.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-color: #667eea;
        }
        
        .page-btn.disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        
        .page-numbers {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container py-4">
        <!-- Back Button -->
        <div class="mb-3">
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
        
        <div class="glass-container">
            <div class="text-center mb-4">
                <h1><i class="fas fa-star-half-alt me-2"></i>Doctor Reviews</h1>
                <p class="text-white mb-0">Patient feedback and ratings</p>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['error']); ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success']); ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
        </div>
        
        <!-- Doctor Information -->
        <div class="glass-container">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <?php 
                    $doctor_image = !empty($doctor['image']) ? $doctor['image'] : 'https://img.freepik.com/free-photo/woman-doctor-wearing-lab-coat-with-stethoscope-isolated_1303-29791.jpg';
                    
                    // 如果是数据库中的相对路径，检查文件是否存在
                    if (!empty($doctor['image']) && !filter_var($doctor['image'], FILTER_VALIDATE_URL)) {
                        if (!file_exists($doctor['image'])) {
                            $doctor_image = 'https://img.freepik.com/free-photo/woman-doctor-wearing-lab-coat-with-stethoscope-isolated_1303-29791.jpg';
                        }
                    }
                    ?>
                    <div class="doctor-avatar">
                        <img src="<?php echo htmlspecialchars($doctor_image); ?>" 
                             alt="Dr. <?php echo htmlspecialchars($doctor['name']); ?>"
                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;"
                             onerror="this.parentElement.innerHTML='<i class=\'fas fa-user-md\'></i>'">
                    </div>
                </div>
                <div class="col-md-6">
                    <h1 class="mb-2">Dr. <?php echo htmlspecialchars($doctor['name']); ?></h1>
                    <p class="text-white mb-2">
                        <i class="fas fa-stethoscope me-2"></i>
                        <?php echo htmlspecialchars($doctor['specialty']); ?>
                    </p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="mb-3">
                        <h2 class="mb-1"><?php echo $avg_rating; ?></h2>
                        <div class="mb-2">
                            <?php echo renderStars($avg_rating); ?>
                        </div>
                        <p class="text-white mb-0"><?php echo $total_reviews; ?> review<?php echo $total_reviews != 1 ? 's' : ''; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rating Breakdown -->
        <?php if ($total_reviews > 0): ?>
        <div class="glass-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Rating Breakdown</h3>
                <?php if ($rating_filter > 0): ?>
                <div>
                    <span class="filter-badge">
                        <i class="fas fa-filter me-2"></i>Showing <?php echo $rating_filter; ?> star reviews
                    </span>
                    <a href="?doctor_id=<?php echo $doctor_id; ?>" class="clear-filter">
                        <i class="fas fa-times me-1"></i>Show All
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <div class="row">
                <?php for ($i = 5; $i >= 1; $i--): 
                    $star_count = $stats[$i == 1 ? 'one_star' : ($i == 2 ? 'two_star' : ($i == 3 ? 'three_star' : ($i == 4 ? 'four_star' : 'five_star')))];
                    $is_active = ($rating_filter == $i);
                ?>
                <div class="col-md-6 mb-2">
                    <div class="rating-row <?php echo $is_active ? 'active' : ''; ?>" 
                         onclick="filterByRating(<?php echo $i; ?>)" 
                         title="Click to filter <?php echo $i; ?> star reviews">
                        <div class="d-flex align-items-center">
                            <span class="text-white me-2"><?php echo $i; ?> star</span>
                            <div class="flex-grow-1 mx-3">
                                <div class="rating-bar">
                                    <div class="rating-bar-fill" style="width: <?php echo getPercentage($star_count, $total_reviews); ?>%"></div>
                                </div>
                            </div>
                            <span class="text-white">
                                <?php echo $star_count; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
            <div class="text-center mt-3">
                <small class="text-white opacity-75">
                    <i class="fas fa-info-circle me-1"></i>
                    Click on any rating bar to filter reviews, click again to show all
                </small>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Reviews Section -->
        <div class="glass-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0">
                    <i class="fas fa-comments me-2"></i>Patient Reviews
                    <?php if ($rating_filter > 0): ?>
                        <small class="text-warning ms-2">(<i class="fas fa-star"></i> <?php echo $rating_filter; ?> Stars)</small>
                    <?php endif; ?>
                </h3>
                <?php if ($filtered_total > 0): ?>
                <div class="pagination-info">
                    Showing <?php echo $start_review; ?>-<?php echo $end_review; ?> of <?php echo $filtered_total; ?> 
                    <?php echo $rating_filter > 0 ? $rating_filter . '-star ' : ''; ?>reviews
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($total_reviews > 0): ?>
                <?php while ($review = $reviews_result->fetch_assoc()): ?>
                <div class="review-card">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="review-title"><?php echo htmlspecialchars($review['review_title']); ?></h5>
                            <div class="mb-2">
                                <?php echo renderStars($review['rating']); ?>
                                <span class="text-white ms-2"><?php echo $review['rating']; ?>/5</span>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="review-meta">
                                By <?php echo htmlspecialchars($review['display_name']); ?>
                            </div>
                            <div class="review-meta">
                                <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="review-text">
                        <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                    </div>
                    
                    <!-- Admin Reply Display -->
                    <?php if (!empty($review['admin_reply'])): ?>
                    <div class="admin-reply">
                        <div class="admin-reply-header">
                            <i class="fas fa-reply me-2"></i>
                            <strong>Management Response</strong>
                            <?php if (!empty($review['replied_at'])): ?>
                                <span class="reply-date"><?php echo date('M j, Y', strtotime($review['replied_at'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-reply-content">
                            <?php echo nl2br(htmlspecialchars($review['admin_reply'])); ?>
                        </div>
                        <?php if (!empty($review['admin_name'])): ?>
                        <div class="admin-signature">
                            - <?php echo htmlspecialchars($review['admin_name']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
                
                <!-- Pagination Navigation -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-nav">
                    <!-- Previous Page -->
                    <?php 
                    $rating_param = $rating_filter > 0 ? '&rating=' . $rating_filter : '';
                    ?>
                    <?php if ($current_page > 1): ?>
                        <a href="?doctor_id=<?php echo $doctor_id; ?>&page=<?php echo $current_page - 1; ?><?php echo $rating_param; ?>" class="page-btn">
                            <i class="fas fa-chevron-left me-1"></i>Previous
                        </a>
                    <?php else: ?>
                        <span class="page-btn disabled">
                            <i class="fas fa-chevron-left me-1"></i>Previous
                        </span>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <div class="page-numbers">
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        if ($start_page > 1) {
                            echo '<a href="?doctor_id=' . $doctor_id . '&page=1' . $rating_param . '" class="page-btn">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="page-btn disabled">...</span>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $current_page) {
                                echo '<span class="page-btn active">' . $i . '</span>';
                            } else {
                                echo '<a href="?doctor_id=' . $doctor_id . '&page=' . $i . $rating_param . '" class="page-btn">' . $i . '</a>';
                            }
                        }
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="page-btn disabled">...</span>';
                            }
                            echo '<a href="?doctor_id=' . $doctor_id . '&page=' . $total_pages . $rating_param . '" class="page-btn">' . $total_pages . '</a>';
                        }
                        ?>
                    </div>
                    
                    <!-- Next Page -->
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?doctor_id=<?php echo $doctor_id; ?>&page=<?php echo $current_page + 1; ?><?php echo $rating_param; ?>" class="page-btn">
                            Next<i class="fas fa-chevron-right ms-1"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-btn disabled">
                            Next<i class="fas fa-chevron-right ms-1"></i>
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-reviews">
                    <i class="fas fa-star fa-3x mb-3 opacity-50"></i>
                    <?php if ($rating_filter > 0): ?>
                        <h4>No <?php echo $rating_filter; ?>-star reviews</h4>
                        <p>This doctor has no <?php echo $rating_filter; ?>-star reviews yet.</p>
                        <a href="?doctor_id=<?php echo $doctor_id; ?>" class="btn btn-secondary mt-3">
                            <i class="fas fa-eye me-2"></i>View All Reviews
                        </a>
                    <?php else: ?>
                        <h4>No reviews yet</h4>
                        <p>This doctor currently has no patient reviews.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Doctor Bio -->
        <?php if (!empty($doctor['bio'])): ?>
        <div class="glass-container">
            <h3 class="mb-3"><i class="fas fa-user me-2"></i>About Dr. <?php echo htmlspecialchars($doctor['name']); ?></h3>
            <p class="text-white"><?php echo nl2br(htmlspecialchars($doctor['bio'])); ?></p>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterByRating(rating) {
            const currentRating = new URLSearchParams(window.location.search).get('rating');
            const doctorId = new URLSearchParams(window.location.search).get('doctor_id');
            
            // If clicking the same rating, remove filter (show all)
            if (currentRating == rating) {
                window.location.href = `?doctor_id=${doctorId}`;
            } else {
                // Apply new rating filter
                window.location.href = `?doctor_id=${doctorId}&rating=${rating}`;
            }
        }
        
        // Add smooth transition effects
        document.addEventListener('DOMContentLoaded', function() {
            const ratingRows = document.querySelectorAll('.rating-row');
            
            ratingRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.boxShadow = '0 4px 15px rgba(255, 255, 255, 0.2)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.boxShadow = 'none';
                });
            });
            
            // Animate rating bars on load
            const ratingBars = document.querySelectorAll('.rating-bar-fill');
            ratingBars.forEach((bar, index) => {
                setTimeout(() => {
                    bar.style.opacity = '1';
                    bar.style.transform = 'scaleX(1)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>