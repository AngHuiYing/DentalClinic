<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$rating_filter = isset($_GET['rating']) ? intval($_GET['rating']) : 0;

// Get doctor ID from doctors table
$sql = "SELECT id FROM doctors WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor_row = $result->fetch_assoc();

if (!$doctor_row) {
    die("Doctor record not found!");
}

$doctor_id = $doctor_row['id'];

// 分頁設定
$reviews_per_page = 6; // 每頁顯示評論數
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $reviews_per_page;

// Get doctor information
$doctor_query = "SELECT * FROM doctors WHERE id = ?";
$doctor_stmt = $conn->prepare($doctor_query);
$doctor_stmt->bind_param("i", $doctor_id);
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();
$doctor = $doctor_result->fetch_assoc();

// 先計算總評論數
if ($rating_filter > 0 && $rating_filter <= 5) {
    $count_query = "SELECT COUNT(*) as total FROM doctor_reviews WHERE doctor_id = ? AND is_approved = 1 AND rating = ?";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("ii", $doctor_id, $rating_filter);
} else {
    $count_query = "SELECT COUNT(*) as total FROM doctor_reviews WHERE doctor_id = ? AND is_approved = 1";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("i", $doctor_id);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$filtered_total = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// 計算分頁
$total_pages = ceil($filtered_total / $reviews_per_page);
$start_review = ($current_page - 1) * $reviews_per_page + 1;
$end_review = min($current_page * $reviews_per_page, $filtered_total);

// 獲取所有評論總數（用於統計）
$total_count_query = "SELECT COUNT(*) as total FROM doctor_reviews WHERE doctor_id = ? AND is_approved = 1";
$total_count_stmt = $conn->prepare($total_count_query);
$total_count_stmt->bind_param("i", $doctor_id);
$total_count_stmt->execute();
$total_count_result = $total_count_stmt->get_result();
$total_reviews_count = $total_count_result->fetch_assoc()['total'];
$total_count_stmt->close();

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
    <title>My Reviews - Doctor Dashboard</title>
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
            padding: 2rem;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            margin-bottom: 2rem;
        }
        
        .review-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1.5rem;
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
        
        .stats-card {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .stats-number {
            font-size: 3rem;
            font-weight: bold;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
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
        
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
        }
        
        .navbar-brand, .nav-link {
            color: #667eea !important;
        }
        
        /* Pagination Styles */
        .pagination-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 10px;
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
            background: rgba(255, 255, 255, 0.3);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }
        
        .page-btn.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-color: transparent;
            font-weight: 700;
        }
        
        .page-btn.disabled {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-info {
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container py-5" style="margin-top: 80px;">
        <!-- Header -->
        <div class="glass-container">
            <div class="text-center mb-4">
                <h1><i class="fas fa-star-half-alt me-2"></i>My Patient Reviews</h1>
                <p class="text-white mb-0">See what your patients are saying about your care</p>
            </div>
        </div>
        
        <!-- Statistics Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $avg_rating; ?></div>
                    <h5 class="text-white">Average Rating</h5>
                    <div class="mb-2">
                        <?php echo renderStars($avg_rating); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $total_reviews; ?></div>
                    <h5 class="text-white">Total Reviews</h5>
                    <i class="fas fa-comments fa-2x text-white opacity-50"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo getPercentage($stats['five_star'], $total_reviews); ?>%</div>
                    <h5 class="text-white">5-Star Reviews</h5>
                    <i class="fas fa-star fa-2x text-warning"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo getPercentage($stats['five_star'] + $stats['four_star'], $total_reviews); ?>%</div>
                    <h5 class="text-white">Positive Reviews</h5>
                    <i class="fas fa-thumbs-up fa-2x text-success"></i>
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
                    <a href="my_reviews.php" class="clear-filter">
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
                    <i class="fas fa-comments me-2"></i>Recent Reviews
                    <?php if ($rating_filter > 0): ?>
                        <small class="text-warning ms-2">(<i class="fas fa-star"></i> <?php echo $rating_filter; ?> Stars)</small>
                    <?php endif; ?>
                </h3>
                <?php if ($total_pages > 1): ?>
                <small class="text-white opacity-75">
                    Page <?= $current_page ?> of <?= $total_pages ?> (<?= $start_review ?>-<?= $end_review ?> of <?= $filtered_total ?>)
                </small>
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
            <?php else: ?>
                <div class="no-reviews">
                    <i class="fas fa-star fa-3x mb-3 opacity-50"></i>
                    <?php if ($rating_filter > 0): ?>
                        <h4>No <?php echo $rating_filter; ?>-star reviews</h4>
                        <p>You have no <?php echo $rating_filter; ?>-star reviews yet.</p>
                        <a href="my_reviews.php" class="btn btn-secondary mt-3">
                            <i class="fas fa-eye me-2"></i>View All Reviews
                        </a>
                    <?php else: ?>
                        <h4>No reviews yet</h4>
                        <p>You haven't received any patient reviews yet. Keep providing excellent care!</p>
                        <a href="dashboard.php" class="btn btn-primary mt-3">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                Showing <?= $start_review ?>-<?= $end_review ?> of <?= number_format($filtered_total) ?> 
                <?= $rating_filter > 0 ? $rating_filter . '-star ' : '' ?>reviews
            </div>
            
            <div class="pagination-nav">
                <!-- Previous Page -->
                <?php if ($current_page > 1): ?>
                    <?php 
                    $prev_params = $_GET;
                    $prev_params['page'] = $current_page - 1;
                    if ($rating_filter > 0) {
                        $prev_params['rating'] = $rating_filter;
                    }
                    ?>
                    <a href="?<?= http_build_query($prev_params) ?>" class="page-btn">
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
                    $first_params = $_GET;
                    $first_params['page'] = 1;
                    if ($rating_filter > 0) {
                        $first_params['rating'] = $rating_filter;
                    }
                    echo '<a href="?' . http_build_query($first_params) . '" class="page-btn">1</a>';
                    if ($start_page > 2) {
                        echo '<span class="page-btn disabled">...</span>';
                    }
                }
                
                // Show page numbers in range
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $page_params = $_GET;
                    $page_params['page'] = $i;
                    if ($rating_filter > 0) {
                        $page_params['rating'] = $rating_filter;
                    }
                    if ($i == $current_page) {
                        echo '<span class="page-btn active">' . $i . '</span>';
                    } else {
                        echo '<a href="?' . http_build_query($page_params) . '" class="page-btn">' . $i . '</a>';
                    }
                }
                
                // Show last page if not in range
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span class="page-btn disabled">...</span>';
                    }
                    $last_params = $_GET;
                    $last_params['page'] = $total_pages;
                    if ($rating_filter > 0) {
                        $last_params['rating'] = $rating_filter;
                    }
                    echo '<a href="?' . http_build_query($last_params) . '" class="page-btn">' . $total_pages . '</a>';
                }
                ?>
                
                <!-- Next Page -->
                <?php if ($current_page < $total_pages): ?>
                    <?php 
                    $next_params = $_GET;
                    $next_params['page'] = $current_page + 1;
                    if ($rating_filter > 0) {
                        $next_params['rating'] = $rating_filter;
                    }
                    ?>
                    <a href="?<?= http_build_query($next_params) ?>" class="page-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="page-btn disabled">
                        Next <i class="fas fa-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterByRating(rating) {
            const currentRating = new URLSearchParams(window.location.search).get('rating');
            
            // If clicking the same rating, remove filter (show all)
            if (currentRating == rating) {
                window.location.href = 'my_reviews.php';
            } else {
                // Apply new rating filter
                window.location.href = `my_reviews.php?rating=${rating}`;
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