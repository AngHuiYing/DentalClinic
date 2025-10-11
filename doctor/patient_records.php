<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../doctor/login.php");
    exit;
}

// 分頁設定
$patients_per_page = 12; // 每頁顯示病人數
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $patients_per_page;

// 确保 $search 变量被正确初始化
$search = isset($_GET['search']) ? trim($_GET['search']) : "";

// 获取医生 ID
$user_id = $_SESSION['user_id'];
$sql = "SELECT id FROM doctors WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    die("Doctor record not found!");
}

$doctor_id = $doctor['id']; // 确保获取的是 doctors 表的 ID

// 先計算總病人數
$count_sql = "
    SELECT COUNT(DISTINCT a.patient_email) as total
    FROM appointments a
    WHERE a.doctor_id = ? AND a.status != 'cancelled_by_patient' && a.status != 'cancelled_by_admin' && a.status != 'rejected' && a.status != 'cancelled'
";

if (!empty($search)) {
    $count_sql .= " AND (a.patient_name LIKE ? OR a.patient_email LIKE ? OR a.patient_phone LIKE ?)";
}

$count_stmt = $conn->prepare($count_sql);
if (!empty($search)) {
    $searchParam = "%$search%";
    $count_stmt->bind_param("isss", $doctor_id, $searchParam, $searchParam, $searchParam);
} else {
    $count_stmt->bind_param("i", $doctor_id);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_patients = $total_result->fetch_assoc()['total'];
$count_stmt->close();

// 計算分頁
$total_pages = ceil($total_patients / $patients_per_page);
$start_patient = ($current_page - 1) * $patients_per_page + 1;
$end_patient = min($current_page * $patients_per_page, $total_patients);

// 直接從 appointments 撈 confirmed 的病人資料 (分頁)
$sql = "
    SELECT DISTINCT 
           a.patient_name, 
           a.patient_email, 
           a.patient_phone
    FROM appointments a
    WHERE a.doctor_id = ? AND a.status != 'cancelled_by_patient' && a.status != 'cancelled_by_admin' && a.status != 'rejected' && a.status != 'cancelled'
";

if (!empty($search)) {
    $sql .= " AND (a.patient_name LIKE ? OR a.patient_email LIKE ? OR a.patient_phone LIKE ?)";
}

$sql .= " LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

if (!empty($search)) {
    $searchParam = "%$search%";
    $stmt->bind_param("isssii", $doctor_id, $searchParam, $searchParam, $searchParam, $patients_per_page, $offset);
} else {
    $stmt->bind_param("iii", $doctor_id, $patients_per_page, $offset);
}

$stmt->execute();
$patients = $stmt->get_result();

// Get doctor name for display
$doctor_name = $_SESSION['user_name'] ?? 'Doctor';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records - Doctor Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
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

        /* Search Section */
        .search-section {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        .search-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-lg);
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--gray-50);
        }

        .form-control:focus {
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

        /* Patients Table */
        .patients-section {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .patients-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .patients-title {
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

        /* Action Buttons */
        .btn-info {
            background: linear-gradient(135deg, var(--info), #0891b2);
            border: none;
            border-radius: var(--radius);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Modal Styles */
        .modal {
            z-index: 1060; /* 確保 modal 在 navbar 之上 */
        }
        
        .modal-dialog {
            margin-top: 5rem; /* 給 navbar 留出空間 */
            margin-bottom: 2rem;
        }
        
        .modal-content {
            border: none;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-2xl);
            max-height: calc(100vh - 7rem); /* 限制最大高度 */
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
            padding: 1.5rem 2rem;
            flex-shrink: 0; /* 防止頭部被壓縮 */
        }

        .modal-title {
            font-weight: 600;
        }

        .modal-body {
            padding: 2rem;
            overflow-y: auto; /* 允許內容滾動 */
            max-height: calc(100vh - 12rem); /* 限制 body 最大高度 */
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

        /* Spinner */
        .spinner-border {
            color: var(--primary);
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
            
            .search-section {
                padding: 1.5rem;
            }
            
            .patients-header {
                padding: 1.5rem;
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .table th,
            .table td {
                padding: 1rem;
            }
            
            /* Mobile modal adjustments */
            .modal-dialog {
                margin: 1rem;
                margin-top: 4rem; /* 減少頂部間距在小螢幕上 */
            }
            
            .modal-content {
                max-height: calc(100vh - 5rem);
            }
            
            .modal-body {
                padding: 1.5rem;
                max-height: calc(100vh - 9rem);
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
                <h1 class="page-title">My Patients</h1>
                <p class="page-subtitle">View and manage your confirmed patient records</p>
            </section>

            <!-- Search Section -->
            <section class="search-section fade-in">
                <h3 class="search-title">
                    <i class="fas fa-search"></i>
                    Search Patients
                </h3>
                
                <form method="GET" class="row g-3">
                    <div class="col-md-9">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name, email or phone..." 
                               value="<?= htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </div>
                </form>
            </section>

            <!-- Patients Table -->
            <section class="patients-section fade-in">
                <div class="patients-header">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <h3 class="patients-title">
                            <i class="fas fa-users"></i>
                            Confirmed Patients
                        </h3>
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge bg-light text-dark fs-6">
                                <?php echo $patients->num_rows; ?> of <?= number_format($total_patients) ?> Patients
                            </span>
                            <?php if ($total_pages > 1): ?>
                            <small class="text-white opacity-85">
                                Page <?= $current_page ?> of <?= $total_pages ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($patients->num_rows > 0) { ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-user me-2"></i>Patient Name</th>
                                    <th><i class="fas fa-envelope me-2"></i>Email</th>
                                    <th><i class="fas fa-phone me-2"></i>Phone</th>
                                    <th><i class="fas fa-cogs me-2"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $patients->fetch_assoc()) { ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <strong><?= htmlspecialchars($row['patient_name'] ?? 'N/A'); ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($row['patient_email'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars($row['patient_phone'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if (!empty($row['patient_email'])) { ?>
                                                <button type="button" class="btn btn-info btn-sm view-history-btn" 
                                                        data-email="<?= htmlspecialchars($row['patient_email']); ?>" 
                                                        data-name="<?= htmlspecialchars($row['patient_name']); ?>">
                                                    <i class="fas fa-history me-1"></i>
                                                    View Medical Record History
                                                </button>
                                                <a href="patient_history.php?patient_email=<?= urlencode($row['patient_email']); ?>" 
                                                   class="btn btn-success btn-sm ms-1">
                                                    <i class="fas fa-plus me-1"></i>
                                                    Add Record
                                                </a>
                                            <?php } else { ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-times-circle me-1"></i>
                                                    No Email Available
                                                </span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?= $start_patient ?>-<?= $end_patient ?> of <?= number_format($total_patients) ?> patients
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
                <?php } else { ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 class="empty-title">No Confirmed Patients Found</h4>
                        <p class="empty-description">
                            <?php if (!empty($search)): ?>
                                No patients found matching your search criteria. Try adjusting your search terms.
                            <?php else: ?>
                                You don't have any confirmed patients yet. Patients will appear here after their appointments are confirmed.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php } ?>
            </section>
        </div>
    </main>

    <!-- Medical History Modal -->
    <div class="modal fade" id="medicalHistoryModal" tabindex="-1" aria-labelledby="medicalHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="medicalHistoryModalLabel">
                        <i class="fas fa-file-medical-alt me-2"></i>
                        Medical History
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="medical-history-content">
                    <div style="overflow-x:auto; max-width:100%;">
                        <!-- Medical history table will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

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

        // Original medical history functionality
        $(document).ready(function() {
            // Global function for loading medical history with pagination
            window.loadMedicalHistory = function(email, page = 1) {
                $.ajax({
                    url: 'get_medical_history.php',
                    type: 'GET',
                    data: { 
                        patient_email: email,
                        page: page
                    },
                    success: function(data) {
                        $('#medical-history-content').html('<div style="overflow-x:auto; max-width:100%;">'+data+'</div>');
                    },
                    error: function() {
                        $('#medical-history-content').html('<div style="overflow-x:auto; max-width:100%;"><div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Failed to load medical history.</div></div>');
                    }
                });
            };
            
            $('.view-history-btn').on('click', function() {
                var email = $(this).data('email');
                var name = $(this).data('name');
                $('#medicalHistoryModalLabel').html('<i class="fas fa-file-medical-alt me-2"></i>Medical History for ' + name + ' (' + email + ')');
                $('#medical-history-content').html('<div style="overflow-x:auto; max-width:100%;"><div class="text-center py-4"><span class="spinner-border"></span> <span class="ms-2">Loading...</span></div></div>');
                $('#medicalHistoryModal').modal('show');
                loadMedicalHistory(email, 1);
            });
        });
    </script>
</body>
</html>