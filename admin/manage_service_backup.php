<?php
// admin/manage_service.php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 建立 services 資料表（如尚未存在）
$conn->query("CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL
)");

// 新增服務
if (isset($_POST['add_service'])) {
    $name = trim($_POST['service_name']);
    $price = floatval($_POST['service_price']);
    if ($name && $price > 0) {
        // 檢查服務名稱是否已存在
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM services WHERE LOWER(name) = LOWER(?)");
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $existing_count = $check_result->fetch_assoc()['count'];
        $check_stmt->close();
        
        if ($existing_count > 0) {
            $_SESSION['error_message'] = "Service name '{$name}' already exists. Please choose a different name.";
        } else {
            $stmt = $conn->prepare("INSERT INTO services (name, price) VALUES (?, ?)");
            $stmt->bind_param("sd", $name, $price);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Service '{$name}' has been added successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to add service. Please try again.";
            }
            $stmt->close();
        }
    } else {
        $_SESSION['error_message'] = "Please provide a valid service name and price.";
    }
    header("Location: manage_service.php");
    exit;
}
// 刪除服務
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // 先獲取服務名稱用於提示消息
    $service_result = $conn->query("SELECT name FROM services WHERE id = $id");
    $service_name = $service_result && $service_result->num_rows > 0 ? $service_result->fetch_assoc()['name'] : 'Service';
    
    $result = $conn->query("DELETE FROM services WHERE id = $id");
    if ($result && $conn->affected_rows > 0) {
        $_SESSION['success_message'] = "Service '{$service_name}' has been deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to delete service. Please try again.";
    }
    header("Location: manage_service.php");
    exit;
}
// 編輯服務
if (isset($_POST['edit_service'])) {
    $id = intval($_POST['service_id']);
    $name = trim($_POST['service_name']);
    $price = floatval($_POST['service_price']);
    if ($name && $price > 0) {
        // 檢查服務名稱是否已存在（排除當前編輯的服務）
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM services WHERE LOWER(name) = LOWER(?) AND id != ?");
        $check_stmt->bind_param("si", $name, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $existing_count = $check_result->fetch_assoc()['count'];
        $check_stmt->close();
        
        if ($existing_count > 0) {
            $_SESSION['error_message'] = "Service name '{$name}' already exists. Please choose a different name.";
        } else {
            $stmt = $conn->prepare("UPDATE services SET name=?, price=? WHERE id=?");
            $stmt->bind_param("sdi", $name, $price, $id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $_SESSION['success_message'] = "Service '{$name}' has been updated successfully!";
                } else {
                    $_SESSION['info_message'] = "No changes were made to the service.";
                }
            } else {
                $_SESSION['error_message'] = "Failed to update service. Please try again.";
            }
            $stmt->close();
        }
    } else {
        $_SESSION['error_message'] = "Please provide a valid service name and price.";
    }
    header("Location: manage_service.php");
    exit;
}

// 分頁設定
$services_per_page = 10; // 每頁顯示服務數
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $services_per_page;

// 先計算總服務數
$count_result = $conn->query("SELECT COUNT(*) as total FROM services");
$total_services = $count_result->fetch_assoc()['total'];

// 計算分頁
$total_pages = ceil($total_services / $services_per_page);
$start_service = ($current_page - 1) * $services_per_page + 1;
$end_service = min($current_page * $services_per_page, $total_services);

