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
$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : null;

// Verify patient has had appointments with this doctor
$appointment_check_query = "SELECT COUNT(*) as count FROM appointments 
                           WHERE (patient_id = ? OR patient_email = (SELECT email FROM users WHERE id = ?)) 
                           AND doctor_id = ? 
                           AND status IN ('completed', 'confirmed')
                           AND appointment_date <= CURDATE()";
$appointment_check_stmt = $conn->prepare($appointment_check_query);
$appointment_check_stmt->bind_param("iii", $patient_id, $patient_id, $doctor_id);
$appointment_check_stmt->execute();
$appointment_check_result = $appointment_check_stmt->get_result();
$appointment_check = $appointment_check_result->fetch_assoc();

if ($appointment_check['count'] == 0) {
    $_SESSION['error'] = "You can only review doctors after completing an appointment with them.";
    header("Location: ../doctor_reviews.php?doctor_id=" . $doctor_id);
    exit();
}

// Verify doctor exists
$doctor_query = "SELECT id, name, specialty, image FROM doctors WHERE id = ?";
$doctor_stmt = $conn->prepare($doctor_query);
$doctor_stmt->bind_param("i", $doctor_id);
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();
$doctor = $doctor_result->fetch_assoc();

if (!$doctor) {
    $_SESSION['error'] = "Doctor not found.";
    header("Location: dashboard.php");
    exit();
}

// Check if user has already reviewed this doctor
$existing_review_query = "SELECT id FROM doctor_reviews WHERE doctor_id = ? AND patient_id = ?";
if ($appointment_id) {
    $existing_review_query .= " AND appointment_id = ?";
    $existing_stmt = $conn->prepare($existing_review_query);
    $existing_stmt->bind_param("iii", $doctor_id, $patient_id, $appointment_id);
} else {
    $existing_stmt = $conn->prepare($existing_review_query);
    $existing_stmt->bind_param("ii", $doctor_id, $patient_id);
}
$existing_stmt->execute();
$existing_result = $existing_stmt->get_result();

