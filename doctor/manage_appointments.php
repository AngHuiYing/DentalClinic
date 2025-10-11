<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['user_name'] ?? 'Doctor';

// Get doctor ID from doctors table
$sql = "SELECT id FROM doctors WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    die("Doctor record not found!");
}

$doctor_id = $doctor['id'];

// Handle status updates
if ($_POST['action'] ?? '' === 'update_status' && isset($_POST['appointment_id'], $_POST['status'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $status = $_POST['status'];
    
    if (in_array($status, ['confirmed', 'completed', 'cancelled_by_admin', 'cancelled_by_patient'])) {
        $update_sql = "UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sii", $status, $appointment_id, $doctor_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Appointment status updated successfully!";
        } else {
            $error_message = "Failed to update appointment status.";
        }
    }
}

// 分頁設定
$appointments_per_page = 15; // 每頁顯示預約數
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $appointments_per_page;

// Search and filter parameters
$search = $_GET['search'] ?? '';
$date_filter = $_GET['date_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

// Build query conditions
$conditions = ["a.doctor_id = ?"];
$params = [$doctor_id];
$param_types = "i";

if (!empty($search)) {
    $conditions[] = "(u.name LIKE ? OR a.patient_name LIKE ? OR u.email LIKE ? OR a.patient_email LIKE ? OR a.patient_phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $param_types .= "sssss";
}

if (!empty($date_filter)) {
    if ($date_filter === 'today') {
        $conditions[] = "a.appointment_date = CURDATE()";
    } elseif ($date_filter === 'upcoming') {
        $conditions[] = "a.appointment_date >= CURDATE()";
    } elseif ($date_filter === 'past') {
        $conditions[] = "a.appointment_date < CURDATE()";
    }
}

if (!empty($status_filter)) {
    $conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

// 先計算總預約數
$count_sql = "SELECT COUNT(*) as total
        FROM appointments a
        LEFT JOIN users u ON a.patient_id = u.id
        WHERE " . implode(" AND ", $conditions);

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_appointments = $total_result->fetch_assoc()['total'];
$count_stmt->close();

// 計算分頁
$total_pages = ceil($total_appointments / $appointments_per_page);
$start_appointment = ($current_page - 1) * $appointments_per_page + 1;
$end_appointment = min($current_page * $appointments_per_page, $total_appointments);

// Main query with pagination
$sql = "SELECT a.id, 
               a.appointment_date, 
               a.appointment_time, 
               a.status,
               a.created_at,
               a.message,
               a.patient_phone,
               COALESCE(u.name, a.patient_name) as patient_name,
               COALESCE(u.email, a.patient_email) as patient_email
        FROM appointments a
        LEFT JOIN users u ON a.patient_id = u.id
        WHERE " . implode(" AND ", $conditions) . "
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT ? OFFSET ?";

// Add pagination parameters
$params[] = $appointments_per_page;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$today_count = 0;
$upcoming_count = 0;
$pending_count = 0;
$completed_count = 0;

foreach ($appointments as $appointment) {
    if ($appointment['appointment_date'] === date('Y-m-d')) {
        $today_count++;
    }
    if ($appointment['appointment_date'] >= date('Y-m-d')) {
        $upcoming_count++;
    }
    if ($appointment['status'] === 'pending') {
        $pending_count++;
    }
    if ($appointment['status'] === 'completed') {
        $completed_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Management - Doctor Portal</title>
    
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

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-info h3 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
            line-height: 1;
        }

        .stat-info p {
            color: var(--gray-600);
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
        }

        .stat-icon {
            width: 4rem;
            height: 4rem;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
        .stat-icon.success { background: linear-gradient(135deg, var(--success), #059669); }
        .stat-icon.warning { background: linear-gradient(135deg, var(--warning), #d97706); }
        .stat-icon.info { background: linear-gradient(135deg, var(--info), #0891b2); }

        /* Filters Section */
        .filters-section {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        .filters-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: var(--radius-lg);
            padding: 0.75rem 1.5rem;
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
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: var(--gray-100);
            border-color: var(--gray-400);
        }

        /* Appointments Table */
        .appointments-section {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .appointments-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .appointments-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .table-responsive {
            border-radius: 0;
        }

        .table {
            margin: 0;
        }

        .table th {
            background: var(--gray-50);
            font-weight: 700;
            color: var(--gray-800);
            border: none;
            padding: 1.25rem 1.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 1.5rem;
            vertical-align: middle;
            border-color: var(--gray-200);
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: var(--gray-50);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-confirmed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .status-completed {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
            border: 1px solid rgba(37, 99, 235, 0.2);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Action Buttons */
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            border-radius: var(--radius);
        }

        .btn-outline-success {
            border-color: var(--success);
            color: var(--success);
        }

        .btn-outline-success:hover {
            background: var(--success);
            border-color: var(--success);
        }

        .btn-outline-primary {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-outline-danger {
            border-color: var(--danger);
            color: var(--danger);
        }

        .btn-outline-danger:hover {
            background: var(--danger);
            border-color: var(--danger);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-500);
        }

        .empty-icon {
            width: 5rem;
            height: 5rem;
            background: var(--gray-100);
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--gray-400);
            font-size: 2rem;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .empty-description {
            color: var(--gray-600);
            font-size: 1rem;
        }

        /* Responsive Design */
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
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .filters-section {
                padding: 1.5rem;
            }
            
            .appointments-header {
                padding: 1.5rem;
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .table th,
            .table td {
                padding: 1rem;
            }
            
            .btn-group {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .btn-group .btn {
                width: 100%;
            }
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
        
        /* Pagination Styles */
        .pagination-container {
            background: white;
            padding: 2rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            background: var(--white);
            border: 2px solid var(--gray-300);
            color: var(--primary);
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius-lg);
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
            box-shadow: var(--shadow-md);
        }
        
        .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            font-weight: 700;
        }
        
        .page-btn.disabled {
            background: var(--gray-100);
            color: var(--gray-400);
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-info {
            text-align: center;
            color: var(--gray-600);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
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
                <h1 class="page-title">Appointment Management</h1>
                <p class="page-subtitle">Manage and track all your patient appointments</p>
            </section>

            <!-- Success/Error Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success fade-in">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger fade-in">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <section class="fade-in">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $today_count; ?></h3>
                                <p>Today's Appointments</p>
                            </div>
                            <div class="stat-icon primary">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $upcoming_count; ?></h3>
                                <p>Upcoming</p>
                            </div>
                            <div class="stat-icon success">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $pending_count; ?></h3>
                                <p>Pending Confirmation</p>
                            </div>
                            <div class="stat-icon warning">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $completed_count; ?></h3>
                                <p>Completed</p>
                            </div>
                            <div class="stat-icon info">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Filters -->
            <section class="filters-section fade-in">
                <h3 class="filters-title">
                    <i class="fas fa-filter"></i>
                    Search & Filter
                </h3>
                
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Patients</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Patient name, email, or phone" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="date_filter" class="form-label">Date Filter</label>
                        <select class="form-select" id="date_filter" name="date_filter">
                            <option value="">All Dates</option>
                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="upcoming" <?php echo $date_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="past" <?php echo $date_filter === 'past' ? 'selected' : ''; ?>>Past</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="status_filter" class="form-label">Status Filter</label>
                        <select class="form-select" id="status_filter" name="status_filter">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled_by_admin" <?php echo $status_filter === 'cancelled_by_admin' ? 'selected' : ''; ?>>Cancelled by Admin</option>
                            <option value="cancelled_by_patient" <?php echo $status_filter === 'cancelled_by_patient' ? 'selected' : ''; ?>>Cancelled by Patient</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                            <a href="manage_appointments.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </section>

            <!-- Today's Appointments Table -->
            <?php 
            $today_appointments = array_filter($appointments, function($appointment) {
                return $appointment['appointment_date'] === date('Y-m-d');
            });
            ?>
            <?php if (!empty($today_appointments)): ?>
            <section class="appointments-section fade-in" style="margin-bottom: 2rem;">
                <div class="appointments-header" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                    <h3 class="appointments-title">
                        <i class="fas fa-calendar-day"></i>
                        Today's Appointments
                    </h3>
                    <span class="badge bg-light text-dark fs-6">
                        <?php echo count($today_appointments); ?> Today
                    </span>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Time</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Message</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Sort today's appointments by time
                            usort($today_appointments, function($a, $b) {
                                return strcmp($a['appointment_time'], $b['appointment_time']);
                            });
                            
                            foreach ($today_appointments as $appointment): 
                            ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            Booked: <?php echo date('M j, Y', strtotime($appointment['created_at'])); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong class="text-success">
                                            <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                        </strong>
                                        <br>
                                        <small class="text-muted">Today</small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <small class="d-block"><?php echo htmlspecialchars($appointment['patient_email']); ?></small>
                                        <?php if (!empty($appointment['patient_phone'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($appointment['patient_phone']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($appointment['message'])): ?>
                                        <small><?php echo htmlspecialchars($appointment['message']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($appointment['status'] !== 'completed' && $appointment['status'] !== 'cancelled_by_admin' && $appointment['status'] !== 'cancelled_by_patient'): ?>
                                    <div class="btn-group" role="group">
                                        <?php if ($appointment['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" class="btn btn-outline-success btn-sm">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($appointment['status'] === 'confirmed'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-check-double"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <!-- Appointments Table -->
            <section class="appointments-section fade-in">
                <div class="appointments-header">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <h3 class="appointments-title">
                            <i class="fas fa-list"></i>
                            All Appointments
                        </h3>
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge bg-light text-dark fs-6">
                                <?php echo count($appointments); ?> of <?= number_format($total_appointments) ?> Total
                            </span>
                            <?php if ($total_pages > 1): ?>
                            <small class="text-white opacity-85">
                                Page <?= $current_page ?> of <?= $total_pages ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($appointments)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <h4 class="empty-title">No Appointments Found</h4>
                        <p class="empty-description">
                            <?php if (!empty($search) || !empty($date_filter) || !empty($status_filter)): ?>
                                Try adjusting your search criteria or filters.
                            <?php else: ?>
                                You don't have any appointments scheduled yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Date & Time</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Message</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                Booked: <?php echo date('M j, Y', strtotime($appointment['created_at'])); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></strong>
                                            <br>
                                            <span class="text-muted">
                                                <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <small class="d-block"><?php echo htmlspecialchars($appointment['patient_email']); ?></small>
                                            <?php if (!empty($appointment['patient_phone'])): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($appointment['patient_phone']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($appointment['message'])): ?>
                                            <small><?php echo htmlspecialchars($appointment['message']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($appointment['status'] !== 'completed' && $appointment['status'] !== 'cancelled_by_admin' && $appointment['status'] !== 'cancelled_by_patient'): ?>
                                        <div class="btn-group" role="group">
                                            <?php if ($appointment['status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <input type="hidden" name="status" value="confirmed">
                                                <button type="submit" class="btn btn-outline-success btn-sm">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($appointment['status'] === 'confirmed'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-check-double"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <!-- <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <input type="hidden" name="status" value="cancelled_by_admin">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                        onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form> -->
                                        </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?= $start_appointment ?>-<?= $end_appointment ?> of <?= number_format($total_appointments) ?> appointments
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
                <?php endif; ?>
            </section>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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
        document.querySelectorAll('section').forEach(section => {
            observer.observe(section);
        });

        // Auto-submit form on filter change
        document.getElementById('date_filter').addEventListener('change', function() {
            if (this.value) {
                this.closest('form').submit();
            }
        });

        document.getElementById('status_filter').addEventListener('change', function() {
            if (this.value) {
                this.closest('form').submit();
            }
        });

        // Confirm status changes
        document.querySelectorAll('form[method="POST"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const statusInput = this.querySelector('input[name="status"]');
                if (statusInput && statusInput.value === 'completed') {
                    if (!confirm('Mark this appointment as completed?')) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>
</html>