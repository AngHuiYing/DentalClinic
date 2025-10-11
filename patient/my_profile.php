<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

// Get patient information
$patient_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    die("Patient record not found!");
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $gender = $_POST['gender'];
    $date_of_birth = $_POST['date_of_birth'];
    
    // Basic validation (email is no longer editable)
    if (empty($name)) {
        $error = "Name is required.";
    } else {
        // Update profile (excluding email)
        $update_stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, gender = ?, date_of_birth = ? WHERE id = ?");
        $update_stmt->bind_param("ssssi", $name, $phone, $gender, $date_of_birth, $patient_id);
        
        if ($update_stmt->execute()) {
            $success = "Profile updated successfully!";
            // Refresh data
            $stmt->execute();
            $patient = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Error updating profile: " . $conn->error;
        }
    }
}

// Get appointment statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_appointments,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_appointments,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments
    FROM appointments 
    WHERE patient_email = ?
");
$stats_stmt->bind_param("s", $patient['email']);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get medical records count
$medical_stmt = $conn->prepare("SELECT COUNT(*) as medical_records FROM medical_records WHERE patient_email = ?");
$medical_stmt->bind_param("s", $patient['email']);
$medical_stmt->execute();
$medical_count = $medical_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👤 My Profile - Green Life Dental Clinic</title>
    
    <!-- Modern CSS Framework -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Modern Color Palette */
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            
            /* Glassmorphism Colors - Enhanced for better readability */
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.3);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            
            /* Text Colors - Enhanced contrast for accessibility */
            --text-primary: #1a202c;
            --text-secondary: #2d3748;
            --text-light: #4a5568;
            
            /* Background */
            --bg-primary: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
            
            /* Spacing */
            --spacing-xs: 0.5rem;
            --spacing-sm: 1rem;
            --spacing-md: 1.5rem;
            --spacing-lg: 2rem;
            --spacing-xl: 3rem;
            
            /* Border Radius */
            --radius-sm: 12px;
            --radius-md: 20px;
            --radius-lg: 30px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            min-height: 100vh;
            color: var(--text-primary);
            overflow-x: hidden;
            padding-top: 90px;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(102, 126, 234, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(240, 147, 251, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(79, 172, 254, 0.3) 0%, transparent 50%);
            animation: backgroundShift 20s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes backgroundShift {
            0%, 100% { transform: translateX(0) translateY(0); }
            25% { transform: translateX(-10px) translateY(-20px); }
            50% { transform: translateX(20px) translateY(-10px); }
            75% { transform: translateX(-20px) translateY(10px); }
        }

        /* Container System */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--spacing-md);
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 calc(var(--spacing-sm) * -0.5);
        }

        .col, .col-1, .col-2, .col-3, .col-4, .col-5, .col-6,
        .col-7, .col-8, .col-9, .col-10, .col-11, .col-12 {
            padding: 0 calc(var(--spacing-sm) * 0.5);
        }

        .col { flex: 1; }
        .col-4 { flex: 0 0 33.333333%; }
        .col-6 { flex: 0 0 50%; }
        .col-8 { flex: 0 0 66.666667%; }
        .col-12 { flex: 0 0 100%; }

        /* Glassmorphism Cards */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--glass-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            margin-bottom: var(--spacing-lg);
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        }

        .glass-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 40px 0 rgba(31, 38, 135, 0.5);
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
            padding: var(--spacing-xl) 0;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            margin: 0 var(--spacing-md) var(--spacing-xl) var(--spacing-md);
        }

        .page-title {
            font-size: 3.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4c51bf, #553c9a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: var(--spacing-sm);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            filter: drop-shadow(0 0 10px rgba(76, 81, 191, 0.3));
        }

        .page-subtitle {
            font-size: 1.2rem;
            font-weight: 500;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
            color: var(--text-primary);
            text-shadow: 0 1px 3px rgba(255, 255, 255, 0.5);
        }

        /* Profile Card */
        .profile-card {
            padding: var(--spacing-xl);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 252, 0.95));
            border: 2px solid rgba(102, 126, 234, 0.2);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
            padding-bottom: var(--spacing-lg);
            border-bottom: 2px solid rgba(102, 126, 234, 0.1);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.8);
        }

        .profile-info h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: var(--spacing-xs);
            color: var(--text-primary);
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.5);
        }

        .profile-info p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: var(--spacing-xs);
            font-weight: 500;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.3);
        }

        /* Statistics Cards */
        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            border: 2px solid rgba(102, 126, 234, 0.1);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin: 0 var(--spacing-xs) var(--spacing-md) var(--spacing-xs);
            min-height: 160px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.2);
            border-color: rgba(102, 126, 234, 0.3);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-md);
            font-size: 1.5rem;
            color: white;
            border: 3px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .stat-icon.primary { background: var(--primary-gradient); }
        .stat-icon.success { background: var(--success-gradient); }
        .stat-icon.warning { background: var(--warning-gradient); }
        .stat-icon.danger { background: var(--danger-gradient); }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: var(--spacing-xs);
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.5);
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.95rem;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.3);
        }

        /* Forms */
        .form-group {
            margin-bottom: var(--spacing-md);
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: var(--spacing-xs);
            color: var(--text-primary);
            font-size: 1rem;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.5);
        }

        .form-control {
            width: 100%;
            padding: var(--spacing-md);
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .form-control:focus {
            outline: none;
            border-color: rgba(102, 126, 234, 0.6);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.2);
            transform: translateY(-1px);
        }

        .form-control::placeholder {
            color: #6b7280;
            font-weight: 400;
        }

        /* Disabled form controls */
        .form-control:disabled {
            background: rgba(156, 163, 175, 0.2);
            border-color: rgba(156, 163, 175, 0.3);
            color: var(--text-secondary);
            cursor: not-allowed;
        }

        .form-control:disabled:hover {
            transform: none;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-xs);
            padding: var(--spacing-md) var(--spacing-lg);
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .btn-outline {
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-primary);
            border: 2px solid rgba(102, 126, 234, 0.4);
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-outline:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: rgba(102, 126, 234, 0.6);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            color: var(--text-primary);
        }

        /* Alerts */
        .alert {
            padding: var(--spacing-md);
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-md);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 2px solid;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border-color: rgba(16, 185, 129, 0.3);
            color: #064e3b;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.3);
            color: #7f1d1d;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }

        .section-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-gradient);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.5);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2.5rem;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .col-4, .col-6, .col-8 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .row {
                margin: 0;
            }
            
            .col {
                padding: 0 0 var(--spacing-sm) 0;
            }
        }

        /* Utility Classes */
        .text-center { text-align: center; }
        .mb-3 { margin-bottom: var(--spacing-md); }
        .mt-3 { margin-top: var(--spacing-md); }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.6s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Accessibility Improvements */
        .form-control:focus-visible,
        .btn:focus-visible {
            outline: 3px solid #4c51bf;
            outline-offset: 2px;
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            :root {
                --glass-bg: rgba(255, 255, 255, 0.95);
                --text-primary: #000000;
                --text-secondary: #1a1a1a;
            }
            
            .form-control {
                border-color: #000000;
                background: #ffffff;
            }
            
            .btn-outline {
                border-color: #000000;
                background: #ffffff;
                color: #000000;
            }
        }

        /* Better visual separation */
        .glass-card {
            border: 2px solid rgba(102, 126, 234, 0.2);
        }

        /* Improved readability for form labels */
        .form-label i {
            margin-right: 8px;
            color: #4c51bf;
        }

        /* Quick Actions Grid Layout */
        .quick-actions-grid .btn {
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 0.95rem;
            padding: var(--spacing-md);
        }

        .quick-actions-grid .btn i {
            margin-right: 8px;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <!-- Page Header -->
    <div class="page-header fade-in">
        <div class="container">
            <h1 class="page-title">My Profile</h1>
            <p class="page-subtitle">👤 Manage your personal information and view your medical statistics</p>
        </div>
    </div>

    <div class="container">
        <!-- Alert Messages -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Information -->
            <div class="col-6">
                <div class="glass-card profile-card fade-in">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="profile-info">
                            <h2><?= htmlspecialchars($patient['name'] ?? 'Unknown Patient') ?></h2>
                            <p><i class="fas fa-envelope me-2"></i><?= htmlspecialchars($patient['email']) ?></p>
                            <p><i class="fas fa-phone me-2"></i><?= htmlspecialchars($patient['phone'] ?? 'Not provided') ?></p>
                            <p><i class="fas fa-calendar me-2"></i>Member since <?= date('F Y', strtotime($patient['created_at'])) ?></p>
                        </div>
                    </div>

                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-edit"></i>
                        </div>
                        <h3 class="section-title">Update Profile Information</h3>
                    </div>

                    <form method="POST">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-user me-2"></i>Full Name
                                    </label>
                                    <input type="text" name="name" class="form-control" 
                                           value="<?= htmlspecialchars($patient['name'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-envelope me-2"></i>Email Address
                                    </label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($patient['email']) ?>" disabled>
                                    <small style="color: var(--text-light); font-size: 0.85rem; margin-top: 0.25rem; display: block;">
                                        <i class="fas fa-lock"></i> Email cannot be changed for security reasons
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-phone me-2"></i>Phone Number
                                    </label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-venus-mars me-2"></i>Gender
                                    </label>
                                    <select name="gender" class="form-control">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?= ($patient['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= ($patient['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                        <option value="other" <?= ($patient['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-birthday-cake me-2"></i>Date of Birth
                            </label>
                            <input type="date" name="date_of_birth" class="form-control" 
                                   value="<?= htmlspecialchars($patient['date_of_birth'] ?? '') ?>">
                        </div>

                        <div class="text-center mt-3">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i>Update Profile
                            </button>
                            <a href="dashboard.php" class="btn btn-outline">
                                <i class="fas fa-arrow-left"></i>Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics Sidebar -->
            <div class="col-6">
                <div class="glass-card fade-in">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3 class="section-title">My Statistics</h3>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="stat-card">
                                <div class="stat-icon primary">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stat-number"><?= $stats['total_appointments'] ?? 0 ?></div>
                                <div class="stat-label">Total Appointments</div>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="stat-card">
                                <div class="stat-icon success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stat-number"><?= $stats['confirmed_appointments'] ?? 0 ?></div>
                                <div class="stat-label">Confirmed Visits</div>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="stat-card">
                                <div class="stat-icon warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-number"><?= $stats['pending_appointments'] ?? 0 ?></div>
                                <div class="stat-label">Pending Appointments</div>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="stat-card">
                                <div class="stat-icon danger">
                                    <i class="fas fa-notes-medical"></i>
                                </div>
                                <div class="stat-number"><?= $medical_count['medical_records'] ?? 0 ?></div>
                                <div class="stat-label">Medical Records</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="glass-card fade-in">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <h3 class="section-title">Quick Actions</h3>
                    </div>

                    <div class="row quick-actions-grid">
                        <div class="col-6">
                            <a href="book_appointment.php" class="btn btn-primary mb-3" style="width: 100%;">
                                <i class="fas fa-calendar-plus"></i>Book Appointment
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="my_appointments.php" class="btn btn-outline mb-3" style="width: 100%;">
                                <i class="fas fa-calendar-alt"></i>My Appointments
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="patient_history.php" class="btn btn-outline mb-3" style="width: 100%;">
                                <i class="fas fa-history"></i>Medical History
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="billing.php" class="btn btn-outline" style="width: 100%;">
                                <i class="fas fa-file-invoice-dollar"></i>Billing Records
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add form validation
            const form = document.querySelector('form');
            const nameInput = document.querySelector('input[name="name"]');

            form.addEventListener('submit', function(e) {
                let isValid = true;

                // Name validation
                if (!nameInput.value.trim()) {
                    showError(nameInput, 'Name is required');
                    isValid = false;
                } else {
                    clearError(nameInput);
                }

                if (!isValid) {
                    e.preventDefault();
                }
            });

            function showError(input, message) {
                clearError(input);
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.style.color = '#e53e3e';
                errorDiv.style.fontSize = '0.875rem';
                errorDiv.style.marginTop = '0.25rem';
                errorDiv.textContent = message;
                input.parentNode.appendChild(errorDiv);
                input.style.borderColor = '#e53e3e';
            }

            function clearError(input) {
                const errorMessage = input.parentNode.querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.remove();
                }
                input.style.borderColor = 'rgba(255, 255, 255, 0.2)';
            }

            // Real-time validation
            nameInput.addEventListener('input', function() {
                if (this.value.trim()) {
                    clearError(this);
                }
            });
        });
    </script>
</body>
</html>