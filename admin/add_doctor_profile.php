<?php
session_start();
include '../includes/db.php';

// 检查是否为管理员
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}


// 查詢所有 role=doctor 且未建立 profile 的 user
$available_doctors = [];
$sql = "SELECT u.id, u.name, u.email FROM users u LEFT JOIN doctors d ON u.id = d.user_id WHERE u.role = 'doctor' AND d.user_id IS NULL";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $available_doctors[] = $row;
    }
}

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $specialty = trim($_POST['specialty']);
    $bio = trim($_POST['bio']);
    $experience = trim($_POST['experience']);
    $location = trim($_POST['location']);
    $department = trim($_POST['department']);
    $user_id = trim($_POST['user_id']);
    $image = "";

    // 确保所有字段都填写，包括照片
    if (empty($name) || empty($specialty) || empty($bio) || empty($experience) || empty($location) || empty($department) || empty($user_id) || empty($_FILES["image"]["name"])) {
        echo "<script>alert('All fields including profile image are required!');</script>";
    } else {
        // 处理文件上传（現在是必填）
        $image_path = null; // 用于存储到数据库的路径
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
        if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            echo "<script>alert('Failed to upload image.');</script>";
            exit;
        }
        
        // 存储到数据库的路径应该是 uploads/doctors/xxx.jpg 格式
        $image_path = "uploads/doctors/" . $timestamped_filename;

        // 插入医生信息到数据库
        $stmt = $conn->prepare("INSERT INTO doctors (user_id, name, image, specialty, bio, experience, location, department) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $user_id, $name, $image_path, $specialty, $bio, $experience, $location, $department);

        if ($stmt->execute()) {
            echo "<script>alert('Doctor profile added successfully!'); window.location.href='manage_doctors.php';</script>";
        } else {
            echo "<script>alert('Failed to add doctor profile.');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Doctor Profile - Dental Clinic Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --clinic-primary: #0ea5e9;
            --clinic-secondary: #22d3ee;
            --clinic-accent: #06b6d4;
            --clinic-green: #10b981;
            --clinic-orange: #f59e0b;
            --clinic-red: #ef4444;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --text-light: #94a3b8;
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --border-light: #e2e8f0;
            --shadow-soft: 0 4px 20px rgba(15, 23, 42, 0.08);
            --shadow-medium: 0 8px 30px rgba(15, 23, 42, 0.12);
        }

        body {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f0fdfa 100%);
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            color: var(--text-primary);
        }

        .main-container {
            padding-top: 90px;
            padding-bottom: 40px;
        }

        /* Clinic-themed header */
        .page-header {
            background: var(--bg-secondary);
            border-radius: 24px;
            padding: 35px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-medium);
            position: relative;
            border: 1px solid var(--border-light);
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--clinic-primary), var(--clinic-secondary), var(--clinic-green));
            border-radius: 24px 24px 0 0;
        }

        .page-header::after {
            content: '👨‍⚕️';
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 3rem;
            opacity: 0.1;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0;
        }

        /* Form container */
        .form-container {
            background: var(--bg-secondary);
            border-radius: 24px;
            padding: 35px;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-light);
            position: relative;
        }

        .form-container::before {
            content: '📝';
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 1.8rem;
            opacity: 0.2;
        }

        /* Form styling */
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-control, .form-select {
            border: 2px solid var(--border-light);
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--bg-primary);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--clinic-primary);
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.15);
            background: var(--bg-secondary);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--text-light);
        }

        /* File input styling */
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            background: var(--bg-primary);
            border: 2px dashed var(--border-light);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-secondary);
            position: relative;
        }

        .file-input-label:hover {
            border-color: var(--clinic-primary);
            background: rgba(14, 165, 233, 0.05);
            color: var(--clinic-primary);
        }

        /* 錯誤狀態樣式 */
        .file-input-label.error {
            border-color: var(--clinic-red) !important;
            background: rgba(239, 68, 68, 0.1) !important;
            color: var(--clinic-red) !important;
            animation: shake 0.5s ease-in-out;
        }

        .file-input-label.error::after {
            content: '⚠️ Required!';
            position: absolute;
            top: -8px;
            right: 10px;
            background: var(--clinic-red);
            color: white;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            animation: pulse 1s infinite;
        }

        /* 搖晃動畫 */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* 脈衝動畫 */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* 成功狀態樣式 */
        .file-input-label.success {
            border-color: var(--clinic-green) !important;
            background: rgba(16, 185, 129, 0.05) !important;
            color: var(--clinic-green) !important;
        }

        /* Button styling */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--clinic-green), #059669);
            color: white;
            border: none;
            border-radius: 16px;
            padding: 16px 32px;
            font-weight: 600;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        .btn-secondary-custom {
            background: var(--bg-primary);
            color: var(--text-secondary);
            border: 2px solid var(--border-light);
            border-radius: 16px;
            padding: 16px 32px;
            font-weight: 600;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-secondary-custom:hover {
            background: var(--bg-secondary);
            border-color: var(--clinic-primary);
            color: var(--clinic-primary);
            transform: translateY(-2px);
            text-decoration: none;
        }

        /* Alert styling */
        .help-text {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(248, 113, 113, 0.05));
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 12px;
            padding: 12px 16px;
            margin-top: 8px;
            color: var(--clinic-red);
            font-size: 0.9rem;
            display: flex;
            align-items: start;
            gap: 8px;
        }

        /* Form sections */
        .form-section {
            margin-bottom: 35px;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--clinic-secondary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .main-container {
                padding-top: 80px;
                padding-left: 10px;
                padding-right: 10px;
            }

            .page-header, .form-container {
                padding: 25px 20px;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .btn-primary-custom, .btn-secondary-custom {
                width: 100%;
                justify-content: center;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="bi bi-person-plus-fill"></i>
                Add New Dental Professional
            </h1>
            <p class="page-subtitle">🦷 Register a new dental professional to your clinic team</p>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <!-- 重要提示框 -->
            <div class="alert alert-info border-0 rounded-4 mb-4" style="background: linear-gradient(135deg, rgba(14, 165, 233, 0.1), rgba(34, 211, 238, 0.05)); border-left: 5px solid var(--clinic-primary) !important;">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-info-circle-fill fs-4 text-primary mt-1"></i>
                    <div>
                        <h5 class="alert-heading mb-2 text-primary">
                            <strong>📋 Important Notice</strong>
                        </h5>
                        <p class="mb-2">
                            <strong>🔴 Please ensure all required fields are completed before submitting the doctor profile!</strong>
                        </p>
                        <div class="small text-muted">
                            <i class="bi bi-check-circle text-success"></i> All fields are required<br>
                            <i class="bi bi-image text-warning"></i> Special attention: Profile image is mandatory<br>
                            <i class="bi bi-person-badge text-info"></i> Please select the correct user ID
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <!-- Basic Information Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="bi bi-person-badge text-primary"></i>
                        👤 Basic Information
                    </h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="bi bi-person text-primary"></i>
                                Doctor Name
                            </label>
                            <input type="text" name="name" class="form-control" placeholder="Enter full name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="bi bi-star text-warning"></i>
                                Specialty
                            </label>
                            <input type="text" name="specialty" class="form-control" placeholder="e.g., General Dentistry, Orthodontics" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="bi bi-calendar text-primary"></i>
                                Experience (Years)
                            </label>
                            <input type="number" name="experience" class="form-control" placeholder="Years of experience" min="0" max="50" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="bi bi-building text-primary"></i>
                                Department
                            </label>
                            <input type="text" name="department" class="form-control" placeholder="e.g., Oral Surgery, Pediatric Dentistry" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-geo-alt text-danger"></i>
                            Location
                        </label>
                        <input type="text" name="location" class="form-control" placeholder="Clinic location or working area" required>
                    </div>
                </div>

                <!-- Profile Details Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="bi bi-file-text text-primary"></i>
                        📄 Profile Details
                    </h3>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-image text-success"></i>
                            Profile Image
                        </label>
                        <div class="file-input-wrapper">
                            <input type="file" name="image" id="profileImage" accept="image/*" required>
                            <label for="profileImage" class="file-input-label">
                                <i class="bi bi-cloud-upload fs-4"></i>
                                <span>Choose profile image (JPG, JPEG, PNG)</span>
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-journal-text text-primary"></i>
                            Professional Bio
                        </label>
                        <textarea name="bio" class="form-control" rows="5" placeholder="Write a brief professional biography, qualifications, and areas of expertise..." required></textarea>
                    </div>
                </div>

                <!-- System Information Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="bi bi-gear text-primary"></i>
                        ⚙️ System Information
                    </h3>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-hash text-warning"></i>
                            Doctor User ID
                        </label>
                        <select name="user_id" class="form-select" required>
                            <option value="">-- Select a doctor user --</option>
                            <?php foreach ($available_doctors as $doc): ?>
                                <option value="<?= htmlspecialchars($doc['id']) ?>">
                                    ID: <?= htmlspecialchars($doc['id']) ?> | <?= htmlspecialchars($doc['name']) ?> (<?= htmlspecialchars($doc['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>
                            <strong>Important:</strong> Please check the Manage Users page's ID column and enter the correct user ID here to ensure proper system connectivity.
                            <br>
                            <strong>Only users with role 'doctor' and no profile are shown.</strong> If no options, all doctors have profiles.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-3 justify-content-end flex-wrap">
                    <a href="manage_doctors.php" class="btn-secondary-custom">
                        <i class="bi bi-x-circle"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary-custom">
                        <i class="bi bi-check-circle-fill"></i>
                        Add Doctor Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File input enhancement
        document.getElementById('profileImage').addEventListener('change', function(e) {
            const label = document.querySelector('.file-input-label span');
            const labelElement = document.querySelector('.file-input-label');
            const fileName = e.target.files[0]?.name;
            
            if (fileName) {
                label.textContent = `✅ Selected: ${fileName}`;
                labelElement.classList.remove('error');
                labelElement.classList.add('success');
            } else {
                label.textContent = 'Choose profile image (JPG, JPEG, PNG)';
                labelElement.classList.remove('error', 'success');
            }
        });

        // 重置檔案選擇的視覺狀態
        function resetFileInputStyle() {
            const labelElement = document.querySelector('.file-input-label');
            const label = document.querySelector('.file-input-label span');
            
            labelElement.classList.remove('error', 'success');
            label.textContent = 'Choose profile image (JPG, JPEG, PNG)';
        }

        // 設置錯誤狀態
        function setFileInputError() {
            const labelElement = document.querySelector('.file-input-label');
            const label = document.querySelector('.file-input-label span');
            
            labelElement.classList.add('error');
            labelElement.classList.remove('success');
            label.textContent = '❌ Please select a profile image!';
        }

        // Form validation enhancement - 使用DOMContentLoaded確保頁面完全載入
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    console.log('Form submit triggered'); // Debug log
                    
                    const imageInput = document.getElementById('profileImage');
                    const nameInput = document.querySelector('input[name="name"]');
                    const specialtyInput = document.querySelector('input[name="specialty"]');
                    const bioInput = document.querySelector('textarea[name="bio"]');
                    const experienceInput = document.querySelector('input[name="experience"]');
                    const locationInput = document.querySelector('input[name="location"]');
                    const departmentInput = document.querySelector('input[name="department"]');
                    const userIdSelect = document.querySelector('select[name="user_id"]');
                    
                    let isValid = true;
                    let missingFields = [];
                    
                    // 檢查照片
                    if (!imageInput.files || imageInput.files.length === 0) {
                        console.log('No image selected'); // Debug log
                        setFileInputError(); // 使用新的錯誤設置函數
                        missingFields.push('Profile Image');
                        isValid = false;
                    } else {
                        // 照片已選擇，移除錯誤狀態
                        const labelElement = document.querySelector('.file-input-label');
                        labelElement.classList.remove('error');
                        labelElement.classList.add('success');
                    }
                    
                    // 檢查其他必填欄位
                    const fieldsToCheck = [
                        {input: nameInput, label: 'Doctor Name'},
                        {input: specialtyInput, label: 'Specialty'},
                        {input: experienceInput, label: 'Experience'},
                        {input: departmentInput, label: 'Department'},
                        {input: locationInput, label: 'Location'},
                        {input: bioInput, label: 'Professional Bio'},
                        {input: userIdSelect, label: 'Doctor User ID'}
                    ];
                    
                    fieldsToCheck.forEach(field => {
                        if (!field.input || !field.input.value.trim()) {
                            console.log('Missing field:', field.label); // Debug log
                            if (field.input) {
                                field.input.style.borderColor = 'var(--clinic-red)';
                                field.input.style.background = 'rgba(239, 68, 68, 0.05)';
                            }
                            missingFields.push(field.label);
                            isValid = false;
                        } else {
                            if (field.input) {
                                field.input.style.borderColor = 'var(--border-light)';
                                field.input.style.background = 'var(--bg-primary)';
                            }
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault(); // 阻止表單提交
                        e.stopPropagation(); // 阻止事件冒泡
                        
                        console.log('Form validation failed, missing fields:', missingFields); // Debug log
                        
                        let errorMessage = '⚠️ Please complete the following required fields:\n\n';
                        missingFields.forEach((field, index) => {
                            errorMessage += `${index + 1}. ${field}\n`;
                        });
                        errorMessage += '\n🚨 All fields including the profile image are mandatory!';
                        
                        alert(errorMessage);
                        
                        // 滾動到第一個錯誤欄位
                        if (missingFields.includes('Profile Image')) {
                            document.querySelector('.file-input-label').scrollIntoView({ 
                                behavior: 'smooth', 
                                block: 'center' 
                            });
                        } else {
                            const firstErrorField = fieldsToCheck.find(field => 
                                missingFields.includes(field.label)
                            );
                            if (firstErrorField && firstErrorField.input) {
                                firstErrorField.input.scrollIntoView({ 
                                    behavior: 'smooth', 
                                    block: 'center' 
                                });
                                firstErrorField.input.focus();
                            }
                        }
                        
                        return false; // 確保不會提交
                    }
                    
                    console.log('Form validation passed'); // Debug log
                    return true;
                });
            }
        });
    </script>
</body>
</html>
