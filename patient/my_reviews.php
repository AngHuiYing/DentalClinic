<?php
session_start();
require_once '../includes/db.php';

// Helper function to check user role safely
function isPatient() {
    return isset($_SESSION['user_id']) && 
           ((isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'patient') || 
            (isset($_SESSION['role']) && $_SESSION['role'] === 'patient'));
}

// Check if user is logged in and is a patient
if (!isPatient()) {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['user_id'];

// Get filter parameters
$doctor_filter = isset($_GET['doctor']) ? intval($_GET['doctor']) : 0;
$rating_filter = isset($_GET['rating']) ? intval($_GET['rating']) : 0;

// Handle edit request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit' && isset($_POST['review_id'])) {
        $review_id = intval($_POST['review_id']);
        $rating = intval($_POST['rating']);
        $review_title = trim($_POST['review_title']);
        $review_text = trim($_POST['review_text']);
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        
        // Verify this review belongs to the current patient
        $verify_query = "SELECT id FROM doctor_reviews WHERE id = ? AND patient_id = ?";
        $verify_stmt = $conn->prepare($verify_query);
        $verify_stmt->bind_param("ii", $review_id, $patient_id);
        $verify_stmt->execute();
        
        if ($verify_stmt->get_result()->num_rows > 0) {
            // Update review
            $update_query = "UPDATE doctor_reviews SET rating = ?, review_title = ?, review_text = ?, is_anonymous = ?, updated_at = NOW() WHERE id = ? AND patient_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("issiii", $rating, $review_title, $review_text, $is_anonymous, $review_id, $patient_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['success'] = "Review updated successfully!";
                header("Location: my_reviews.php");
                exit();
            } else {
                $_SESSION['error'] = "Failed to update review.";
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['review_id'])) {
        $review_id = intval($_POST['review_id']);
        
        // Verify this review belongs to the current patient
        $verify_query = "SELECT id FROM doctor_reviews WHERE id = ? AND patient_id = ?";
        $verify_stmt = $conn->prepare($verify_query);
        $verify_stmt->bind_param("ii", $review_id, $patient_id);
        $verify_stmt->execute();
        
        if ($verify_stmt->get_result()->num_rows > 0) {
            // Delete the review
            $delete_query = "DELETE FROM doctor_reviews WHERE id = ? AND patient_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("ii", $review_id, $patient_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['success'] = "Review deleted successfully!";
                header("Location: my_reviews.php");
                exit();
            } else {
                $_SESSION['error'] = "Failed to delete review.";
            }
        } else {
            $_SESSION['error'] = "Review not found or you don't have permission to delete it.";
        }
    }
}

// Get user's reviews with filters
$filter_conditions = "";
$params = array($patient_id);
$param_types = "i";

// Add doctor filter
if ($doctor_filter > 0) {
    $filter_conditions .= " AND dr.doctor_id = ?";
    $params[] = $doctor_filter;
    $param_types .= "i";
}

// Add rating filter
if ($rating_filter > 0 && $rating_filter <= 5) {
    $filter_conditions .= " AND dr.rating = ?";
    $params[] = $rating_filter;
    $param_types .= "i";
}

$reviews_query = "SELECT dr.*, d.name as doctor_name, d.specialty, d.image as doctor_image
                  FROM doctor_reviews dr
                  LEFT JOIN doctors d ON dr.doctor_id = d.id
                  WHERE dr.patient_id = ?" . $filter_conditions . "
                  ORDER BY dr.created_at DESC";
$reviews_stmt = $conn->prepare($reviews_query);
$reviews_stmt->bind_param($param_types, ...$params);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();

// Get all doctors that this patient has reviewed (for filter dropdown)
$doctors_query = "SELECT DISTINCT d.id, d.name, d.specialty 
                  FROM doctor_reviews dr 
                  LEFT JOIN doctors d ON dr.doctor_id = d.id 
                  WHERE dr.patient_id = ? 
                  ORDER BY d.name";
$doctors_stmt = $conn->prepare($doctors_query);
$doctors_stmt->bind_param("i", $patient_id);
$doctors_stmt->execute();
$doctors_result = $doctors_stmt->get_result();
$available_doctors = $doctors_result->fetch_all(MYSQLI_ASSOC);

// Get total count for display
$total_query = "SELECT COUNT(*) as total FROM doctor_reviews WHERE patient_id = ?";
$total_stmt = $conn->prepare($total_query);
$total_stmt->bind_param("i", $patient_id);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_reviews = $total_result->fetch_assoc()['total'];