if ($existing_result->num_rows > 0) {
    $_SESSION['error'] = "You have already reviewed this doctor.";
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating']);
    $review_title = trim($_POST['review_title']);
    $review_text = trim($_POST['review_text']);
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    // Get patient info
    $patient_query = "SELECT name, email FROM users WHERE id = ?";
    $patient_stmt = $conn->prepare($patient_query);
    $patient_stmt->bind_param("i", $patient_id);
    $patient_stmt->execute();
    $patient_result = $patient_stmt->get_result();
    $patient = $patient_result->fetch_assoc();
    
    $patient_name = $patient['name'];
    $patient_email = $patient['email'];
    
    // Validate input
    if ($rating < 1 || $rating > 5) {
        $error = "Please select a valid rating.";
    } elseif (empty($review_title)) {
        $error = "Please provide a review title.";
    } elseif (empty($review_text)) {
        $error = "Please provide a review comment.";
    } else {
        // Insert review
        $insert_query = "INSERT INTO doctor_reviews (doctor_id, patient_id, patient_name, patient_email, appointment_id, rating, review_title, review_text, is_anonymous) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("iisssissi", $doctor_id, $patient_id, $patient_name, $patient_email, $appointment_id, $rating, $review_title, $review_text, $is_anonymous);
        
        if ($insert_stmt->execute()) {
            $_SESSION['success'] = "🎉 Your review has been submitted successfully! Thank you for your feedback.";
            header("Location: my_appointments.php?review_success=1");
            exit();
        } else {
            $error = "❌ Failed to submit review. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Doctor - Dental Clinic</title>
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
        }
        
        .rating-stars {
            font-size: 2rem;
            color: #ddd;
            margin: 1rem 0;
        }
        
        .rating-stars .star {
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .rating-stars .star:hover,
        .rating-stars .star.active {
            color: #ffd700;
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
        
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            padding: 12px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.95);
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .doctor-info {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        h1, h2, h3 {
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .text-white {
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .form-check-label {
            color: white;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="glass-container">
                    <div class="text-center mb-4">
                        <h1><i class="fas fa-star-half-alt me-2"></i>Rate Doctor</h1>
                        <p class="text-white mb-0">Share your experience to help other patients</p>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="background: linear-gradient(135deg, #ef4444, #dc2626); border: none; border-radius: 12px; color: white;">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Doctor Information -->
                    <div class="doctor-info">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <?php 
                                $doctor_image = !empty($doctor['image']) ? '../' . $doctor['image'] : 'https://img.freepik.com/free-photo/woman-doctor-wearing-lab-coat-with-stethoscope-isolated_1303-29791.jpg';
                                
                                // 检查图片文件是否存在
                                if (!empty($doctor['image']) && !filter_var($doctor['image'], FILTER_VALIDATE_URL)) {
                                    if (!file_exists('../' . $doctor['image'])) {
                                        $doctor_image = 'https://img.freepik.com/free-photo/woman-doctor-wearing-lab-coat-with-stethoscope-isolated_1303-29791.jpg';
                                    }
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($doctor_image); ?>" 
                                     alt="Dr. <?php echo htmlspecialchars($doctor['name']); ?>"
                                     style="width: 80px; height: 80px; object-fit: cover; border-radius: 50%; border: 3px solid rgba(255,255,255,0.3);"
                                     onerror="this.src='https://img.freepik.com/free-photo/woman-doctor-wearing-lab-coat-with-stethoscope-isolated_1303-29791.jpg'">
                            </div>
                            <div class="col-md-8">
                                <h3 class="mb-1">Dr. <?php echo htmlspecialchars($doctor['name']); ?></h3>
                                <p class="text-white mb-1">
                                    <i class="fas fa-stethoscope me-2"></i>
                                    <?php echo htmlspecialchars($doctor['specialty']); ?>
                                </p>
                            </div>
                            <div class="col-md-2 text-end">
                                <i class="fas fa-star fa-3x text-warning opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Review Form -->
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="rating" class="form-label text-white">
                                    <i class="fas fa-star me-2"></i>Overall Rating
                                </label>
                                <div class="rating-stars" id="rating-stars">
                                    <span class="star" data-rating="1"><i class="fas fa-star"></i></span>
                                    <span class="star" data-rating="2"><i class="fas fa-star"></i></span>
                                    <span class="star" data-rating="3"><i class="fas fa-star"></i></span>
                                    <span class="star" data-rating="4"><i class="fas fa-star"></i></span>
                                    <span class="star" data-rating="5"><i class="fas fa-star"></i></span>
                                </div>
                                <input type="hidden" name="rating" id="rating" value="0" required>
                                <div class="text-white small" id="rating-text">Please select a rating</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="review_title" class="form-label text-white">
                                    <i class="fas fa-heading me-2"></i>Review Title
                                </label>
                                <input type="text" class="form-control" id="review_title" name="review_title" 
                                       placeholder="Brief summary of your experience" required maxlength="200">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="review_text" class="form-label text-white">
                                <i class="fas fa-comment me-2"></i>Your Review
                            </label>
                            <textarea class="form-control" id="review_text" name="review_text" rows="5" 
                                      placeholder="Share your detailed experience with this doctor..." required></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_anonymous" name="is_anonymous">
                                <label class="form-check-label" for="is_anonymous">
                                    <i class="fas fa-user-secret me-2"></i>Post this review anonymously
                                </label>
                            </div>
                            <small class="text-white opacity-75">
                                <i class="fas fa-info-circle me-1"></i>
                                If checked, your name will not be displayed with the review
                            </small>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="my_appointments.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Submit Review
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Rating system
        const stars = document.querySelectorAll('.star');
        const ratingInput = document.getElementById('rating');
        const ratingText = document.getElementById('rating-text');
        
        const ratingTexts = {
            1: 'Poor - Very unsatisfied',
            2: 'Fair - Below expectations',
            3: 'Good - Met expectations',
            4: 'Very Good - Exceeded expectations',
            5: 'Excellent - Outstanding experience'
        };
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.dataset.rating);
                ratingInput.value = rating;
                ratingText.textContent = ratingTexts[rating];
                
                // Update star display
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
            
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.dataset.rating);
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.style.color = '#ffd700';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });
        });
        
        document.getElementById('rating-stars').addEventListener('mouseleave', function() {
            const currentRating = parseInt(ratingInput.value);
            stars.forEach((s, index) => {
                if (index < currentRating) {
                    s.style.color = '#ffd700';
                    s.classList.add('active');
                } else {
                    s.style.color = '#ddd';
                    s.classList.remove('active');
                }
            });
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const rating = parseInt(ratingInput.value);
            if (rating === 0) {
                e.preventDefault();
                alert('Please select a rating before submitting your review.');
                return false;
            }
        });
    </script>
</body>
</html>