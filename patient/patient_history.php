<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: ../patient/login.php");
    exit;
}

$patient_id = $_SESSION['user_id'];
$search_query = "";

// 取得病人資訊
$patient_query = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$patient_query->bind_param("i", $patient_id);
$patient_query->execute();
$patient_result = $patient_query->get_result();
$patient_info = $patient_result->fetch_assoc();
$patient_name  = $patient_info['name'];
$patient_email = $patient_info['email'];

// 分頁設定
$records_per_page = 8; // 每頁顯示病歷記錄數
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

// 日期限制設定 (預設顯示最近12個月)
$show_all_records = isset($_GET['show_all']) && $_GET['show_all'] === '1';
$default_limit_months = 12;

$search_query = "";
$date_from = "";
$date_to = "";
$doctor_filter = "";

// 搜尋參數
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}
if (isset($_GET['date_from'])) {
    $date_from = trim($_GET['date_from']);
}
if (isset($_GET['date_to'])) {
    $date_to = trim($_GET['date_to']);
}
if (isset($_GET['doctor'])) {
    $doctor_filter = trim($_GET['doctor']);
}

// 建立動態 WHERE 條件
$where_conditions = ["mr.patient_email = ?"];
$params = [$patient_email];
$param_types = "s";

// 預設日期限制 (如果沒有指定顯示全部記錄且沒有自訂日期範圍)
if (!$show_all_records && empty($date_from) && empty($date_to)) {
    $default_from_date = date('Y-m-d', strtotime("-{$default_limit_months} months"));
    $where_conditions[] = "mr.visit_date >= ?";
    $params[] = $default_from_date;
    $param_types .= "s";
}

// 醫生或症狀搜索
if (!empty($search_query)) {
    $where_conditions[] = "(d.name LIKE ? OR mr.diagnosis LIKE ? OR mr.chief_complaint LIKE ? OR mr.treatment_plan LIKE ?)";
    $params[] = "%" . $search_query . "%";
    $params[] = "%" . $search_query . "%";
    $params[] = "%" . $search_query . "%";
    $params[] = "%" . $search_query . "%";
    $param_types .= "ssss";
}

// 特定醫生過濾
if (!empty($doctor_filter)) {
    $where_conditions[] = "d.name LIKE ?";
    $params[] = "%" . $doctor_filter . "%";
    $param_types .= "s";
}

