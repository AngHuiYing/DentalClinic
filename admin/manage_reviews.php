<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle review approval/rejection/reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['review_id'])) {
        $review_id = intval($_POST['review_id']);
        $action = $_POST['action'];
        $admin_id = $_SESSION['admin_id'];
        
        if ($action === 'approve') {
            $update_query = "UPDATE doctor_reviews SET is_approved = 1 WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("i", $review_id);
        } elseif ($action === 'reject') {
            $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : 'No reason provided';
            $update_query = "UPDATE doctor_reviews SET is_approved = 0, rejection_reason = ?, admin_id = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sii", $rejection_reason, $admin_id, $review_id);
        } elseif ($action === 'reply') {
            $admin_reply = isset($_POST['admin_reply']) ? trim($_POST['admin_reply']) : '';
            if (!empty($admin_reply)) {
                $update_query = "UPDATE doctor_reviews SET admin_reply = ?, admin_id = ?, replied_at = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("sii", $admin_reply, $admin_id, $review_id);
            } else {
                $error_message = "Reply content cannot be empty.";
            }
        } elseif ($action === 'delete') {
            $update_query = "DELETE FROM doctor_reviews WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("i", $review_id);
        }
        
        if (isset($update_stmt) && $update_stmt->execute()) {
            $success_message = "Review " . $action . "d successfully!";
        } elseif (!isset($error_message)) {
            $error_message = "Failed to " . $action . " review.";
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$doctor_filter = isset($_GET['doctor']) ? intval($_GET['doctor']) : 0;
$rating_filter = isset($_GET['rating']) ? intval($_GET['rating']) : 0;

// 分頁設定
$reviews_per_page = 8; // 每頁顯示評論數
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $reviews_per_page;

// 先計算總評論數（支持篩選）
$filter_conditions = "";
$count_params = array();
$count_param_types = "";

// Add status filter
if ($status_filter === 'pending') {
    $filter_conditions .= " WHERE is_approved = 0";
} elseif ($status_filter === 'approved') {
    $filter_conditions .= " WHERE is_approved = 1";
}

// Add doctor filter
if ($doctor_filter > 0) {
    $filter_conditions .= ($filter_conditions ? " AND" : " WHERE") . " doctor_id = ?";
    $count_params[] = $doctor_filter;
    $count_param_types .= "i";
}

// Add rating filter
if ($rating_filter > 0 && $rating_filter <= 5) {
    $filter_conditions .= ($filter_conditions ? " AND" : " WHERE") . " rating = ?";
    $count_params[] = $rating_filter;
    $count_param_types .= "i";
}

$count_query = "SELECT COUNT(*) as total FROM doctor_reviews" . $filter_conditions;

if (!empty($count_params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($count_param_types, ...$count_params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
} else {
    $count_result = $conn->query($count_query);
}

$total_reviews = $count_result->fetch_assoc()['total'];

// 計算分頁
$total_pages = ceil($total_reviews / $reviews_per_page);
$start_review = ($current_page - 1) * $reviews_per_page + 1;
$end_review = min($current_page * $reviews_per_page, $total_reviews);

// Get all reviews with doctor and patient information (分頁 + 篩選)
$reviews_query = "SELECT 
    dr.*,
    d.name as doctor_name,
    d.specialty as doctor_specialty,
    a.name as admin_name,
    CASE 
        WHEN dr.is_anonymous = 1 THEN 'Anonymous Patient'
        ELSE dr.patient_name
    END as display_name
    FROM doctor_reviews dr 
    LEFT JOIN doctors d ON dr.doctor_id = d.id
    LEFT JOIN admin a ON dr.admin_id = a.id" . $filter_conditions . "
    ORDER BY dr.created_at DESC
    LIMIT ? OFFSET ?";

$review_params = $count_params;
$review_param_types = $count_param_types;
$review_params[] = $reviews_per_page;
$review_params[] = $offset;
$review_param_types .= "ii";

$stmt = $conn->prepare($reviews_query);
$stmt->bind_param($review_param_types, ...$review_params);
$stmt->execute();
$reviews_result = $stmt->get_result();

// Get all doctors for filter dropdown
$doctors_query = "SELECT DISTINCT d.id, d.name, d.specialty 
                  FROM doctor_reviews dr 
                  LEFT JOIN doctors d ON dr.doctor_id = d.id 
                  ORDER BY d.name";
$doctors_result = $conn->query($doctors_query);
$available_doctors = $doctors_result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_reviews,
    SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved_reviews,
    SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending_reviews,
    AVG(rating) as avg_rating
    FROM doctor_reviews";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

function renderStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '<i class="fas fa-star text-warning"></i>';
        } else {
            $stars .= '<i class="far fa-star text-muted"></i>';
        }
    }
    return $stars;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reviews - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #c3a1e6 0%, #7b6cff 50%, #d1b3ff 100%) !important;
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
        
        .filter-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .filter-badge {
            background: linear-gradient(45deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        
        .filter-badge.status-pending {
            background: linear-gradient(45deg, #f59e0b, #d97706);
        }
        
        .filter-badge.status-approved {
            background: linear-gradient(45deg, #10b981, #059669);
        }
        
        .filter-badge.rating {
            background: linear-gradient(45deg, #fbbf24, #f59e0b);
            color: #333;
        }
        
        .clear-filters {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .clear-filters:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .form-select {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            color: #333;
        }
        
        .form-select:focus {
            background: rgba(255, 255, 255, 0.95);
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
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
        
        .stats-card {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .btn-approve {
            background: linear-gradient(45deg, #10b981, #059669);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .btn-approve:hover {
            background: linear-gradient(45deg, #059669, #047857);
            transform: translateY(-1px);
            color: white;
        }
        
        .btn-reject {
            background: linear-gradient(45deg, #ef4444, #dc2626);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .btn-reject:hover {
            background: linear-gradient(45deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
            color: white;
        }
        
        .btn-delete {
            background: linear-gradient(45deg, #8b5cf6, #7c3aed);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .btn-delete:hover {
            background: linear-gradient(45deg, #7c3aed, #6d28d9);
            transform: translateY(-1px);
            color: white;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-approved {
            background: #10b981;
            color: white;
        }
        
        .status-pending {
            background: #f59e0b;
            color: white;
        }
        
        h1, h2, h3, h4, h5 {
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .text-white {
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
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
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .modal-glass {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .admin-reply {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 0 8px 8px 0;
        }
        
        .rejection-reason {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid #ef4444;
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 0 8px 8px 0;
        }
        
        .btn-reply {
            background: linear-gradient(45deg, #3b82f6, #2563eb);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .btn-reply:hover {
            background: linear-gradient(45deg, #2563eb, #1d4ed8);
            transform: translateY(-1px);
            color: white;
        }
        
        /* Pagination Styles */
        .pagination-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.18);
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
            border: 2px solid rgba(255, 255, 255, 0.3);
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
            backdrop-filter: blur(5px);
        }
        
        .page-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 255, 255, 0.3);
        }
        
        .page-btn.active {
            background: linear-gradient(135deg, #c084fc, #a855f7);
            color: white;
            border-color: rgba(255, 255, 255, 0.5);
            font-weight: 700;
            box-shadow: 0 6px 15px rgba(192, 132, 252, 0.4);
            text-shadow: none;
        }
        
        .page-btn.disabled {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.4);
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-info {
            text-align: center;
            color: white;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <!-- Include system navbar -->
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-5" style="margin-top: 80px;">
        <!-- Header -->
        <div class="glass-container">
            <div class="text-center mb-4">
                <h1><i class="fas fa-star-half-alt me-2"></i>Manage Patient Reviews</h1>
                <p class="text-white mb-0">Review and moderate patient feedback for doctors</p>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Statistics Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total_reviews']; ?></div>
                    <h5 class="text-white">Total Reviews</h5>
                    <i class="fas fa-comments fa-2x text-white opacity-50"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['approved_reviews']; ?></div>
                    <h5 class="text-white">Approved</h5>
                    <i class="fas fa-check-circle fa-2x text-success"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['pending_reviews']; ?></div>
                    <h5 class="text-white">Pending</h5>
                    <i class="fas fa-clock fa-2x text-warning"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo round($stats['avg_rating'], 1); ?></div>
                    <h5 class="text-white">Avg Rating</h5>
                    <i class="fas fa-star fa-2x text-warning"></i>
                </div>
            </div>
        </div>
        
        <!-- Reviews List -->
        <div class="glass-container">
            <!-- Filter Section -->
            <div class="filter-container">
                <div class="row align-items-end">
                    <div class="col-md-9">
                        <h5 class="text-white mb-3"><i class="fas fa-filter me-2"></i>Filter Reviews</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-white">Status:</label>
                                <select class="form-select" id="statusFilter">
                                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Reviews</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>⏳ Pending Review</option>
                                    <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>✅ Approved</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-white">Doctor:</label>
                                <select class="form-select" id="doctorFilter">
                                    <option value="0">All Doctors</option>
                                    <?php foreach ($available_doctors as $doctor): ?>
                                        <option value="<?= $doctor['id'] ?>" <?= $doctor_filter == $doctor['id'] ? 'selected' : '' ?>>
                                            Dr. <?= htmlspecialchars($doctor['name']) ?> (<?= htmlspecialchars($doctor['specialty']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-white">Rating:</label>
                                <select class="form-select" id="ratingFilter">
                                    <option value="0">All Ratings</option>
                                    <option value="1" <?= $rating_filter == 1 ? 'selected' : '' ?>>⭐ 1 Star (Critical)</option>
                                    <option value="2" <?= $rating_filter == 2 ? 'selected' : '' ?>>⭐⭐ 2 Stars</option>
                                    <option value="3" <?= $rating_filter == 3 ? 'selected' : '' ?>>⭐⭐⭐ 3 Stars</option>
                                    <option value="4" <?= $rating_filter == 4 ? 'selected' : '' ?>>⭐⭐⭐⭐ 4 Stars</option>
                                    <option value="5" <?= $rating_filter == 5 ? 'selected' : '' ?>>⭐⭐⭐⭐⭐ 5 Stars</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 text-end">
                        <?php if ($status_filter !== 'all' || $doctor_filter > 0 || $rating_filter > 0): ?>
                            <div class="mb-3">
                                <small class="text-white d-block mb-2">Active Filters:</small>
                                <?php if ($status_filter !== 'all'): ?>
                                    <span class="filter-badge status-<?= $status_filter ?>">
                                        <i class="fas fa-<?= $status_filter === 'pending' ? 'clock' : 'check' ?> me-1"></i>
                                        <?= ucfirst($status_filter) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($doctor_filter > 0): 
                                    $selected_doctor = array_filter($available_doctors, function($d) use ($doctor_filter) {
                                        return $d['id'] == $doctor_filter;
                                    });
                                    $selected_doctor = reset($selected_doctor);
                                ?>
                                    <span class="filter-badge">
                                        <i class="fas fa-user-md me-1"></i>Dr. <?= htmlspecialchars($selected_doctor['name']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($rating_filter > 0): ?>
                                    <span class="filter-badge rating">
                                        <i class="fas fa-star me-1"></i><?= $rating_filter ?> Stars
                                    </span>
                                <?php endif; ?>
                            </div>
                            <a href="manage_reviews.php" class="clear-filters">
                                <i class="fas fa-times me-1"></i>Clear All
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0">
                    <i class="fas fa-list me-2"></i>Reviews 
                    <?php if ($status_filter !== 'all' || $doctor_filter > 0 || $rating_filter > 0): ?>
                        <small class="text-warning">(Filtered Results)</small>
                    <?php endif; ?>
                </h3>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-light text-dark fs-6">
                        <?php echo $reviews_result->num_rows; ?> of <?= number_format($total_reviews) ?> Reviews
                    </span>
                    <?php if ($total_pages > 1): ?>
                    <small class="text-white opacity-85">
                        Page <?= $current_page ?> of <?= $total_pages ?>
                    </small>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($reviews_result->num_rows > 0): ?>
                <?php while ($review = $reviews_result->fetch_assoc()): ?>
                <div class="review-card">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="review-title"><?php echo htmlspecialchars($review['review_title']); ?></h5>
                                    <div class="mb-2">
                                        <?php echo renderStars($review['rating']); ?>
                                        <span class="text-white ms-2"><?php echo $review['rating']; ?>/5</span>
                                    </div>
                                    <div class="review-meta mb-2">
                                        <strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($review['doctor_name']); ?> 
                                        (<?php echo htmlspecialchars($review['doctor_specialty']); ?>)
                                    </div>
                                    <div class="review-meta mb-2">
                                        <strong>Patient:</strong> <?php echo htmlspecialchars($review['display_name']); ?> 
                                        <?php if (!$review['is_anonymous']): ?>
                                            (<?php echo htmlspecialchars($review['patient_email']); ?>)
                                        <?php endif; ?>
                                    </div>
                                    <div class="review-meta mb-2">
                                        <strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($review['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="status-badge <?php echo $review['is_approved'] ? 'status-approved' : 'status-pending'; ?>">
                                        <?php echo $review['is_approved'] ? 'Approved' : 'Pending'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="review-text">
                                <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                            </div>
                            
                            <!-- Admin Reply Display -->
                            <?php if (!empty($review['admin_reply'])): ?>
                            <div class="admin-reply">
                                <h6 class="text-primary mb-2">
                                    <i class="fas fa-reply me-2"></i>Admin Reply:
                                </h6>
                                <p class="mb-2"><?php echo nl2br(htmlspecialchars($review['admin_reply'])); ?></p>
                                <small class="text-muted">
                                    <?php if (!empty($review['admin_name'])): ?>
                                        By: <?php echo htmlspecialchars($review['admin_name']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($review['replied_at'])): ?>
                                        on <?php echo date('M j, Y g:i A', strtotime($review['replied_at'])); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Rejection Reason Display -->
                            <?php if (!$review['is_approved'] && !empty($review['rejection_reason'])): ?>
                            <div class="rejection-reason">
                                <h6 class="text-danger mb-2">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Rejection Reason:
                                </h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['rejection_reason'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <div class="d-grid gap-2">
                                <?php if (!$review['is_approved']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-approve w-100">
                                            <i class="fas fa-check me-2"></i>Approve
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-reject w-100" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#rejectModal<?php echo $review['id']; ?>">
                                        <i class="fas fa-times me-2"></i>Reject
                                    </button>
                                <?php endif; ?>
                                
                                <!-- Reply Button -->
                                <button type="button" class="btn btn-reply w-100" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#replyModal<?php echo $review['id']; ?>">
                                    <i class="fas fa-reply me-2"></i>Reply
                                </button>
                                
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('Are you sure you want to delete this review? This action cannot be undone.')">
                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-delete w-100">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-star fa-3x mb-3 opacity-50 text-white"></i>
                    <?php if ($status_filter !== 'all' || $doctor_filter > 0 || $rating_filter > 0): ?>
                        <h4 class="text-white">No reviews match your filters</h4>
                        <p class="text-white opacity-75">Try adjusting your filter criteria or clear all filters.</p>
                        <a href="manage_reviews.php" class="btn btn-light mt-3">
                            <i class="fas fa-times me-2"></i>Clear All Filters
                        </a>
                    <?php else: ?>
                        <h4 class="text-white">No reviews found</h4>
                        <p class="text-white opacity-75">No patient reviews have been submitted yet.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <?= $start_review ?>-<?= $end_review ?> of <?= number_format($total_reviews) ?> reviews
                </div>
                
                <div class="pagination-nav">
                    <!-- Previous Page -->
                    <?php if ($current_page > 1): ?>
                        <?php 
                        $prev_params = $_GET;
                        $prev_params['page'] = $current_page - 1;
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
                        echo '<a href="?' . http_build_query($first_params) . '" class="page-btn">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="page-btn disabled">...</span>';
                        }
                    }
                    
                    // Show page numbers in range
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $page_params = $_GET;
                        $page_params['page'] = $i;
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
                        echo '<a href="?' . http_build_query($last_params) . '" class="page-btn">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <!-- Next Page -->
                    <?php if ($current_page < $total_pages): ?>
                        <?php 
                        $next_params = $_GET;
                        $next_params['page'] = $current_page + 1;
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
    </div>
    
    <!-- Modals for each review -->
    <?php
    $reviews_result->data_seek(0); // Reset result pointer
    while ($review = $reviews_result->fetch_assoc()): 
    ?>
    
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal<?php echo $review['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content modal-glass">
                <div class="modal-header">
                    <h5 class="modal-title text-dark">
                        <i class="fas fa-times-circle text-danger me-2"></i>Reject Review
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                        <input type="hidden" name="action" value="reject">
                        
                        <div class="mb-3">
                            <label for="rejection_reason<?php echo $review['id']; ?>" class="form-label text-dark">
                                <strong>Reason for rejection:</strong>
                            </label>
                            <textarea class="form-control" 
                                      id="rejection_reason<?php echo $review['id']; ?>" 
                                      name="rejection_reason" 
                                      rows="4" 
                                      placeholder="Please provide a reason for rejecting this review..."
                                      required></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> This review will be hidden from public view and the rejection reason will be recorded.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-2"></i>Reject Review
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reply Modal -->
    <div class="modal fade" id="replyModal<?php echo $review['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content modal-glass">
                <div class="modal-header">
                    <h5 class="modal-title text-dark">
                        <i class="fas fa-reply text-primary me-2"></i>Reply to Review
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                        <input type="hidden" name="action" value="reply">
                        
                        <!-- Review Summary -->
                        <div class="mb-4 p-3 bg-light rounded">
                            <h6 class="text-dark mb-2">Original Review:</h6>
                            <div class="text-muted small">
                                <strong>Patient:</strong> <?php echo htmlspecialchars($review['display_name']); ?><br>
                                <strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($review['doctor_name']); ?><br>
                                <strong>Rating:</strong> <?php echo renderStars($review['rating']); ?> (<?php echo $review['rating']; ?>/5)
                            </div>
                            <div class="mt-2">
                                <strong class="text-dark"><?php echo htmlspecialchars($review['review_title']); ?></strong>
                                <p class="text-dark mb-0"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_reply<?php echo $review['id']; ?>" class="form-label text-dark">
                                <strong>Your Reply:</strong>
                            </label>
                            <textarea class="form-control" 
                                      id="admin_reply<?php echo $review['id']; ?>" 
                                      name="admin_reply" 
                                      rows="5" 
                                      placeholder="Write your professional response to this review..."
                                      required><?php echo htmlspecialchars($review['admin_reply']); ?></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Your reply will be visible to all users viewing this review.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Send Reply
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php endwhile; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const statusFilter = document.getElementById('statusFilter');
            const doctorFilter = document.getElementById('doctorFilter');
            const ratingFilter = document.getElementById('ratingFilter');
            
            function applyFilters() {
                const statusValue = statusFilter ? statusFilter.value : 'all';
                const doctorValue = doctorFilter ? doctorFilter.value : '0';
                const ratingValue = ratingFilter ? ratingFilter.value : '0';
                
                let url = 'manage_reviews.php';
                const params = new URLSearchParams();
                
                if (statusValue !== 'all') {
                    params.append('status', statusValue);
                }
                
                if (doctorValue !== '0') {
                    params.append('doctor', doctorValue);
                }
                
                if (ratingValue !== '0') {
                    params.append('rating', ratingValue);
                }
                
                if (params.toString()) {
                    url += '?' + params.toString();
                }
                
                window.location.href = url;
            }
            
            if (statusFilter) {
                statusFilter.addEventListener('change', applyFilters);
            }
            
            if (doctorFilter) {
                doctorFilter.addEventListener('change', applyFilters);
            }
            
            if (ratingFilter) {
                ratingFilter.addEventListener('change', applyFilters);
            }
        });
    </script>
</body>
</html>