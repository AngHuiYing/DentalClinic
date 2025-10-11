<?php
session_start();
include '../includes/db.php';

// 确保管理员已登录
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 获取搜索关键字
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// 分頁設定
$doctors_per_page = 12; // 每頁顯示醫生數
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $doctors_per_page;

// 先計算總醫生數
$count_sql = "SELECT COUNT(*) as total FROM doctors d LEFT JOIN users u ON d.user_id = u.id";
if (!empty($search)) {
    $count_sql .= " WHERE d.name LIKE '%$search%'
                    OR d.specialty LIKE '%$search%'
                    OR d.department LIKE '%$search%'
                    OR d.user_id LIKE '%$search%'
                    OR u.email LIKE '%$search%'";
}
$count_result = $conn->query($count_sql);
$total_doctors = $count_result->fetch_assoc()['total'];

// 計算分頁
$total_pages = ceil($total_doctors / $doctors_per_page);
$start_doctor = ($current_page - 1) * $doctors_per_page + 1;
$end_doctor = min($current_page * $doctors_per_page, $total_doctors);

// 获取医生列表（支持搜索和分頁）
$sql = "SELECT d.id, d.user_id, d.name, d.image, d.specialty, d.experience, d.department, u.email 
        FROM doctors d 
        LEFT JOIN users u ON d.user_id = u.id";

if (!empty($search)) {
    $sql .= " WHERE d.name LIKE '%$search%'
              OR d.specialty LIKE '%$search%'
              OR d.department LIKE '%$search%'
              OR d.user_id LIKE '%$search%'
              OR u.email LIKE '%$search%'";
}

