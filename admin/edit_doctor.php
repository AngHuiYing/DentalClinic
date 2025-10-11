<?php
session_start();
include '../includes/db.php';

// 确保管理员已登录
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 获取医生 ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_doctors.php");
    exit;
}

$doctor_id = $_GET['id'];

// 获取医生数据
$stmt = $conn->prepare("SELECT * FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: manage_doctors.php");
    exit;
}

$doctor = $result->fetch_assoc();

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $specialty = trim($_POST['specialty']);
    $bio = trim($_POST['bio']);
    $experience = trim($_POST['experience']);
    $location = trim($_POST['location']);
    $department = trim($_POST['department']);
    $user_id = trim($_POST['user_id']);
    $image = $doctor['image']; // 旧头像

    // 确保所有字段都填写
    if (empty($name) || empty($specialty) || empty($bio) || empty($experience) || empty($location) || empty($department) || empty($user_id)) {
        echo "<script>alert('All fields are required!');</script>";
    } else {
        // 处理文件上传
        if (!empty($_FILES["image"]["name"])) {
            $target_dir = "../uploads/doctors/";
            $image_filename = basename($_FILES["image"]["name"]);
            $timestamped_filename = time() . "_" . $image_filename; // 避免重复文件名
            $target_file = $target_dir . $timestamped_filename;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            // 允许的文件格式
            $allowed_types = array("jpg", "jpeg", "png");
            if (!in_array($imageFileType, $allowed_types)) {
                echo "<script>alert('Only JPG, JPEG, PNG files are allowed.');</script>";
                exit;
            }

            // 移动上传的文件
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                // 删除旧头像（如果存在）
                if (!empty($doctor['image']) && file_exists("../" . $doctor['image'])) {
                    unlink("../" . $doctor['image']);
                }
                // 存储到数据库的路径应该是 uploads/doctors/xxx.jpg 格式
                $image = "uploads/doctors/" . $timestamped_filename;
            } else {
                echo "<script>alert('Failed to upload image.');</script>";
                exit;
            }
        }

        // 更新医生信息
        $stmt = $conn->prepare("UPDATE doctors SET user_id=?, name=?, image=?, specialty=?, bio=?, experience=?, location=?, department=? WHERE id=?");
        $stmt->bind_param("isssssssi", $user_id, $name, $image, $specialty, $bio, $experience, $location, $department, $doctor_id);

        if ($stmt->execute()) {
            echo "<script>alert('Doctor profile updated successfully!'); window.location.href='manage_doctors.php';</script>";
        } else {
            echo "<script>alert('Failed to update doctor profile.');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Doctor Profile - Green Life Dental Clinic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #10b981;
            --accent-color: #f59e0b;
            --danger-color: #ef4444;
            --success-color: #22c55e;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            background: var(--light-bg);
            margin: 95px 20px 20px 20px;
            border-radius: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 1;
        }

        .header-section {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #1e40af 100%);
            color: white;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            transform: translate(100px, -100px);
        }

        .page-title {
            position: relative;
            z-index: 2;
        }

        .breadcrumb-custom {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            padding: 0.75rem 1.5rem;
            margin-top: 1rem;
        }

        .breadcrumb-custom a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }

        .breadcrumb-custom a:hover {
            color: white;
        }

        .form-section {
            padding: 2rem;
        }

        .form-card {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 1.5rem 1.5rem 0 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control, .form-select {
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .form-control:hover, .form-select:hover {
            border-color: #d1d5db;
        }

        .image-preview-section {
            background: #f9fafb;
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            border: 2px dashed #d1d5db;
            transition: all 0.3s ease;
        }

        .image-preview-section:hover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .current-image {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 1rem;
            border: 4px solid white;
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .current-image:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 35px -5px rgba(0, 0, 0, 0.2);
        }

        .no-image-placeholder {
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, #e5e7eb, #f3f4f6);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            border: 4px solid white;
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .file-upload-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, var(--info-color), var(--primary-color));
            color: white;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .file-upload-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(59, 130, 246, 0.4);
        }

        .btn-group-custom {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }

        .btn-custom {
            padding: 0.75rem 2rem;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(37, 99, 235, 0.4);
            color: white;
        }

        .btn-secondary-custom {
            background: linear-gradient(135deg, #6b7280, #9ca3af);
            color: white;
        }

        .btn-secondary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(107, 114, 128, 0.4);
            color: white;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-full-width {
            grid-column: 1 / -1;
        }

        /* Enhanced Responsive Design */
        @media (max-width: 1200px) {
            .main-container {
                margin: 95px 15px 15px 15px;
            }
        }

        @media (max-width: 992px) {
            .main-container {
                margin: 90px 12px 12px 12px;
                border-radius: 1.5rem;
            }
            
            .header-section {
                padding: 1.8rem;
            }
            
            .form-section {
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                margin: 90px 8px 8px 8px;
                border-radius: 1rem;
            }
            
            .header-section {
                padding: 1.2rem;
                text-align: center;
            }
            
            .form-section {
                padding: 1rem;
            }
            
            .form-card {
                padding: 1.5rem;
                border-radius: 1rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .btn-group-custom {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .current-image {
                width: 150px;
                height: 150px;
            }
            
            .no-image-placeholder {
                width: 150px;
                height: 150px;
            }
        }

        @media (max-width: 576px) {
            .main-container {
                margin: 85px 5px 5px 5px;
                border-radius: 0.8rem;
            }
            
            .header-section {
                padding: 1rem;
            }
            
            .form-section {
                padding: 0.8rem;
            }
            
            .form-card {
                padding: 1rem;
                border-radius: 0.8rem;
            }
            
            .current-image, .no-image-placeholder {
                width: 120px;
                height: 120px;
            }
            
            .btn-custom {
                padding: 0.6rem 1.5rem;
                font-size: 0.9rem;
            }
            
            .page-title h1 {
                font-size: 1.5rem;
            }
        }

        /* Loading animation */
        .loading {
            position: relative;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="main-container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="page-title text-white" style="font-family: 'Segoe UI', 'Inter', Arial, sans-serif;">
            <div class="d-flex align-items-center mb-3" style="gap: 1rem;">
                <i class="bi bi-person-fill-gear" style="font-size: 2.8rem; color: #fff; text-shadow: 0 2px 8px rgba(0,0,0,0.12);"></i>
                <div>
                    <h1 class="mb-1 fw-bold" style="font-size: 2.3rem; color: #fff; letter-spacing: 0.5px; text-shadow: 0 2px 8px rgba(0,0,0,0.12); font-family: 'Segoe UI', 'Inter', Arial, sans-serif;">Edit Doctor Profile</h1>
                    <div class="mb-0" style="font-size: 1.08rem; color: #f3f4f6; opacity: 0.95; font-weight: 500;">Update doctor information and profile details</div>
                </div>
            </div>
            <nav class="breadcrumb-custom" style="background: rgba(255,255,255,0.10); border-radius: 1rem; padding: 0.75rem 1.5rem; margin-top: 1rem;">
                <ol class="breadcrumb mb-0" style="background: transparent; padding: 0; margin: 0; font-size: 1.08rem;">
                    <li class="breadcrumb-item">
                        <a href="dashboard.php" style="color: #e0e7ef; font-weight: 500; text-decoration: none; transition: color 0.2s;">Dashboard</a>
                    </li>
                    <li class="breadcrumb-separator" style="color: #e0e7ef; margin: 0 0.5rem; font-weight: 600;">/</li>
                    <li class="breadcrumb-item">
                        <a href="manage_doctors.php" style="color: #e0e7ef; font-weight: 500; text-decoration: none; transition: color 0.2s;">Manage Doctors</a>
                    </li>
                    <li class="breadcrumb-separator" style="color: #e0e7ef; margin: 0 0.5rem; font-weight: 600;">/</li>
                    <li class="breadcrumb-item active" style="color: #fff; font-weight: 600;">Edit Doctor</li>
                </ol>
                <style>
                    .breadcrumb-item a:hover {
                        color: #fff !important;
                        text-decoration: underline;
                    }
                    .breadcrumb-separator {
                        user-select: none;
                    }
                </style>
            </nav>
        </div>
    </div>

    <!-- Form Section -->
    <div class="form-section">
        <div class="form-card">
            <form method="POST" enctype="multipart/form-data" id="editDoctorForm">
                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="bi bi-person me-2"></i>Doctor Name
                                </label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($doctor['name']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="bi bi-award me-2"></i>Specialty
                                </label>
                                <input type="text" name="specialty" class="form-control" value="<?= htmlspecialchars($doctor['specialty']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="bi bi-calendar me-2"></i>Experience (Years)
                                </label>
                                <input type="number" name="experience" class="form-control" value="<?= htmlspecialchars($doctor['experience']) ?>" required min="0">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="bi bi-geo-alt me-2"></i>Location
                                </label>
                                <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($doctor['location']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="bi bi-building me-2"></i>Department
                                </label>
                                <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($doctor['department']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="bi bi-hash me-2"></i>Doctor User ID
                                </label>
                                <input type="number" name="user_id" class="form-control" value="<?= htmlspecialchars($doctor['user_id']) ?>" required min="1">
                            </div>
                        </div>

                        <div class="form-group form-full-width">
                            <label class="form-label">
                                <i class="bi bi-file-text me-2"></i>Biography
                            </label>
                            <textarea name="bio" class="form-control" rows="4" required><?= htmlspecialchars($doctor['bio']) ?></textarea>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-image me-2"></i>Profile Image
                            </label>
                            <div class="image-preview-section">
                                <?php if (!empty($doctor['image'])): ?>
                                    <img src="../<?= $doctor['image'] ?>" alt="Doctor Image" class="current-image mb-3">
                                <?php else: ?>
                                    <div class="no-image-placeholder mb-3">
                                        <i class="bi bi-person-circle" style="font-size: 4rem; color: #9ca3af;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <p class="mb-2 text-muted">Current profile image</p>
                                
                                <div class="file-upload-wrapper">
                                    <input type="file" name="image" class="file-upload-input" accept="image/*" id="imageInput">
                                    <label for="imageInput" class="file-upload-label">
                                        <i class="bi bi-camera"></i>
                                        Change Image
                                    </label>
                                </div>
                                
                                <small class="text-muted mt-2 d-block">
                                    Supported formats: JPG, JPEG, PNG<br>
                                    Maximum size: 5MB
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="btn-group-custom">
                    <a href="manage_doctors.php" class="btn-custom btn-secondary-custom">
                        <i class="bi bi-arrow-left"></i>Cancel
                    </a>
                    <button type="submit" class="btn-custom btn-primary-custom">
                        <i class="bi bi-check-lg"></i>Update Doctor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('editDoctorForm');
        const imageInput = document.getElementById('imageInput');
        const submitBtn = form.querySelector('button[type="submit"]');

        // Image preview functionality
        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const currentImage = document.querySelector('.current-image');
                    const placeholder = document.querySelector('.no-image-placeholder');
                    
                    if (currentImage) {
                        currentImage.src = e.target.result;
                    } else if (placeholder) {
                        placeholder.outerHTML = `<img src="${e.target.result}" alt="Preview" class="current-image mb-3">`;
                    }
                };
                reader.readAsDataURL(file);
            }
        });

        // Form validation
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return;
            }

            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        });

        // Add input animations
        const inputs = form.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.querySelector('.form-label').style.color = 'var(--primary-color)';
            });

            input.addEventListener('blur', function() {
                this.parentElement.querySelector('.form-label').style.color = '#374151';
            });
        });

        // Add invalid feedback styles
        const style = document.createElement('style');
        style.textContent = `
            .form-control.is-invalid {
                border-color: var(--danger-color) !important;
                box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
            }
        `;
        document.head.appendChild(style);
    });
</script>
</body>
</html>
