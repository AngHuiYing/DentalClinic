<?php
session_start();
include '../includes/db.php';

// 检查管理员是否登录
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 获取用户 ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_users.php");
    exit;
}

$user_id = $_GET['id'];
$result = $conn->query("SELECT id, name, email, role FROM users WHERE id = $user_id");
if ($result->num_rows === 0) {
    echo "<script>alert('User not found!'); window.location.href='manage_users.php';</script>";
    exit;
}
$user = $result->fetch_assoc();

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $role = $_POST['role'];

    if (!empty($name) && !empty($role)) {
        // 只更新name和role，不更新email（因为email输入框被disabled）
        $stmt = $conn->prepare("UPDATE users SET name = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $role, $user_id);
        
        if ($stmt->execute()) {
            // 記錄管理操作
            $admin_id = $_SESSION['admin_id'];
            $action = "Updated user (ID: $user_id) - Name: $name, Role: $role";
            $log_stmt = $conn->prepare("INSERT INTO user_logs (admin_id, action) VALUES (?, ?)");
            $log_stmt->bind_param("is", $admin_id, $action);
            $log_stmt->execute();
            
            echo "<script>alert('User updated successfully!'); window.location.href='manage_users.php';</script>";
        } else {
            echo "<script>alert('Failed to update user: " . $conn->error . "');</script>";
        }
    } else {
        echo "<script>alert('Name and role are required.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Green Life Dental Clinic</title>
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
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
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

        .page-title h1 {
            color: white !important;
            font-size: 2.5rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .page-title i {
            color: white !important;
            font-size: 2.5rem;
            margin-right: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
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

        .user-avatar-section {
            text-align: center;
            margin-bottom: 2rem;
            padding: 2rem;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05), rgba(16, 185, 129, 0.05));
            border-radius: 1.5rem;
            border: 2px solid rgba(37, 99, 235, 0.1);
        }

        .user-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: 600;
            margin: 0 auto 1rem;
            box-shadow: 0 12px 25px -5px rgba(37, 99, 235, 0.3);
        }

        .user-id-badge {
            background: linear-gradient(135deg, var(--accent-color), #fbbf24);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.5rem;
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .form-control:disabled, .form-control:read-only, .form-control[readonly] {
            background: #f9fafb;
            border-color: #e5e7eb;
            color: #6b7280;
            cursor: not-allowed;
        }

        .role-select-wrapper {
            position: relative;
        }

        .role-badge {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            pointer-events: none;
            z-index: 5;
        }

        .role-admin { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .role-doctor { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .role-patient { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }

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

        .security-note {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 1rem;
            padding: 1rem;
            margin-top: 1rem;
            color: #92400e;
        }

        .security-note i {
            color: var(--warning-color);
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
            
            .btn-group-custom {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .user-avatar {
                width: 100px;
                height: 100px;
                font-size: 2rem;
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
            
            .user-avatar {
                width: 80px;
                height: 80px;
                font-size: 1.5rem;
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
                <i class="bi bi-person-gear" style="font-size: 2.8rem; color: #fff; text-shadow: 0 2px 8px rgba(0,0,0,0.12);"></i>
                <div>
                    <h1 class="mb-1 fw-bold" style="font-size: 2.3rem; color: #fff; letter-spacing: 0.5px; text-shadow: 0 2px 8px rgba(0,0,0,0.12); font-family: 'Segoe UI', 'Inter', Arial, sans-serif;">Edit User Account</h1>
                    <div class="mb-0" style="font-size: 1.08rem; color: #f3f4f6; opacity: 0.95; font-weight: 500;">Update user information and role permissions</div>
                </div>
            </div>
            <nav class="breadcrumb-custom" style="background: rgba(255,255,255,0.10); border-radius: 1rem; padding: 0.75rem 1.5rem; margin-top: 1rem;">
                <ol class="breadcrumb mb-0" style="background: transparent; padding: 0; margin: 0; font-size: 1.08rem;">
                    <li class="breadcrumb-item">
                        <a href="dashboard.php" style="color: #e0e7ef; font-weight: 500; text-decoration: none; transition: color 0.2s;">Dashboard</a>
                    </li>
                    <li class="breadcrumb-separator" style="color: #e0e7ef; margin: 0 0.5rem; font-weight: 600;">/</li>
                    <li class="breadcrumb-item">
                        <a href="manage_users.php" style="color: #e0e7ef; font-weight: 500; text-decoration: none; transition: color 0.2s;">Manage Users</a>
                    </li>
                    <li class="breadcrumb-separator" style="color: #e0e7ef; margin: 0 0.5rem; font-weight: 600;">/</li>
                    <li class="breadcrumb-item active" style="color: #fff; font-weight: 600;">Edit User</li>
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
            <!-- User Avatar Section -->
            <div class="user-avatar-section">
                <div class="user-avatar">
                    <?= strtoupper(substr($user['name'], 0, 2)) ?>
                </div>
                <h4 class="mb-1"><?= htmlspecialchars($user['name']) ?></h4>
                <p class="text-muted mb-2"><?= htmlspecialchars($user['email']) ?></p>
                <div class="user-id-badge">User ID: #<?= $user['id'] ?></div>
            </div>

            <form method="POST" id="editUserForm">
                <div class="row">
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-person"></i>Full Name
                            </label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']); ?>" required>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-envelope"></i>Email Address
                            </label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']); ?>" readonly>
                            <small class="text-muted">Email cannot be changed for security reasons</small>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-shield-check"></i>User Role
                            </label>
                            <div class="role-select-wrapper">
                                <select name="role" class="form-select" id="roleSelect" required>
                                    <option value="">Select a role...</option>
                                    <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : ''; ?>>System Administrator</option>
                                    <option value="doctor" <?= $user['role'] == 'doctor' ? 'selected' : ''; ?>>Doctor/Dentist</option>
                                    <option value="patient" <?= $user['role'] == 'patient' ? 'selected' : ''; ?>>Patient</option>
                                </select>
                                <div class="role-badge role-<?= $user['role'] ?>" id="roleBadge">
                                    <?= ucfirst($user['role']) ?>
                                </div>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <strong>Admin:</strong> Full system access &nbsp;|&nbsp; 
                                <strong>Doctor:</strong> Patient management &nbsp;|&nbsp; 
                                <strong>Patient:</strong> Appointment booking
                            </small>
                        </div>
                    </div>
                </div>

                <div class="security-note">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Security Notice:</strong> Changing a user's role will immediately affect their system permissions and access levels. Please ensure you have the proper authorization before making this change.
                </div>

                <div class="btn-group-custom">
                    <a href="manage_users.php" class="btn-custom btn-secondary-custom">
                        <i class="bi bi-arrow-left"></i>Cancel
                    </a>
                    <button type="submit" class="btn-custom btn-primary-custom">
                        <i class="bi bi-check-lg"></i>Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('editUserForm');
        const roleSelect = document.getElementById('roleSelect');
        const roleBadge = document.getElementById('roleBadge');
        const submitBtn = form.querySelector('button[type="submit"]');

        // Update role badge when selection changes
        roleSelect.addEventListener('change', function() {
            const selectedRole = this.value;
            roleBadge.className = `role-badge role-${selectedRole}`;
            roleBadge.textContent = selectedRole ? selectedRole.charAt(0).toUpperCase() + selectedRole.slice(1) : '';
            
            // Update role badge visibility
            if (selectedRole) {
                roleBadge.style.display = 'block';
            } else {
                roleBadge.style.display = 'none';
            }
        });

        // Form validation
        form.addEventListener('submit', function(e) {
            const name = form.querySelector('input[name="name"]').value.trim();
            const role = roleSelect.value;

            if (!name) {
                e.preventDefault();
                alert('Please enter a valid name.');
                form.querySelector('input[name="name"]').focus();
                return;
            }

            if (!role) {
                e.preventDefault();
                alert('Please select a user role.');
                roleSelect.focus();
                return;
            }

            // Confirmation for role changes
            const currentRole = '<?= $user['role'] ?>';
            if (role !== currentRole) {
                const confirmed = confirm(`You are changing the user's role from "${currentRole}" to "${role}". This will affect their system permissions. Are you sure you want to continue?`);
                if (!confirmed) {
                    e.preventDefault();
                    return;
                }
            }

            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        });

        // Add input animations
        const inputs = form.querySelectorAll('.form-control, .form-select');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                if (!this.disabled) {
                    this.parentElement.querySelector('.form-label').style.color = 'var(--primary-color)';
                }
            });

            input.addEventListener('blur', function() {
                this.parentElement.querySelector('.form-label').style.color = '#374151';
            });
        });

        // Initialize role badge
        const initialRole = roleSelect.value;
        if (initialRole) {
            roleBadge.style.display = 'block';
        } else {
            roleBadge.style.display = 'none';
        }
    });
</script>
</body>
</html>