$sql .= " ORDER BY d.id DESC LIMIT $doctors_per_page OFFSET $offset";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors - Dental Clinic Admin</title>
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
            --accent-orange: #f59e0b;
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
            content: '🏥';
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

        /* Medical-themed stats */
        .stats-info {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, #fafbff 100%);
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 25px;
            border: 2px solid var(--clinic-secondary);
            box-shadow: var(--shadow-soft);
            position: relative;
        }

        .stats-info::before {
            content: '👩‍⚕️';
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 1.5rem;
            opacity: 0.6;
        }

        .stats-text {
            margin: 0;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1.05rem;
        }

        /* Clinic-style search section */
        .search-add-section {
            background: var(--bg-secondary);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-light);
            position: relative;
        }

        .search-add-section::before {
            content: '🔍';
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 1.5rem;
            opacity: 0.3;
        }
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        }

        .search-container {
            flex-grow: 1;
        }

        .search-input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-input {
            width: 100%;
            padding: 16px 25px 16px 50px;
            border: 2px solid var(--border-light);
            border-radius: 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--clinic-primary);
            background: var(--bg-secondary);
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.15);
            transform: translateY(-1px);
        }

        .search-icon {
            position: absolute;
            left: 18px;
            color: var(--clinic-primary);
            font-size: 1.2rem;
            z-index: 2;
        }

        .search-btn {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-accent));
            color: white;
            border: none;
            border-radius: 16px;
            padding: 16px 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
            white-space: nowrap;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
        }

        .search-btn:hover {
            background: linear-gradient(135deg, var(--clinic-accent), #0284c7);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(14, 165, 233, 0.4);
        }

        .add-doctor-btn {
            background: linear-gradient(135deg, var(--clinic-green), #059669);
            color: white;
            border: none;
            border-radius: 16px;
            padding: 16px 24px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            white-space: nowrap;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .add-doctor-btn:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
            color: white;
            text-decoration: none;
        }

        /* Medical table design */
        .doctors-table-container {
            background: var(--bg-secondary);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-light);
        }

        .doctors-table {
            margin-bottom: 0;
        }

        .doctors-table thead th {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-accent));
            color: white;
            font-weight: 600;
            border: none;
            padding: 20px 16px;
            font-size: 0.95rem;
            text-transform: none;
            letter-spacing: 0.3px;
            position: relative;
        }

        .doctors-table tbody td {
            padding: 20px 16px;
            border-bottom: 1px solid rgba(226, 232, 240, 0.6);
            vertical-align: middle;
            font-size: 0.95rem;
            background: var(--bg-secondary);
        }

        .doctors-table tbody tr:hover {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.03), rgba(34, 211, 238, 0.02));
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05);
        }

        .doctor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            object-fit: cover;
            border: 3px solid var(--clinic-secondary);
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.2);
            margin-left: -10px;
        }

        .no-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
            margin-left: -10px;
        }

        .doctor-name {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 1.05rem;
        }

        .doctor-specialty {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.15), rgba(34, 211, 238, 0.1));
            color: var(--clinic-primary);
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }

        .experience-badge {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(251, 191, 36, 0.1));
            color: var(--clinic-orange);
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-edit, .btn-delete {
            border: none;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
            min-width: 70px;
            justify-content: center;
        }

        .btn-edit {
            background: linear-gradient(135deg, var(--accent-orange), #d97706);
            color: white;
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #d97706, #b45309);
            transform: translateY(-1px);
            color: white;
            text-decoration: none;
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
            color: white;
            text-decoration: none;
        }

        /* Mobile-specific styles */
        @media (max-width: 576px) {
            .main-container {
                padding-top: 80px;
                padding-left: 10px;
                padding-right: 10px;
            }

            .page-header {
                padding: 20px 15px;
                margin-bottom: 20px;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .search-add-section {
                padding: 15px;
            }

            .search-input {
                padding: 10px 15px 10px 40px;
                font-size: 0.9rem;
            }

            .search-btn, .add-doctor-btn {
                padding: 10px 15px;
                font-size: 0.85rem;
            }

            .doctors-table thead th {
                padding: 12px 8px;
                font-size: 0.75rem;
            }

            .doctors-table tbody td {
                padding: 12px 8px;
                font-size: 0.85rem;
            }

            .doctor-avatar, .no-avatar {
                width: 40px;
                height: 40px;
                margin-left: -8px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }

            .btn-edit, .btn-delete {
                padding: 8px 12px;
                font-size: 0.75rem;
                min-width: 60px;
            }
        }

        @media (max-width: 768px) {
            .doctors-table-container {
                margin: 0 -15px;
                border-radius: 0;
            }
        }

        .stats-info {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05), rgba(16, 185, 129, 0.03));
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-blue);
        }

        .stats-text {
            margin: 0;
            color: var(--text-dark);
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .search-add-section {
                flex-direction: column;
                align-items: stretch;
            }

            .search-container {
                min-width: 100%;
            }

            .add-doctor-btn {
                justify-content: center;
            }

            .doctors-table-container {
                overflow-x: auto;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
        
        /* Pagination Styles */
        .pagination-container {
            background: var(--bg-secondary);
            border-radius: 24px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: var(--shadow-medium);
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
            border: 2px solid var(--border-light);
            color: var(--clinic-primary);
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
            background: var(--clinic-primary);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        
        .page-btn.active {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: #1a202c;
            border-color: var(--clinic-primary);
            font-weight: 700;
            text-shadow: none;
        }
        
        .page-btn.disabled {
            background: var(--bg-primary);
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
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="bi bi-hospital text-primary"></i>
                Dental Team Management
            </h1>
            <p class="page-subtitle">🦷 Manage and coordinate your dental professionals</p>
        </div>

        <!-- Statistics Info -->
        <?php 
        $total_doctors = $result->num_rows;
        $conn->close();
        // Re-run query for display since we already fetched the result
        include '../includes/db.php';
        $display_sql = $sql;
        $display_result = $conn->query($display_sql);
        ?>
        <div class="stats-info">
            <p class="stats-text">
                <i class="bi bi-clipboard-data me-2 text-primary"></i>
                🩺 Showing <?= $result->num_rows ?> of <?= number_format($total_doctors) ?> Dental Professionals
                <?php if (!empty($search)): ?>
                    | 🔍 Search Results for "<strong><?= htmlspecialchars($search) ?></strong>"
                <?php endif; ?>
                <?php if ($total_pages > 1): ?>
                    | 📄 Page <?= $current_page ?> of <?= $total_pages ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Search and Add Section -->
        <div class="search-add-section">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-lg-8">
                    <div class="search-container">
                        <form method="GET" class="search-input-group">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" 
                                   name="search" 
                                   class="search-input" 
                                   placeholder="Search by name, specialty, department, email, or user ID..."
                                   value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                            <div class="d-flex gap-2 mt-2 mt-sm-0 ms-sm-2">
                                <button type="submit" class="search-btn flex-shrink-0">
                                    <i class="bi bi-search"></i>
                                    <span class="d-none d-sm-inline">Search</span>
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="manage_doctors.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x"></i>
                                        <span class="d-none d-sm-inline">Clear</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="d-grid">
                        <a href="add_doctor_profile.php" class="add-doctor-btn">
                            <i class="bi bi-person-plus-fill"></i>
                            👨‍⚕️ Add Dental Professional
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Doctors Table -->
        <div class="doctors-table-container">
            <div class="table-responsive">
                <table class="table doctors-table mb-0">
                    <thead>
                        <tr>
                            <th class="d-none d-md-table-cell">🆔 ID</th>
                            <th>👤 Profile</th>
                            <th>🩺 Doctor</th>
                            <th class="d-none d-lg-table-cell">🦷 Specialty</th>
                            <th class="d-none d-xl-table-cell">📅 Experience</th>
                            <th class="d-none d-lg-table-cell">🏥 Department</th>
                            <th class="d-none d-xl-table-cell">📧 Email</th>
                            <th class="d-none d-md-table-cell">🔢 User ID</th>
                            <th>⚙️ Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($display_result->num_rows > 0): ?>
                            <?php while ($row = $display_result->fetch_assoc()): ?>
                            <tr>
                                <td class="d-none d-md-table-cell">
                                    <span class="badge bg-primary">#<?= $row['id'] ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($row['image'])): ?>
                                        <img src="../<?= htmlspecialchars($row['image']) ?>" 
                                             alt="Dr. <?= htmlspecialchars($row['name']) ?>" 
                                             class="doctor-avatar">
                                    <?php else: ?>
                                        <div class="no-avatar">
                                            <?= strtoupper(substr($row['name'], 0, 2)) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="doctor-name">Dr. <?= htmlspecialchars($row['name']) ?></div>
                                    <!-- Mobile-only info -->
                                    <div class="d-lg-none">
                                        <small class="text-muted d-block"><?= htmlspecialchars($row['specialty']) ?></small>
                                        <small class="text-muted d-block"><?= htmlspecialchars($row['department']) ?></small>
                                        <small class="text-muted d-block d-md-none">ID: <?= $row['user_id'] ?></small>
                                    </div>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <span class="doctor-specialty"><?= htmlspecialchars($row['specialty']) ?></span>
                                </td>
                                <td class="d-none d-xl-table-cell">
                                    <span class="experience-badge"><?= htmlspecialchars($row['experience']) ?> years</span>
                                </td>
                                <td class="d-none d-lg-table-cell"><?= htmlspecialchars($row['department']) ?></td>
                                <td class="d-none d-xl-table-cell">
                                    <a href="mailto:<?= htmlspecialchars($row['email']) ?>" class="text-decoration-none text-truncate" style="max-width: 150px; display: inline-block;">
                                        <?= htmlspecialchars($row['email']) ?>
                                    </a>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <span class="badge bg-secondary"><?= $row['user_id'] ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit_doctor.php?id=<?= $row['id'] ?>" class="btn-edit">
                                            <i class="bi bi-pencil"></i>
                                            <span class="d-none d-sm-inline">Edit</span>
                                        </a>
                                        <a href="delete_doctor.php?id=<?= $row['id'] ?>" 
                                           class="btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete Dr. <?= htmlspecialchars($row['name']) ?>? This action cannot be undone.');">
                                            <i class="bi bi-trash"></i>
                                            <span class="d-none d-sm-inline">Delete</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-person-x fs-1 mb-3 d-block"></i>
                                        <h5>No doctors found</h5>
                                        <?php if (!empty($search)): ?>
                                            <p>No doctors match your search criteria. Try adjusting your search terms.</p>
                                            <a href="manage_doctors.php" class="btn btn-outline-primary">Show All Doctors</a>
                                        <?php else: ?>
                                            <p>No doctors have been registered yet.</p>
                                            <a href="add_doctor_profile.php" class="btn btn-primary">Add First Doctor</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                Showing <?= $start_doctor ?>-<?= $end_doctor ?> of <?= number_format($total_doctors) ?> doctors
            </div>
            
            <div class="pagination-nav">
                <!-- Previous Page -->
                <?php if ($current_page > 1): ?>
                    <?php 
                    $prev_params = $_GET;
                    $prev_params['page'] = $current_page - 1;
                    ?>
                    <a href="?<?= http_build_query($prev_params) ?>" class="page-btn">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                <?php else: ?>
                    <span class="page-btn disabled">
                        <i class="bi bi-chevron-left"></i> Previous
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
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="page-btn disabled">
                        Next <i class="bi bi-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');
            
            // Auto-focus search input on page load (desktop only)
            if (searchInput && !searchInput.value && window.innerWidth > 768) {
                searchInput.focus();
            }
            
            // Handle search form on mobile
            const searchForm = searchInput.closest('form');
            if (searchForm) {
                searchForm.addEventListener('submit', function(e) {
                    if (!searchInput.value.trim()) {
                        e.preventDefault();
                        searchInput.focus();
                    }
                });
            }
        });
    </script>
</body>
</html>
