<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['user_name'] ?? 'Doctor';

// 获取医生信息
$sql = "SELECT d.id, u.name, u.email, d.image, d.specialty, d.bio, d.experience, d.location, d.department
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    die("Doctor record not found!");
}

$success_message = '';
$error_message = '';

// 处理医生资料更新
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $specialty = $_POST['specialty'];
    $bio = $_POST['bio'];
    $experience = $_POST['experience'];
    $location = $_POST['location'];
    $department = $_POST['department'];

    // **头像上传**
    $image_updated = false;
    if (!empty($_FILES['image']['name'])) {
        // Check if uploads directory exists
        $upload_dir = "../uploads/doctors/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $allowed_types)) {
            $image_name = time() . "_" . basename($_FILES["image"]["name"]);
            $image_path = $upload_dir . $image_name;
            
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $image_path)) {
                // 更新数据库中的头像
                $sql = "UPDATE doctors SET image = ? WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $image_name, $user_id);
                $stmt->execute();
                $image_updated = true;
            } else {
                $error_message = "Failed to upload image.";
            }
        } else {
            $error_message = "Invalid file type. Please upload JPG, JPEG, PNG, or GIF files only.";
        }
    }

    if (empty($error_message)) {
        // 更新 users 表中的姓名和邮箱
        $sql = "UPDATE users SET name = ?, email = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $name, $email, $user_id);
        $stmt->execute();

        // 更新 doctors 表的其余信息
        $sql = "UPDATE doctors SET specialty = ?, bio = ?, experience = ?, location = ?, department = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $specialty, $bio, $experience, $location, $department, $user_id);
        $stmt->execute();

        $success_message = "Profile updated successfully!";
        
        // Refresh doctor data
        $stmt = $conn->prepare("SELECT d.id, u.name, u.email, d.image, d.specialty, d.bio, d.experience, d.location, d.department
                FROM doctors d 
                JOIN users u ON d.user_id = u.id 
                WHERE u.id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $doctor = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Profile - Doctor Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Medical Professional Color Palette */
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary: #059669;
            --accent: #7c3aed;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            
            /* Sophisticated Grays */
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            
            /* Professional Shadows */
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px rgba(0, 0, 0, 0.25);
            
            /* Modern Border Radius */
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-2xl: 2rem;
            --radius-full: 9999px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Navigation Bar */
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            box-shadow: var(--shadow-lg);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .navbar-brand i {
            font-size: 2rem;
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 0.75rem 1rem !important;
            border-radius: var(--radius-lg);
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }

        .dropdown-menu {
            border: none;
            box-shadow: var(--shadow-xl);
            border-radius: var(--radius-lg);
            padding: 0.5rem;
        }

        .dropdown-item {
            border-radius: var(--radius);
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: var(--gray-100);
            transform: translateX(4px);
        }

        /* Main Content */
        .main-content {
            padding: 2rem 0;
        }

        /* Header Section */
        .page-header {
            background: white;
            border-radius: var(--radius-2xl);
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .page-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--gray-600);
            font-size: 1.1rem;
            margin: 0;
        }

        /* Profile Grid */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            align-items: start;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            text-align: center;
        }

        .profile-avatar {
            position: relative;
            margin-bottom: 2rem;
        }

        .avatar-image {
            width: 10rem;
            height: 10rem;
            border-radius: var(--radius-full);
            object-fit: cover;
            border: 4px solid white;
            box-shadow: var(--shadow-lg);
            margin: 0 auto;
            display: block;
        }

        .avatar-placeholder {
            width: 10rem;
            height: 10rem;
            border-radius: var(--radius-full);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 600;
            margin: 0 auto;
            box-shadow: var(--shadow-lg);
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .profile-specialty {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        /* Edit Form */
        .edit-form {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-floating label {
            color: var(--gray-600);
        }

        .form-control,
        .form-select {
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-lg);
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--gray-50);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15);
            background: white;
        }

        .file-upload-area {
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius-lg);
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            background: var(--gray-50);
        }

        .file-upload-area:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }

        .file-upload-area.dragover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.1);
        }

        .upload-icon {
            font-size: 2rem;
            color: var(--gray-400);
            margin-bottom: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: var(--radius-lg);
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline-secondary {
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-lg);
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: var(--gray-100);
            border-color: var(--gray-400);
        }

        /* Alerts */
        .alert {
            border-radius: var(--radius-lg);
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .profile-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem 0;
            }
            
            .page-header {
                padding: 2rem 1.5rem;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .profile-card,
            .edit-form {
                padding: 1.5rem;
            }
            
            .profile-stats {
                grid-template-columns: 1fr;
            }
        }

        /* Animation */
        .fade-in {
            animation: fadeInUp 0.6s ease-out;
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
    <!-- Navigation -->
    <?php include '../includes/navbar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Header -->
            <section class="page-header fade-in">
                <h1 class="page-title">Doctor Profile</h1>
                <p class="page-subtitle">Manage your professional profile and personal information</p>
            </section>

            <!-- Success/Error Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success fade-in">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger fade-in">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Profile Grid -->
            <div class="profile-grid">
                <!-- Profile Card -->
                <div class="profile-card fade-in">
                    <div class="profile-avatar">
                        <?php if (!empty($doctor['image'])): ?>
                            <img src="../<?php echo htmlspecialchars($doctor['image']); ?>" 
                                 alt="Doctor Profile" class="avatar-image">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?php echo strtoupper(substr($doctor['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <h3 class="profile-name">Dr. <?php echo htmlspecialchars($doctor['name']); ?></h3>
                    <div class="profile-specialty"><?php echo htmlspecialchars($doctor['specialty'] ?? 'General Practice'); ?></div>
                    
                    <?php if (!empty($doctor['bio'])): ?>
                        <p class="text-muted"><?php echo htmlspecialchars($doctor['bio']); ?></p>
                    <?php endif; ?>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo htmlspecialchars($doctor['experience'] ?? '0'); ?></div>
                            <div class="stat-label">Years Experience</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo htmlspecialchars($doctor['department'] ?? 'General'); ?></div>
                            <div class="stat-label">Department</div>
                        </div>
                    </div>
                </div>

                <!-- Edit Form -->
                <div class="edit-form fade-in">
                    <h3 class="form-title">
                        <i class="fas fa-edit"></i>
                        Edit Profile
                    </h3>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Profile Image Upload -->
                        <div class="mb-4">
                            <label class="form-label">Profile Picture</label>
                            <div class="file-upload-area" id="fileUploadArea">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <p class="mb-2"><strong>Click to upload</strong> or drag and drop</p>
                                <p class="text-muted small">JPG, JPEG, PNG or GIF (MAX. 2MB)</p>
                                <input type="file" name="image" id="imageInput" class="d-none" disabled accept="image/*">
                            </div>
                        </div>

                        <div class="row g-3">
                            <!-- Basic Information -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($doctor['name']); ?>" disabled required>
                                    <label for="name">Full Name</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($doctor['email']); ?>" disabled required>
                                    <label for="email">Email Address</label>
                                </div>
                            </div>

                            <!-- Professional Information -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="specialty" name="specialty" 
                                           value="<?php echo htmlspecialchars($doctor['specialty']); ?>" disabled required>
                                    <label for="specialty">Medical Specialty</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="department" name="department" 
                                           value="<?php echo htmlspecialchars($doctor['department']); ?>" disabled required>
                                    <label for="department">Department</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="experience" name="experience" 
                                           value="<?php echo htmlspecialchars($doctor['experience']); ?>" min="0" max="50" disabled required>
                                    <label for="experience">Years of Experience</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($doctor['location']); ?>" disabled required>
                                    <label for="location">Practice Location</label>
                                </div>
                            </div>

                            <!-- Bio -->
                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" id="bio" name="bio" style="height: 120px" disabled><?php echo htmlspecialchars($doctor['bio']); ?></textarea>
                                    <label for="bio">Professional Bio</label>
                                </div>
                            </div>
                        </div>
                        <p style="color: red;">*Cannot edit profile information. If you need to make changes, please contact the admin.</p>

                        <!-- Action Buttons -->
                        <!-- <div class="d-flex gap-3 justify-content-end mt-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Reset Changes
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </div> -->
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // File upload functionality
        const fileUploadArea = document.getElementById('fileUploadArea');
        const imageInput = document.getElementById('imageInput');

        fileUploadArea.addEventListener('click', () => {
            imageInput.click();
        });

        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                imageInput.files = files;
                handleFileSelect(files[0]);
            }
        });

        imageInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        function handleFileSelect(file) {
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            const maxSize = 2 * 1024 * 1024; // 2MB

            if (!allowedTypes.includes(file.type)) {
                alert('Please select a valid image file (JPG, JPEG, PNG, or GIF).');
                imageInput.value = '';
                return;
            }

            if (file.size > maxSize) {
                alert('File size must be less than 2MB.');
                imageInput.value = '';
                return;
            }

            // Update file upload area with file name
            const uploadContent = fileUploadArea.querySelector('.upload-icon').parentElement;
            uploadContent.innerHTML = `
                <div class="upload-icon">
                    <i class="fas fa-file-image text-success"></i>
                </div>
                <p class="mb-2"><strong>${file.name}</strong></p>
                <p class="text-muted small">File ready for upload</p>
            `;
        }

        // Reset form function
        function resetForm() {
            if (confirm('Are you sure you want to reset all changes?')) {
                document.querySelector('form').reset();
                
                // Reset file upload area
                const uploadContent = fileUploadArea.querySelector('.upload-icon').parentElement;
                uploadContent.innerHTML = `
                    <div class="upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <p class="mb-2"><strong>Click to upload</strong> or drag and drop</p>
                    <p class="text-muted small">JPG, JPEG, PNG or GIF (MAX. 2MB)</p>
                `;
            }
        }

        // Add fade-in animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, observerOptions);

        // Observe all sections
        document.querySelectorAll('section, .profile-card, .edit-form').forEach(element => {
            observer.observe(element);
        });
    </script>
</body>
</html>