// 日期範圍過濾
if (!empty($date_from)) {
    $where_conditions[] = "mr.visit_date >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}
if (!empty($date_to)) {
    $where_conditions[] = "mr.visit_date <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

// 先計算總記錄數
$count_sql = "SELECT COUNT(*) as total FROM medical_records mr
             JOIN doctors d ON mr.doctor_id = d.id
             WHERE " . implode(" AND ", $where_conditions);
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_records = $total_result->fetch_assoc()['total'];
$count_stmt->close();

// 計算分頁
$total_pages = ceil($total_records / $records_per_page);
$start_record = ($current_page - 1) * $records_per_page + 1;
$end_record = min($current_page * $records_per_page, $total_records);

// 查詢病歷 (更多欄位) - 分頁
$sql = "
        SELECT mr.id, mr.visit_date, mr.created_at, mr.report_generated,
                     d.name as doctor_name,
                     mr.chief_complaint, mr.diagnosis, mr.treatment_plan,
                     mr.prescription, mr.progress_notes
        FROM medical_records mr
        JOIN doctors d ON mr.doctor_id = d.id
        WHERE " . implode(" AND ", $where_conditions) . "
        ORDER BY mr.created_at DESC
        LIMIT ? OFFSET ?
";
$params[] = $records_per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$records_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Medical History - Dental Clinic</title>
    
    <!-- Bootstrap 5.3.0 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6.4.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --clinic-primary: #2d5aa0;
            --clinic-secondary: #4a9396;
            --clinic-accent: #84c69b;
            --clinic-light: #f1f8e8;
            --clinic-warm: #f9f7ef;
            --clinic-text: #2c3e50;
            --clinic-muted: #7f8c8d;
            --clinic-success: #27ae60;
            --clinic-warning: #f39c12;
            --clinic-danger: #e74c3c;
            --clinic-white: #ffffff;
            --clinic-shadow: 0 2px 10px rgba(45, 90, 160, 0.1);
            --clinic-shadow-hover: 0 8px 25px rgba(45, 90, 160, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--clinic-light);
            color: var(--clinic-text);
            line-height: 1.6;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--clinic-primary) 0%, var(--clinic-secondary) 100%);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2rem;
            position: relative;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
                radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.1) 2px, transparent 2px);
            background-size: 40px 40px;
        }

        .page-header .container {
            position: relative;
            z-index: 1;
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-align: center;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .page-header p {
            font-size: 1rem;
            opacity: 0.95;
            text-align: center;
        }

        .patient-info {
            font-size: 1.1rem;
            text-align: center;
            margin-top: 0.5rem;
            opacity: 0.9;
        }

        .history-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .search-card {
            background: var(--clinic-white);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--clinic-shadow);
            border: 1px solid rgba(45, 90, 160, 0.08);
        }

        .search-form {
            display: block;
        }

        .search-input, .form-select {
            height: 2.4rem;
            border: 2px solid #e8ecef;
            border-radius: 8px;
            padding: 0 0.75rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: var(--clinic-warm);
        }

        .search-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--clinic-primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(45, 90, 160, 0.1);
        }

        .search-btn, .reset-btn {
            height: 2.4rem;
            padding: 0 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .search-btn {
            background: var(--clinic-primary);
            color: white;
        }

        .search-btn:hover {
            background: #234a87;
            transform: translateY(-1px);
        }

        .btn-outline-secondary {
            height: 2.4rem;
            padding: 0 1rem;
            border: 2px solid #6c757d;
            color: #6c757d;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
        }

        .quick-filter {
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .quick-filter:hover {
            transform: translateY(-1px);
        }

        .quick-filter.active {
            background: var(--clinic-primary);
            border-color: var(--clinic-primary);
            color: white;
        }

        .history-card {
            background: var(--clinic-white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--clinic-shadow);
            border: 1px solid rgba(45, 90, 160, 0.08);
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--clinic-secondary) 0%, var(--clinic-accent) 100%);
            color: white;
            padding: 1.2rem 1.5rem;
        }

        .card-header-custom h3 {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .records-grid {
            padding: 1.5rem;
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.2rem;
        }

        .record-item {
            background: var(--clinic-warm);
            border-radius: 10px;
            padding: 1.2rem;
            border-left: 4px solid var(--clinic-primary);
            transition: all 0.3s ease;
            position: relative;
            border: 1px solid rgba(45, 90, 160, 0.05);
        }

        .record-item:hover {
            transform: translateX(3px);
            box-shadow: var(--clinic-shadow-hover);
            background: white;
        }

        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .visit-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--clinic-text);
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .doctor-name {
            color: var(--clinic-secondary);
            font-weight: 500;
            font-size: 0.9rem;
            background: rgba(74, 147, 150, 0.1);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            display: inline-block;
        }

        .visit-date {
            font-size: 1rem;
            font-weight: 600;
            color: var(--clinic-primary);
            background: rgba(45, 90, 160, 0.1);
            padding: 0.3rem 0.8rem;
            border-radius: 8px;
            border: 1px solid rgba(45, 90, 160, 0.2);
        }

        .record-details {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-section {
            background: var(--clinic-white);
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid rgba(45, 90, 160, 0.05);
        }

        .detail-title {
            font-weight: 600;
            color: var(--clinic-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .detail-content {
            color: var(--clinic-text);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .services-billing {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            margin-top: 1rem;
            padding: 1rem;
            background: linear-gradient(135deg, rgba(45, 90, 160, 0.05) 0%, rgba(74, 147, 150, 0.05) 100%);
            border-radius: 8px;
            border: 1px solid rgba(45, 90, 160, 0.1);
        }

        .services-list {
            color: var(--clinic-text);
            font-size: 0.9rem;
        }

        .billing-amount {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--clinic-primary);
            text-align: right;
        }

        .no-records {
            text-align: center;
            padding: 3rem 2rem;
            background: var(--clinic-white);
            border-radius: 12px;
            box-shadow: var(--clinic-shadow);
            border: 1px solid rgba(45, 90, 160, 0.08);
        }

        .no-records i {
            font-size: 3rem;
            color: var(--clinic-muted);
            margin-bottom: 1rem;
            opacity: 0.7;
        }

        .no-records h3 {
            color: var(--clinic-text);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .no-records p {
            color: var(--clinic-muted);
        }

        .clinic-badge {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
            padding: 0.15rem 0.5rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .record-item::before {
            content: '';
            position: absolute;
            top: 0.8rem;
            right: 0.8rem;
            width: 12px;
            height: 12px;
            background: var(--clinic-accent);
            border-radius: 50%;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.8rem;
            }

            .history-container {
                padding: 0 0.5rem;
            }

            .search-form {
                flex-direction: column;
                gap: 0.5rem;
            }

            .search-input, .search-btn, .reset-btn {
                width: 100%;
            }

            .record-header {
                flex-direction: column;
                gap: 0.8rem;
            }

            .services-billing {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .records-grid {
                padding: 1rem;
            }

            .detail-section {
                padding: 0.8rem;
            }
        }

        .record-item {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .record-item:nth-child(1) { animation-delay: 0.1s; }
        .record-item:nth-child(2) { animation-delay: 0.2s; }
        .record-item:nth-child(3) { animation-delay: 0.3s; }
        .record-item:nth-child(4) { animation-delay: 0.4s; }
        .record-item:nth-child(5) { animation-delay: 0.5s; }

        /* Medical Report Button Styles */
        .view-report-btn {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .view-report-btn:hover {
            background: linear-gradient(135deg, #234a87, #3d7a7d);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(45, 90, 160, 0.3);
        }

        .report-unavailable {
            color: var(--clinic-muted);
            font-size: 0.85rem;
            font-style: italic;
            opacity: 0.7;
        }
        
        /* Pagination Styles */
        .pagination-container {
            background: var(--clinic-warm);
            padding: 1.5rem;
            border-top: 1px solid rgba(45, 90, 160, 0.08);
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            background: var(--clinic-white);
            border: 1px solid rgba(45, 90, 160, 0.2);
            color: var(--clinic-primary);
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            min-width: 40px;
            justify-content: center;
        }
        
        .page-btn:hover {
            background: var(--clinic-primary);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(45, 90, 160, 0.3);
        }
        
        .page-btn.active {
            background: var(--clinic-primary);
            color: white;
            font-weight: 600;
        }
        
        .page-btn.disabled {
            background: #f8f9fa;
            color: #adb5bd;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-info {
            text-align: center;
            color: var(--clinic-muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-history"></i> My Medical History</h1>
            <p>Complete record of your medical visits and treatments</p>
            <div class="patient-info">
                <i class="fas fa-user"></i> <?= htmlspecialchars($patient_name) ?> 
                (<i class="fas fa-envelope"></i> <?= htmlspecialchars($patient_email) ?>)
            </div>
        </div>
    </div>

    <div class="history-container">
        <!-- Search Section -->
        <div class="search-card">
            <form method="GET" class="search-form">
                <div class="row g-3">
                    <!-- General Search -->
                    <div class="col-md-4">
                        <label for="search" class="form-label text-muted small mb-1">
                            <i class="fas fa-search me-1"></i>Search Records
                        </label>
                        <input type="text" 
                               id="search"
                               name="search" 
                               class="form-control search-input"
                               placeholder="Diagnosis, complaint, treatment..." 
                               value="<?= htmlspecialchars($search_query) ?>">
                    </div>
                    
                    <!-- Doctor Filter -->
                    <div class="col-md-2">
                        <label for="doctor" class="form-label text-muted small mb-1">
                            <i class="fas fa-user-md me-1"></i>Doctor
                        </label>
                        <input type="text" 
                               id="doctor"
                               name="doctor" 
                               class="form-control search-input"
                               placeholder="Doctor name..." 
                               value="<?= htmlspecialchars($doctor_filter) ?>">
                    </div>
                    
                    <!-- Date From -->
                    <div class="col-md-2">
                        <label for="date_from" class="form-label text-muted small mb-1">
                            <i class="fas fa-calendar me-1"></i>From Date
                        </label>
                        <input type="date" 
                               id="date_from"
                               name="date_from" 
                               class="form-control search-input" 
                               value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    
                    <!-- Date To -->
                    <div class="col-md-2">
                        <label for="date_to" class="form-label text-muted small mb-1">
                            <i class="fas fa-calendar me-1"></i>To Date
                        </label>
                        <input type="date" 
                               id="date_to"
                               name="date_to" 
                               class="form-control search-input" 
                               value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    
                    <!-- Search Buttons -->
                    <div class="col-md-2">
                        <label class="form-label text-muted small mb-1 d-block">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn search-btn flex-fill">
                                <i class="fas fa-search"></i>Search
                            </button>
                            <a href="patient_history.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Date Filters -->
                <div class="row mt-3">
                    <div class="col-12">
                        <small class="text-muted me-2">Quick filters:</small>
                        <button type="button" class="btn btn-sm btn-outline-primary me-2 quick-filter" data-filter="last-month">Last Month</button>
                        <button type="button" class="btn btn-sm btn-outline-primary me-2 quick-filter" data-filter="last-3-months">Last 3 Months</button>
                        <button type="button" class="btn btn-sm btn-outline-primary me-2 quick-filter" data-filter="last-6-months">Last 6 Months</button>
                        <button type="button" class="btn btn-sm btn-outline-primary me-2 quick-filter" data-filter="this-year">This Year</button>
                        <button type="button" class="btn btn-sm btn-outline-primary quick-filter" data-filter="all-time">All Records</button>
                    </div>
                </div>
                
                <!-- Record Display Options -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">Display options:</small>
                                <?php if (!$show_all_records): ?>
                                    <span class="badge bg-info ms-2">
                                        <i class="fas fa-calendar-alt"></i> Showing last <?= $default_limit_months ?> months
                                    </span>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['show_all' => '1'])) ?>" 
                                       class="btn btn-sm btn-outline-warning ms-2">
                                        <i class="fas fa-history"></i> Show All Records
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark ms-2">
                                        <i class="fas fa-infinity"></i> Showing all records
                                    </span>
                                    <a href="?<?= http_build_query(array_diff_key($_GET, ['show_all' => ''])) ?>" 
                                       class="btn btn-sm btn-outline-info ms-2">
                                        <i class="fas fa-filter"></i> Show Recent Only
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php if ($total_records > 0): ?>
                            <small class="text-muted">
                                <?= number_format($total_records) ?> total records found
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($records_result->num_rows > 0) { ?>
            <div class="history-card">
                <div class="card-header-custom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">
                            <i class="fas fa-file-medical-alt"></i>
                            Medical Records
                            <span class="clinic-badge"><?= $records_result->num_rows ?> of <?= number_format($total_records) ?></span>
                        </h3>
                        <?php if ($total_pages > 1): ?>
                        <small class="text-white opacity-75">
                            Page <?= $current_page ?> of <?= $total_pages ?> 
                            (Showing <?= $start_record ?>-<?= $end_record ?>)
                        </small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="records-grid">
                    <?php while ($row = $records_result->fetch_assoc()) { ?>
                        <?php
                        // Query medical_record_services to get all services
                        $service_stmt = $conn->prepare("SELECT s.name, s.price FROM medical_record_services mrs JOIN services s ON mrs.service_id = s.id WHERE mrs.medical_record_id = ?");
                        $service_stmt->bind_param("i", $row['id']);
                        $service_stmt->execute();
                        $service_res = $service_stmt->get_result();
                        $service_names = [];
                        $total_service_price = 0;
                        while ($srv = $service_res->fetch_assoc()) {
                            $service_names[] = htmlspecialchars($srv['name']);
                            $total_service_price += $srv['price'];
                        }
                        $services_str = $service_names ? implode(', ', $service_names) : 'No services recorded';
                        $billing_str = $total_service_price > 0 ? ('RM ' . number_format($total_service_price, 2)) : 'No charges';
                        ?>
                        
                        <div class="record-item">
                            <div class="record-header">
                                <div class="visit-info">
                                    <h4>
                                        <i class="fas fa-calendar-day"></i>
                                        Visit Record
                                    </h4>
                                    <div class="doctor-name">
                                        <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($row['doctor_name']) ?>
                                    </div>
                                </div>
                                
                                <div class="visit-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('M j, Y', strtotime($row['visit_date'])) ?>
                                </div>
                            </div>

                            <div class="record-details">
                                <div class="detail-section">
                                    <div class="detail-title">
                                        <i class="fas fa-comment-medical"></i> Chief Complaint
                                    </div>
                                    <div class="detail-content">
                                        <?= htmlspecialchars($row['chief_complaint']) ?>
                                    </div>
                                </div>

                                <div class="detail-section">
                                    <div class="detail-title">
                                        <i class="fas fa-diagnoses"></i> Diagnosis
                                    </div>
                                    <div class="detail-content">
                                        <?= htmlspecialchars($row['diagnosis']) ?>
                                    </div>
                                </div>

                                <div class="detail-section">
                                    <div class="detail-title">
                                        <i class="fas fa-clipboard-list"></i> Treatment Plan
                                    </div>
                                    <div class="detail-content">
                                        <?= htmlspecialchars($row['treatment_plan']) ?>
                                    </div>
                                </div>

                                <?php if (!empty($row['prescription'])): ?>
                                <div class="detail-section">
                                    <div class="detail-title">
                                        <i class="fas fa-prescription-bottle-alt"></i> Prescription
                                    </div>
                                    <div class="detail-content">
                                        <?= htmlspecialchars($row['prescription']) ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($row['progress_notes'])): ?>
                                <div class="detail-section">
                                    <div class="detail-title">
                                        <i class="fas fa-notes-medical"></i> Progress Notes
                                    </div>
                                    <div class="detail-content">
                                        <?= htmlspecialchars($row['progress_notes']) ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="services-billing">
                                <div class="services-section">
                                    <div class="detail-title">
                                        <i class="fas fa-concierge-bell"></i> Services Provided
                                    </div>
                                    <div class="services-list">
                                        <?= $services_str ?>
                                    </div>
                                </div>
                                <div class="billing-section">
                                    <div class="detail-title">
                                        <i class="fas fa-receipt"></i> Total Amount
                                    </div>
                                    <div class="billing-amount">
                                        <?= $billing_str ?>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(45, 90, 160, 0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> 
                                    Record created: <?= date('M j, Y g:i A', strtotime($row['created_at'])) ?>
                                </small>
                                
                                <?php if ($row['report_generated']): ?>
                                <a href="view_medical_report.php?record_id=<?= $row['id'] ?>" 
                                   target="_blank" 
                                   class="view-report-btn">
                                    <i class="fas fa-file-medical-alt"></i> View Medical Report
                                </a>
                                <?php else: ?>
                                <span class="report-unavailable">
                                    <i class="fas fa-info-circle"></i> Report not available
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?= $start_record ?>-<?= $end_record ?> of <?= number_format($total_records) ?> medical records
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
            </div>
        <?php } else { ?>
            <div class="no-records">
                <i class="fas fa-file-medical"></i>
                <h3>No Medical Records Found</h3>
                <p>
                    <?php if (!empty($search_query)): ?>
                        No records match your search criteria. Try adjusting your search terms.
                    <?php else: ?>
                        You don't have any medical records yet. Your visit history will appear here after your appointments.
                    <?php endif; ?>
                </p>
            </div>
        <?php } ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enhanced search functionality for medical history
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('#search');
            const doctorInput = document.querySelector('#doctor');
            const searchForm = document.querySelector('.search-form');
            const searchBtn = document.querySelector('.search-btn');
            const dateFromInput = document.querySelector('#date_from');
            const dateToInput = document.querySelector('#date_to');

            // Quick filter functionality
            document.querySelectorAll('.quick-filter').forEach(btn => {
                btn.addEventListener('click', function() {
                    const filter = this.dataset.filter;
                    const today = new Date();
                    
                    // Remove active class from all buttons
                    document.querySelectorAll('.quick-filter').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Clear existing date values
                    dateFromInput.value = '';
                    dateToInput.value = '';
                    
                    switch(filter) {
                        case 'last-month':
                            const lastMonth = new Date(today);
                            lastMonth.setMonth(today.getMonth() - 1);
                            dateFromInput.value = lastMonth.toISOString().split('T')[0];
                            dateToInput.value = today.toISOString().split('T')[0];
                            break;
                            
                        case 'last-3-months':
                            const last3Months = new Date(today);
                            last3Months.setMonth(today.getMonth() - 3);
                            dateFromInput.value = last3Months.toISOString().split('T')[0];
                            dateToInput.value = today.toISOString().split('T')[0];
                            break;
                            
                        case 'last-6-months':
                            const last6Months = new Date(today);
                            last6Months.setMonth(today.getMonth() - 6);
                            dateFromInput.value = last6Months.toISOString().split('T')[0];
                            dateToInput.value = today.toISOString().split('T')[0];
                            break;
                            
                        case 'this-year':
                            const startOfYear = new Date(today.getFullYear(), 0, 1);
                            dateFromInput.value = startOfYear.toISOString().split('T')[0];
                            dateToInput.value = today.toISOString().split('T')[0];
                            break;
                            
                        case 'all-time':
                            // Clear date filters to show all records
                            break;
                    }
                    
                    // Auto-submit the form
                    searchForm.submit();
                });
            });

            // Date validation
            dateFromInput.addEventListener('change', function() {
                if (dateToInput.value && this.value > dateToInput.value) {
                    alert('Start date cannot be later than end date');
                    this.value = '';
                }
            });

            dateToInput.addEventListener('change', function() {
                if (dateFromInput.value && this.value < dateFromInput.value) {
                    alert('End date cannot be earlier than start date');
                    this.value = '';
                }
            });

            // Debounced search
            let searchTimeout;
            [searchInput, doctorInput].forEach(input => {
                if (input) {
                    input.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        document.querySelectorAll('.quick-filter').forEach(b => b.classList.remove('active'));
                        
                        searchTimeout = setTimeout(() => {
                            console.log('Searching for:', this.value);
                        }, 500);
                    });
                }
            });

            // Clear filters functionality
            document.querySelector('.btn-outline-secondary').addEventListener('click', function(e) {
                e.preventDefault();
                
                // Clear all form fields
                if (searchInput) searchInput.value = '';
                if (doctorInput) doctorInput.value = '';
                if (dateFromInput) dateFromInput.value = '';
                if (dateToInput) dateToInput.value = '';
                
                // Remove active states
                document.querySelectorAll('.quick-filter').forEach(b => b.classList.remove('active'));
                
                // Redirect to clear URL
                window.location.href = 'patient_history.php';
            });

            // Search form enhancement
            searchForm.addEventListener('submit', function() {
                searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                searchBtn.disabled = true;
            });

            // Set active state for current filters
            const urlParams = new URLSearchParams(window.location.search);
            const currentDateFrom = urlParams.get('date_from');
            const currentDateTo = urlParams.get('date_to');
            
            // Animation for record items
            const recordItems = document.querySelectorAll('.record-item');
            recordItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(30px)';
                item.style.transition = `all 0.6s ease ${index * 0.1}s`;
                
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, 100);
            });

            // Add hover effects for record items
            recordItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.borderLeftColor = 'var(--clinic-secondary)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.borderLeftColor = 'var(--clinic-primary)';
                });
            });

            // Enhanced report button functionality
            document.querySelectorAll('.view-report-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    // Add loading state
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading Report...';
                    
                    // Restore after a short delay (the report will open in new tab)
                    setTimeout(() => {
                        this.innerHTML = originalText;
                    }, 2000);
                });
            });
        });
    </script>
</body>
</html>
