<?php
session_start();
include '../includes/db.php';

// 检查是否是管理员
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 获取现有的诊所设置信息
$sql = "SELECT * FROM clinic_settings WHERE id = 1";
$result = $conn->query($sql);
$settings = $result->fetch_assoc();

// 如果没有设置记录，创建默认记录
if (!$settings) {
    $conn->query("INSERT INTO clinic_settings (id, clinic_name, clinic_address, contact_email, contact_phone, updated_at) 
                  VALUES (1, 'Green Life Dental Clinic', 'Not Set', 'clinic@example.com', 'Not Set', NOW())");
    $result = $conn->query($sql);
    $settings = $result->fetch_assoc();
}

$success_msg = '';
$error_msg = '';

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $clinic_name = $_POST['clinic_name'];
    $clinic_address = $_POST['clinic_address'];
    $contact_email = $_POST['contact_email'];
    $contact_phone = $_POST['contact_phone'];
    $working_hours = $_POST['working_hours'];
    $description = $_POST['description'];

    $updateSql = "UPDATE clinic_settings SET clinic_name=?, clinic_address=?, contact_email=?, contact_phone=?, working_hours=?, description=?, updated_at=NOW() WHERE id=1";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("ssssss", $clinic_name, $clinic_address, $contact_email, $contact_phone, $working_hours, $description);
    
    if ($stmt->execute()) {
        $success_msg = 'Clinic settings updated successfully!';
        // Refresh settings
        $result = $conn->query($sql);
        $settings = $result->fetch_assoc();
    } else {
        $error_msg = "Error updating settings: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Settings - Green Life Dental Clinic</title>
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
            --gradient-bg: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #1e40af 100%);
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-attachment: fixed;
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
            background: var(--gradient-bg);
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

        .content-section {
            padding: 2rem;
        }

        .settings-card {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .settings-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 1.5rem 1.5rem 0 0;
        }

        .card-title {
            color: #1f2937;
            font-weight: 600;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title i {
            color: var(--primary-color);
            font-size: 1.8rem;
        }

        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: var(--primary-color);
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
        }

        .btn {
            border-radius: 0.75rem;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #1d4ed8);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e3a8a);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(37, 99, 235, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #16a34a);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #16a34a, #15803d);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(34, 197, 94, 0.3);
        }

        .alert {
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
            border: none;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(16, 185, 129, 0.1));
            color: #166534;
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05), rgba(29, 78, 216, 0.05));
            border-radius: 1rem;
            padding: 1.5rem;
            border-left: 4px solid var(--primary-color);
        }

        .info-card h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .info-card p {
            color: #6b7280;
            margin: 0;
            line-height: 1.5;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .main-container {
                margin: 95px 15px 15px 15px;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                margin: 90px 8px 8px 8px;
                border-radius: 1rem;
            }
            
            .header-section {
                padding: 1.5rem;
            }
            
            .content-section {
                padding: 1rem;
            }
            
            .settings-card {
                padding: 1.5rem;
                border-radius: 1rem;
            }
            
            .card-title {
                font-size: 1.25rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
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
            
            .content-section {
                padding: 0.8rem;
            }
            
            .settings-card {
                padding: 1rem;
            }
            
            .card-title {
                font-size: 1.1rem;
            }
        }

        .loading-animation {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease;
        }

        .loading-animation.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="main-container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <i class="bi bi-gear-fill" style="font-size: 3rem;"></i>
            </div>
            <div>
                <h1 class="mb-2">Clinic Settings</h1>
                <p class="mb-0 opacity-90">Manage your clinic information and preferences</p>
            </div>
        </div>
    </div>

    <!-- Content Section -->
    <div class="content-section">
        <!-- Success/Error Messages -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success loading-animation">
                <i class="bi bi-check-circle me-2"></i><?php echo $success_msg; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger loading-animation">
                <i class="bi bi-exclamation-circle me-2"></i><?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <!-- Current Settings Info -->
        <div class="info-grid loading-animation">
            <div class="info-card">
                <h6><i class="bi bi-hospital me-2"></i>Current Clinic Name</h6>
                <p><?php echo htmlspecialchars($settings['clinic_name']); ?></p>
            </div>
            <div class="info-card">
                <h6><i class="bi bi-geo-alt me-2"></i>Current Address</h6>
                <p><?php echo htmlspecialchars($settings['clinic_address']); ?></p>
            </div>
            <div class="info-card">
                <h6><i class="bi bi-envelope me-2"></i>Contact Email</h6>
                <p><?php echo htmlspecialchars($settings['contact_email']); ?></p>
            </div>
            <div class="info-card">
                <h6><i class="bi bi-telephone me-2"></i>Contact Phone</h6>
                <p><?php echo htmlspecialchars($settings['contact_phone']); ?></p>
            </div>
        </div>

        <!-- Settings Form -->
        <div class="settings-card loading-animation">
            <div class="card-title">
                <i class="bi bi-pencil-square"></i>
                Update Clinic Settings
            </div>

            <form method="POST" id="settingsForm">
                <div class="row g-4">
                    <div class="col-12 col-md-6">
                        <label class="form-label">
                            <i class="bi bi-hospital"></i>Clinic Name
                        </label>
                        <input type="text" class="form-control" name="clinic_name" 
                               value="<?= htmlspecialchars($settings['clinic_name']) ?>" required>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <label class="form-label">
                            <i class="bi bi-telephone"></i>Contact Phone
                        </label>
                        <input type="text" class="form-control" name="contact_phone" 
                               value="<?= htmlspecialchars($settings['contact_phone']) ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">
                            <i class="bi bi-geo-alt"></i>Clinic Address
                        </label>
                        <textarea class="form-control" name="clinic_address" rows="3" required><?= htmlspecialchars($settings['clinic_address']) ?></textarea>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">
                            <i class="bi bi-envelope"></i>Contact Email
                        </label>
                        <input type="email" class="form-control" name="contact_email" 
                               value="<?= htmlspecialchars($settings['contact_email']) ?>" required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">
                            <i class="bi bi-clock"></i>Working Hours
                        </label>
                        <input type="text" class="form-control" name="working_hours" 
                               value="<?= htmlspecialchars($settings['working_hours'] ?? 'Mon-Fri: 9AM-6PM') ?>" 
                               placeholder="e.g., Mon-Fri: 9AM-6PM, Sat: 9AM-2PM">
                    </div>

                    <div class="col-12">
                        <label class="form-label">
                            <i class="bi bi-card-text"></i>Clinic Description
                        </label>
                        <textarea class="form-control" name="description" rows="4" 
                                  placeholder="Brief description of your clinic services and specialties..."><?= htmlspecialchars($settings['description'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Update Settings
                            </button>
                            <button type="button" class="btn btn-success" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise me-2"></i>Reset Form
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Page load animations
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        document.querySelectorAll('.loading-animation').forEach((element, index) => {
            setTimeout(() => {
                element.classList.add('visible');
            }, index * 150);
        });
    }, 300);

    // Add loading states for form submission
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        let submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Updating...';
            submitBtn.disabled = true;
        }
    });

    // Auto-resize textareas
    document.querySelectorAll('textarea').forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });
});
</script>
</body>
</html>