// Helper function to render stars
function renderStars($rating, $editable = false, $review_id = null) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $filled = $i <= $rating ? 'fas' : 'far';
        $stars .= '<i class="' . $filled . ' fa-star"></i>';
    }
    return $stars;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews - Dental Clinic</title>
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
        
        .doctor-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .doctor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .stars {
            color: #fbbf24;
            margin: 0.5rem 0;
        }
        
        .review-text {
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .review-meta {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 1rem;
        }
        
        .review-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-edit, .btn-delete-request {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-edit {
            background: linear-gradient(45deg, #3b82f6, #2563eb);
            color: white;
        }
        
        .btn-edit:hover {
            background: linear-gradient(45deg, #2563eb, #1d4ed8);
            color: white;
            text-decoration: none;
        }
        
        .btn-delete-request {
            background: linear-gradient(45deg, #f59e0b, #d97706);
            color: white;
        }
        
        .btn-delete-request:hover {
            background: linear-gradient(45deg, #d97706, #b45309);
            color: white;
            text-decoration: none;
        }
        
        .modal-glass {
            background: rgba(255, 255, 255, 0.98) !important;  /* Much more opaque for brightness */
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;  /* Stronger shadow for definition */
        }
        
        .modal {
            z-index: 10050 !important;
        }
        
        .modal-backdrop {
            z-index: 10040 !important;
        }
        
        .modal-dialog {
            z-index: 10060 !important;
            margin: 1.75rem auto;
        }
        
        .rating-stars-edit {
            font-size: 1.5rem;
            margin: 1rem 0;
        }
        
        .rating-stars-edit .star {
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .rating-stars-edit .star:hover,
        .rating-stars-edit .star.active {
            color: #fbbf24;
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
            padding: 3rem 2rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .edit-note {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            padding: 1rem;
            border-radius: 0 8px 8px 0;
            margin-top: 1rem;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        /* Additional Modal Fixes */
        .modal-content {
            position: relative;
            z-index: 10070 !important;
        }
        
        .modal.show {
            display: block !important;
            opacity: 1 !important;
        }
        
        .modal.show .modal-dialog {
            transform: translateY(0) !important;
            opacity: 1 !important;
        }
        
        .modal.show .modal-content {
            opacity: 1 !important;
            filter: brightness(1.1) !important;  /* Slightly brighter */
        }
        
        /* Ensure proper positioning */
        .modal-dialog-centered {
            min-height: calc(100% - 3.5rem);
        }
        
        /* Center Modal properly */
        .modal-dialog {
            display: flex;
            align-items: center;
            min-height: calc(100vh - 1rem);
        }
        
        /* Fix backdrop issues */
        .modal-backdrop.show {
            opacity: 0.2 !important;  /* Much lower opacity for brighter modal */
            pointer-events: none !important;
        }
        
        .modal-backdrop {
            opacity: 0.2 !important;  /* Much lower opacity */
            pointer-events: none !important;
        }
        
        /* Force Modal interactivity */
        .modal,
        .modal-dialog,
        .modal-content,
        .modal-header,
        .modal-body,
        .modal-footer {
            pointer-events: auto !important;
            position: relative;
        }
        
        /* Ensure all interactive elements work */
        .modal * {
            pointer-events: auto !important;
        }
        
        /* Ensure backdrop doesn't block page content when modal is closed */
        .modal:not(.show) .modal-backdrop {
            display: none !important;
        }
        
        /* Ensure form elements are clickable */
        .modal .form-control,
        .modal .form-check-input,
        .modal .btn,
        .modal .rating-stars-edit .star {
            pointer-events: auto !important;
            position: relative;
            z-index: 10080 !important;
        }
    </style>
</head>
<body>
    <!-- Include navbar -->
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container py-5" style="margin-top: 80px;">
        <!-- Header -->
        <div class="glass-container">
            <div class="text-center mb-4">
                <h1><i class="fas fa-star me-2"></i>My Reviews</h1>
                <p class="text-white mb-0">Manage your doctor reviews and feedback</p>
            </div>
            
            <!-- Information Notice -->
            <div class="alert alert-info d-flex align-items-center">
                <i class="fas fa-info-circle me-3 fs-5"></i>
                <div>
                    <strong>How to add reviews:</strong> You can only review doctors you have visited. 
                    To add a new review, go to <a href="my_appointments.php" class="text-decoration-none fw-bold">My Appointments</a> 
                    and click " <i class="fas fa-star"></i> Rate Doctor " next to completed appointments.
                </div>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['info'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i><?php echo $_SESSION['info']; unset($_SESSION['info']); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Reviews List -->
        <div class="glass-container">
            <?php if ($total_reviews > 0): ?>
                <!-- Filter Section -->
                <div class="filter-container">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="text-white mb-3"><i class="fas fa-filter me-2"></i>Filter Reviews</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-white">Filter by Doctor:</label>
                                    <select class="form-select" id="doctorFilter">
                                        <option value="0">All Doctors</option>
                                        <?php foreach ($available_doctors as $doctor): ?>
                                            <option value="<?= $doctor['id'] ?>" <?= $doctor_filter == $doctor['id'] ? 'selected' : '' ?>>
                                                Dr. <?= htmlspecialchars($doctor['name']) ?> (<?= htmlspecialchars($doctor['specialty']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-white">Filter by Rating:</label>
                                    <select class="form-select" id="ratingFilter">
                                        <option value="0">All Ratings</option>
                                        <option value="5" <?= $rating_filter == 5 ? 'selected' : '' ?>>⭐⭐⭐⭐⭐ (5 Stars)</option>
                                        <option value="4" <?= $rating_filter == 4 ? 'selected' : '' ?>>⭐⭐⭐⭐ (4 Stars)</option>
                                        <option value="3" <?= $rating_filter == 3 ? 'selected' : '' ?>>⭐⭐⭐ (3 Stars)</option>
                                        <option value="2" <?= $rating_filter == 2 ? 'selected' : '' ?>>⭐⭐ (2 Stars)</option>
                                        <option value="1" <?= $rating_filter == 1 ? 'selected' : '' ?>>⭐ (1 Star)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php if ($doctor_filter > 0 || $rating_filter > 0): ?>
                                <div class="mb-3">
                                    <small class="text-white d-block mb-2">Active Filters:</small>
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
                                        <span class="filter-badge">
                                            <i class="fas fa-star me-1"></i><?= $rating_filter ?> Stars
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <a href="my_reviews.php" class="clear-filters">
                                    <i class="fas fa-times me-1"></i>Clear All Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0">
                        <i class="fas fa-list me-2"></i>Your Reviews 
                        <small class="text-warning">(<?= $reviews_result->num_rows ?> of <?= $total_reviews ?>)</small>
                    </h3>
                </div>
                
                <?php while ($review = $reviews_result->fetch_assoc()): ?>
                <div class="review-card">
                    <div class="doctor-info">
                        <img src="../<?= htmlspecialchars($review['doctor_image']) ?>" 
                             alt="Dr. <?= htmlspecialchars($review['doctor_name']) ?>"
                             class="doctor-avatar"
                             onerror="this.src='https://via.placeholder.com/60x60/3b82f6/ffffff?text=Dr'">
                        <div>
                            <h4 class="text-white mb-1">Dr. <?= htmlspecialchars($review['doctor_name']) ?></h4>
                            <p class="text-white-50 mb-0"><?= htmlspecialchars($review['specialty']) ?></p>
                        </div>
                    </div>
                    
                    <div class="stars">
                        <?= renderStars($review['rating']) ?>
                        <span class="text-white ms-2"><?= $review['rating'] ?>/5</span>
                    </div>
                    
                    <h5 class="text-white"><?= htmlspecialchars($review['review_title']) ?></h5>
                    <div class="review-text">
                        <?= nl2br(htmlspecialchars($review['review_text'])) ?>
                    </div>
                    
                    <div class="review-meta">
                        <i class="fas fa-calendar me-1"></i>
                        Posted on <?= date('M j, Y', strtotime($review['created_at'])) ?>
                        <?php if ($review['updated_at'] != $review['created_at']): ?>
                            • <i class="fas fa-edit me-1"></i>Updated on <?= date('M j, Y', strtotime($review['updated_at'])) ?>
                        <?php endif; ?>
                        <?php if ($review['is_anonymous']): ?>
                            • <i class="fas fa-user-secret me-1"></i>Anonymous
                        <?php endif; ?>
                        • Status: <?= $review['is_approved'] ? '<span class="text-success">Approved</span>' : '<span class="text-warning">Pending Review</span>' ?>
                    </div>
                    
                    <div class="review-actions">
                        <button type="button" class="btn-edit" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editModal<?= $review['id'] ?>">
                            <i class="fas fa-edit me-1"></i>Edit Review
                        </button>
                        
                        <button type="button" class="btn-delete-request" 
                                data-bs-toggle="modal" 
                                data-bs-target="#deleteModal<?= $review['id'] ?>">
                            <i class="fas fa-trash me-1"></i>Delete Review
                        </button>
                    </div>
                    
                    <div class="edit-note">
                        <small class="text-white-75">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Note:</strong> You can edit or delete your review at any time. Changes will be reflected immediately.
                        </small>
                    </div>
                </div>
                
                <!-- Edit Modal -->
                <div class="modal fade" id="editModal<?= $review['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content modal-glass">
                            <div class="modal-header">
                                <h5 class="modal-title text-dark">
                                    <i class="fas fa-edit text-primary me-2"></i>Edit Review for Dr. <?= htmlspecialchars($review['doctor_name']) ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-dark"><strong>Rating:</strong></label>
                                        <div class="rating-stars-edit" data-rating="<?= $review['rating'] ?>">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star star" data-rating="<?= $i ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <input type="hidden" name="rating" value="<?= $review['rating'] ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="review_title<?= $review['id'] ?>" class="form-label text-dark"><strong>Review Title:</strong></label>
                                        <input type="text" class="form-control" id="review_title<?= $review['id'] ?>" 
                                               name="review_title" value="<?= htmlspecialchars($review['review_title']) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="review_text<?= $review['id'] ?>" class="form-label text-dark"><strong>Your Review:</strong></label>
                                        <textarea class="form-control" id="review_text<?= $review['id'] ?>" 
                                                  name="review_text" rows="5" required><?= htmlspecialchars($review['review_text']) ?></textarea>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="anonymous<?= $review['id'] ?>" 
                                               name="is_anonymous" <?= $review['is_anonymous'] ? 'checked' : '' ?>>
                                        <label class="form-check-label text-dark" for="anonymous<?= $review['id'] ?>">
                                            Post as anonymous
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update Review
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Delete Confirmation Modal -->
                <div class="modal fade" id="deleteModal<?= $review['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content modal-glass">
                            <div class="modal-header">
                                <h5 class="modal-title text-dark">
                                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>Delete Review
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                    
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Warning:</strong> This action cannot be undone. Are you sure you want to delete this review?
                                    </div>
                                    
                                    <div class="review-preview p-3 bg-light rounded">
                                        <h6 class="text-dark mb-2"><?= htmlspecialchars($review['review_title']) ?></h6>
                                        <div class="text-muted small mb-2">
                                            Rating: <?= str_repeat('⭐', $review['rating']) ?> (<?= $review['rating'] ?>/5)
                                        </div>
                                        <p class="text-dark mb-0"><?= nl2br(htmlspecialchars(substr($review['review_text'], 0, 100))) ?><?= strlen($review['review_text']) > 100 ? '...' : '' ?></p>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash me-2"></i>Delete Review
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                
            <?php else: ?>
                <?php if ($doctor_filter > 0 || $rating_filter > 0): ?>
                    <!-- No filtered results -->
                    <div class="filter-container">
                        <div class="text-center">
                            <h5 class="text-white mb-3">No reviews match your filters</h5>
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
                                <span class="filter-badge">
                                    <i class="fas fa-star me-1"></i><?= $rating_filter ?> Stars
                                </span>
                            <?php endif; ?>
                            <div class="mt-3">
                                <a href="my_reviews.php" class="clear-filters">
                                    <i class="fas fa-eye me-1"></i>View All Reviews
                                </a>
                            </div>
                        </div>
                    </div>
                <?php elseif ($total_reviews > 0): ?>
                    <!-- Has reviews but something went wrong -->
                    <div class="no-reviews">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3 text-warning"></i>
                        <h4>Unable to load reviews</h4>
                        <p>There was an issue loading your reviews. Please try refreshing the page.</p>
                        <button onclick="location.reload()" class="btn btn-primary mt-3">
                            <i class="fas fa-refresh me-2"></i>Refresh Page
                        </button>
                    </div>
                <?php else: ?>
                    <!-- No reviews at all -->
                    <div class="no-reviews">
                        <i class="fas fa-star fa-3x mb-3 opacity-50"></i>
                        <h4>No Reviews Yet</h4>
                        <p>You haven't written any reviews yet. After completing appointments, you can rate and review your doctors.</p>
                        <a href="my_appointments.php" class="btn btn-primary mt-3">
                            <i class="fas fa-calendar me-2"></i>View My Appointments
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const doctorFilter = document.getElementById('doctorFilter');
            const ratingFilter = document.getElementById('ratingFilter');
            
            function applyFilters() {
                const doctorValue = doctorFilter ? doctorFilter.value : '0';
                const ratingValue = ratingFilter ? ratingFilter.value : '0';
                
                let url = 'my_reviews.php';
                const params = new URLSearchParams();
                
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
            
            if (doctorFilter) {
                doctorFilter.addEventListener('change', applyFilters);
            }
            
            if (ratingFilter) {
                ratingFilter.addEventListener('change', applyFilters);
            }
        });
        
        // Star rating functionality for edit modals
        document.addEventListener('DOMContentLoaded', function() {
            const ratingContainers = document.querySelectorAll('.rating-stars-edit');
            
            ratingContainers.forEach(container => {
                const stars = container.querySelectorAll('.star');
                const hiddenInput = container.nextElementSibling;
                const currentRating = parseInt(container.dataset.rating);
                
                // Set initial rating
                updateStars(stars, currentRating);
                
                stars.forEach(star => {
                    star.addEventListener('click', function() {
                        const rating = parseInt(this.dataset.rating);
                        hiddenInput.value = rating;
                        updateStars(stars, rating);
                    });
                    
                    star.addEventListener('mouseenter', function() {
                        const rating = parseInt(this.dataset.rating);
                        updateStars(stars, rating);
                    });
                });
                
                container.addEventListener('mouseleave', function() {
                    const currentRating = parseInt(hiddenInput.value);
                    updateStars(stars, currentRating);
                });
            });
            
            function updateStars(stars, rating) {
                stars.forEach((star, index) => {
                    if (index < rating) {
                        star.classList.add('active');
                    } else {
                        star.classList.remove('active');
                    }
                });
            }
            
            // Fix modal z-index issues and ensure proper display
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('show.bs.modal', function(e) {
                    // Ensure modal appears above navbar with proper z-index
                    this.style.zIndex = '10050';
                    this.style.position = 'fixed';
                    this.style.display = 'block';
                    this.style.pointerEvents = 'auto';
                    
                    // Clean up any existing backdrops
                    const existingBackdrops = document.querySelectorAll('.modal-backdrop');
                    existingBackdrops.forEach(backdrop => backdrop.remove());
                });
                
                modal.addEventListener('shown.bs.modal', function(e) {
                    // Ensure modal dialog is positioned correctly and interactive
                    const dialog = this.querySelector('.modal-dialog');
                    if (dialog) {
                        dialog.style.zIndex = '10060';
                        dialog.style.position = 'relative';
                        dialog.style.pointerEvents = 'auto';
                    }
                    
                    // Ensure modal content is interactive and bright
                    const content = this.querySelector('.modal-content');
                    if (content) {
                        content.style.zIndex = '10070';
                        content.style.pointerEvents = 'auto';
                        content.style.opacity = '1';
                        content.style.filter = 'brightness(1.1)';
                        content.style.backgroundColor = 'rgba(255, 255, 255, 0.98)';
                    }
                    
                    // Fix backdrop - make it non-blocking and very transparent
                    setTimeout(() => {
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) {
                            backdrop.style.zIndex = '10040';
                            backdrop.style.pointerEvents = 'none'; // This is key!
                            backdrop.style.opacity = '0.2'; // Very low opacity for brightness
                            backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.2)';
                        }
                    }, 50);
                    
                    // Make all form elements explicitly clickable
                    const formElements = this.querySelectorAll('input, textarea, button, .star');
                    formElements.forEach(element => {
                        element.style.pointerEvents = 'auto';
                        element.style.position = 'relative';
                        element.style.zIndex = '10080';
                    });
                    
                    // Focus on first input for better UX
                    const firstInput = this.querySelector('input[type="text"], textarea');
                    if (firstInput) {
                        setTimeout(() => {
                            firstInput.focus();
                            firstInput.click();
                        }, 200);
                    }
                });
                
                // Handle modal hide event
                modal.addEventListener('hide.bs.modal', function(e) {
                    // Clean up backdrop completely
                    setTimeout(() => {
                        const backdrops = document.querySelectorAll('.modal-backdrop');
                        backdrops.forEach(backdrop => backdrop.remove());
                    }, 100);
                });
            });
            
            // Additional debugging and failsafe measures
            document.addEventListener('click', function(e) {
                console.log('Clicked element:', e.target);
                console.log('Element z-index:', window.getComputedStyle(e.target).zIndex);
                
                // If clicking on backdrop, don't prevent modal interaction
                if (e.target.classList.contains('modal-backdrop')) {
                    e.stopPropagation();
                    return false;
                }
            });
            
            // Force remove any problematic backdrop on page load
            setTimeout(() => {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => {
                    backdrop.style.pointerEvents = 'none';
                    backdrop.style.zIndex = '10040';
                });
            }, 1000);
        });
    </script>
</body>
</html>