$services = $conn->query("SELECT * FROM services ORDER BY id DESC LIMIT $services_per_page OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Management | Dental Clinic Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --admin-primary: #c0392b;
            --admin-secondary: #e74c3c;
            --admin-dark: #a93226;
            --admin-light: #fadbd8;
            --admin-gradient: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
            --clinic-accent: #2c3e50;
            --clinic-muted: #95a5a6;
            --clinic-light-bg: #f8fafc;
            --clinic-success: #27ae60;
            --clinic-warning: #f39c12;
            --clinic-info: #3498db;
            --clinic-card-shadow: 0 10px 30px rgba(192, 57, 43, 0.15);
            --clinic-border-radius: 16px;
            --clinic-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            line-height: 1.6;
            color: #2c3e50;
        }

        /* Main Content */
        .main-content {
            padding: 100px 0 50px 0;
        }

        /* Page Header */
        .page-header {
            background: var(--admin-gradient);
            padding: 60px 0;
            margin-bottom: 50px;
            border-radius: 0 0 50px 50px;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="%23ffffff" opacity="0.1"><path d="M0,0v46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1547.29,35.58,1000,98.74V0Z"/></svg>') repeat-x;
            animation: wave 20s linear infinite;
        }

        @keyframes wave {
            0% { background-position-x: 0; }
            100% { background-position-x: 1000px; }
        }

        .page-header h1 {
            color: white;
            font-size: 2.5rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 15px;
            text-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .page-header .subtitle {
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
            font-size: 1.1rem;
            font-weight: 400;
        }

        /* Container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Cards */
        .clinic-card {
            background: white;
            border-radius: var(--clinic-border-radius);
            box-shadow: var(--clinic-card-shadow);
            border: none;
            transition: var(--clinic-transition);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .clinic-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(192, 57, 43, 0.25);
        }

        .card-header {
            background: var(--admin-gradient);
            border: none;
            padding: 25px;
            color: white;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-body {
            padding: 30px;
        }

        /* Service Statistics */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: var(--clinic-border-radius);
            box-shadow: var(--clinic-card-shadow);
            text-align: center;
            transition: var(--clinic-transition);
            border-left: 5px solid var(--admin-primary);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--admin-primary);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--clinic-muted);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Form Styling */
        .form-label {
            font-weight: 600;
            color: var(--clinic-accent);
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-control, .form-select {
            border: 2px solid #e3e6eb;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 1rem;
            transition: var(--clinic-transition);
            background-color: #fafbfc;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.25rem rgba(192, 57, 43, 0.25);
            background-color: white;
        }

        /* Buttons */
        .btn {
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--clinic-transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-admin {
            background: var(--admin-gradient);
            color: white;
        }

        .btn-admin:hover {
            background: linear-gradient(135deg, #a93226 0%, #c0392b 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(192, 57, 43, 0.4);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            border: none;
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #229954 0%, #27ae60 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46, 204, 113, 0.4);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(192, 57, 43, 0.4);
            color: white;
        }

        /* Table Styling */
        .table-container {
            background: white;
            border-radius: var(--clinic-border-radius);
            box-shadow: var(--clinic-card-shadow);
            overflow: hidden;
            overflow-x: auto;
        }

        .table {
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 100%;
        }

        .table thead th {
            background: var(--admin-gradient);
            color: white;
            border: none;
            padding: 20px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 20px;
            border-top: 1px solid #eef2f7;
            vertical-align: middle;
            font-size: 0.95rem;
        }

        .table tbody tr {
            transition: var(--clinic-transition);
        }

        .table tbody tr:hover {
            background-color: var(--admin-light);
            transform: scale(1.002);
        }

        /* Input Styling in Table */
        .table input[type="text"], 
        .table input[type="number"] {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.9rem;
            width: 100%;
            background: white;
            transition: var(--clinic-transition);
        }

        .table input[type="text"]:focus, 
        .table input[type="number"]:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.2rem rgba(192, 57, 43, 0.25);
            outline: none;
        }

        /* Service ID Badge */
        .service-id {
            background: var(--admin-primary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Price Display */
        .price-display {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--clinic-success);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Add Service Form */
        .add-service-form {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px dashed var(--admin-primary);
            border-radius: var(--clinic-border-radius);
            padding: 25px;
            margin-bottom: 30px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--clinic-muted);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* Search Container */
        .search-container {
            position: relative;
            margin-bottom: 25px;
        }

        .search-container .form-control {
            padding-left: 50px;
            border-radius: 50px;
            border: 2px solid #e3e6eb;
            background: white;
        }

        .search-container .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--clinic-muted);
            font-size: 1.1rem;
        }

        /* Alert Messages */
        .alert {
            border-radius: var(--clinic-border-radius);
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid var(--clinic-success);
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-info {
            background: linear-gradient(135deg, #cce7ff 0%, #b3daff 100%);
            color: #004085;
            border-left: 4px solid var(--clinic-info);
        }

        .alert .fas {
            font-size: 1.1rem;
            margin-right: 8px;
        }

        .btn-close {
            margin-left: auto;
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }

        .btn-close:hover {
            opacity: 1;
        }

        /* Alert Animation */
        .alert.fade.show {
            animation: slideInDown 0.5s ease-out;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding-top: 80px;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .page-header {
                padding: 40px 0;
                margin-bottom: 30px;
            }

            .card-body, .add-service-form {
                padding: 20px;
            }

            .table thead th,
            .table tbody td {
                padding: 12px 8px;
                font-size: 0.85rem;
            }

            .btn {
                padding: 10px 16px;
                font-size: 0.9rem;
            }

            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }

            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .action-buttons .btn {
                margin-bottom: 5px;
            }
        }
        
        /* Pagination Styles */
        .pagination-container {
            background: var(--bg-secondary);
            border-radius: 20px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-light);
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            background: var(--bg-primary);
            border: 2px solid var(--primary-light);
            color: var(--primary);
            padding: 0.5rem 0.75rem;
            border-radius: 12px;
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
            background: var(--primary);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        
        .page-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #1a202c;
            border-color: var(--primary);
            font-weight: 700;
            text-shadow: none;
        }
        
        .page-btn.disabled {
            background: var(--bg-light);
            color: var(--text-light);
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-info {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-concierge-bell"></i> Service Management</h1>
            <p class="subtitle">Manage dental clinic services and pricing</p>
        </div>
    </div>

    <div class="main-container">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['info_message'])): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <?= htmlspecialchars($_SESSION['info_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['info_message']); ?>
        <?php endif; ?>

        <!-- Service Statistics -->
        <div class="stats-container">
            <?php 
            $total_services = $conn->query("SELECT COUNT(*) as count FROM services")->fetch_assoc()['count'];
            $avg_price = $conn->query("SELECT AVG(price) as avg FROM services")->fetch_assoc()['avg'];
            $max_price = $conn->query("SELECT MAX(price) as max FROM services")->fetch_assoc()['max'];
            $min_price = $conn->query("SELECT MIN(price) as min FROM services")->fetch_assoc()['min'];
            ?>
            <div class="stat-card">
                <div class="stat-number"><?= $total_services ?></div>
                <div class="stat-label">Total Services</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">RM<?= number_format($avg_price ?: 0, 2) ?></div>
                <div class="stat-label">Average Price</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">RM<?= number_format($max_price ?: 0, 2) ?></div>
                <div class="stat-label">Highest Price</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">RM<?= number_format($min_price ?: 0, 2) ?></div>
                <div class="stat-label">Lowest Price</div>
            </div>
        </div>

        <!-- Add New Service -->
        <div class="clinic-card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-plus-circle"></i>
                    Add New Service
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="add-service-form">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label for="service_name" class="form-label">
                                <i class="fas fa-tooth"></i> Service Name
                            </label>
                            <input type="text" 
                                   id="service_name"
                                   name="service_name" 
                                   class="form-control" 
                                   placeholder="Enter service name" 
                                   required>
                        </div>
                        <div class="col-md-3">
                            <label for="service_price" class="form-label">
                                <i class="fas fa-dollar-sign"></i> Price
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">RM</span>
                                <input type="number" 
                                       id="service_price"
                                       name="service_price" 
                                       class="form-control" 
                                       placeholder="0.00"
                                       min="0" 
                                       step="0.01" 
                                       required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="add_service" class="btn btn-admin w-100">
                                <i class="fas fa-plus"></i> Add Service
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Services List -->
        <div class="clinic-card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list-alt"></i>
                        Current Services
                    </h5>
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge bg-primary fs-6">
                            <?php echo $services->num_rows; ?> of <?= number_format($total_services) ?> Services
                        </span>
                        <?php if ($total_pages > 1): ?>
                        <small class="text-muted">
                            Page <?= $current_page ?> of <?= $total_pages ?>
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Search Bar -->
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           id="searchInput" 
                           class="form-control" 
                           placeholder="Search services by name or price...">
                </div>

                <!-- Services Table -->
                <?php if ($services->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="table table-hover" id="servicesTable">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag"></i> ID</th>
                                    <th><i class="fas fa-concierge-bell"></i> Service Name</th>
                                    <th><i class="fas fa-dollar-sign"></i> Price</th>
                                    <th><i class="fas fa-cogs"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $services->fetch_assoc()): ?>
                                    <tr>
                                        <form method="POST" style="display: contents;">
                                            <td>
                                                <span class="service-id">#<?= $row['id'] ?></span>
                                            </td>
                                            <td>
                                                <input type="hidden" name="service_id" value="<?= $row['id'] ?>">
                                                <input type="text" 
                                                       name="service_name" 
                                                       value="<?= htmlspecialchars($row['name']) ?>" 
                                                       required>
                                            </td>
                                            <td>
                                                <div class="input-group">
                                                    <span class="input-group-text">RM</span>
                                                    <input type="number" 
                                                           name="service_price" 
                                                           value="<?= number_format($row['price'], 2, '.', '') ?>" 
                                                           min="0" 
                                                           step="0.01" 
                                                           required>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button type="submit" 
                                                            name="edit_service" 
                                                            class="btn btn-success btn-sm">
                                                        <i class="fas fa-save"></i> Save
                                                    </button>
                                                    <a href="?delete=<?= $row['id'] ?>" 
                                                       class="btn btn-danger btn-sm"
                                                       onclick="return confirm('Are you sure you want to delete this service? This action cannot be undone.')">
                                                        <i class="fas fa-trash-alt"></i> Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </form>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?= $start_service ?>-<?= $end_service ?> of <?= number_format($total_services) ?> services
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
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-concierge-bell"></i>
                        <h4>No Services Added</h4>
                        <p>Start by adding your first dental service to the system.</p>
                        <a href="#" class="btn btn-admin" onclick="document.getElementById('service_name').focus();">
                            <i class="fas fa-plus"></i> Add First Service
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Enhanced Search Functionality
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const table = document.getElementById('servicesTable');
        if (table) {
            const rows = table.querySelectorAll('tbody tr');
            let visibleCount = 0;

            rows.forEach((row) => {
                const serviceName = row.querySelector('input[name="service_name"]').value.toLowerCase();
                const servicePrice = row.querySelector('input[name="service_price"]').value.toLowerCase();
                const serviceId = row.querySelector('input[name="service_id"]').value;

                if (serviceName.includes(filter) || servicePrice.includes(filter) || serviceId.includes(filter)) {
                    row.style.display = '';
                    row.style.animation = 'fadeIn 0.3s ease-in';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Show/hide empty state for search
            updateSearchEmptyState(visibleCount === 0 && filter !== '');
        }
    });
}

// Update Search Empty State
function updateSearchEmptyState(show) {
    let searchEmptyState = document.querySelector('.search-empty-state');
    
    if (show && !searchEmptyState) {
        searchEmptyState = document.createElement('div');
        searchEmptyState.className = 'search-empty-state empty-state';
        searchEmptyState.innerHTML = `
            <i class="fas fa-search"></i>
            <h4>No Services Found</h4>
            <p>No services match your search criteria. Try different keywords.</p>
        `;
        
        const tableContainer = document.querySelector('.table-container');
        if (tableContainer) {
            tableContainer.style.display = 'none';
            tableContainer.parentNode.appendChild(searchEmptyState);
        }
    } else if (!show && searchEmptyState) {
        searchEmptyState.remove();
        const tableContainer = document.querySelector('.table-container');
        if (tableContainer) {
            tableContainer.style.display = 'block';
        }
    }
}

// Price Formatting
document.querySelectorAll('input[name="service_price"]').forEach(input => {
    input.addEventListener('blur', function() {
        const value = parseFloat(this.value);
        if (!isNaN(value)) {
            this.value = value.toFixed(2);
        }
    });
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .table tbody tr {
        animation: fadeIn 0.3s ease-in;
    }
`;
document.head.appendChild(style);

// Auto-focus on service name input when page loads
document.addEventListener('DOMContentLoaded', function() {
    const serviceNameInput = document.getElementById('service_name');
    if (serviceNameInput) {
        serviceNameInput.focus();
    }
});

// Enhanced form validation
document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
    const serviceName = this.querySelector('input[name="service_name"]').value.trim();
    const servicePrice = parseFloat(this.querySelector('input[name="service_price"]').value);
    
    if (serviceName.length < 2) {
        e.preventDefault();
        alert('Service name must be at least 2 characters long.');
        return;
    }
    
    if (servicePrice <= 0) {
        e.preventDefault();
        alert('Service price must be greater than 0.');
        return;
    }
    
    if (servicePrice > 10000) {
        if (!confirm('Service price seems unusually high (RM' + servicePrice.toFixed(2) + '). Are you sure?')) {
            e.preventDefault();
            return;
        }
    }
});

// Table row hover effects
document.querySelectorAll('.table tbody tr').forEach(row => {
    row.addEventListener('mouseenter', function() {
        this.style.transform = 'scale(1.02)';
        this.style.zIndex = '10';
        this.style.position = 'relative';
    });
    
    row.addEventListener('mouseleave', function() {
        this.style.transform = 'scale(1)';
        this.style.zIndex = 'auto';
        this.style.position = 'static';
    });
});
</script>
</body>
</html>
