<?php
session_start();
require_once __DIR__ . "/../db.php";

// 只有病患可以進來
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['user_id'];

// 找出這個病患的 email (因為 billing 是用 email 存的)
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$patient_email = $user['email'] ?? '';
$stmt->close();

// 分頁設定
$records_per_page = 12; // 每頁顯示記錄數
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

// 日期限制設定 (預設顯示最近12個月)
$show_all_records = isset($_GET['show_all']) && $_GET['show_all'] === '1';
$default_limit_months = 12;

// 搜尋條件
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : "";
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : "";
$payment_method = isset($_GET['payment_method']) ? trim($_GET['payment_method']) : "";

// 建立動態 WHERE 條件
$where_conditions = ["patient_email = ?"];
$params = [$patient_email];
$param_types = "s";

// 預設日期限制 (如果沒有指定顯示全部記錄且沒有自訂日期範圍)
if (!$show_all_records && empty($date_from) && empty($date_to)) {
    $default_from_date = date('Y-m-d', strtotime("-{$default_limit_months} months"));
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $default_from_date;
    $param_types .= "s";
}

// 服務搜索
if (!empty($search)) {
    $where_conditions[] = "(service LIKE ? OR amount LIKE ?)";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
    $param_types .= "ss";
}

// 付款方式過濾
if (!empty($payment_method)) {
    $where_conditions[] = "payment_method = ?";
    $params[] = $payment_method;
    $param_types .= "s";
}

// 日期範圍過濾
if (!empty($date_from)) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}
if (!empty($date_to)) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

// 先計算總記錄數
$count_sql = "SELECT COUNT(*) as total FROM billing 
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

// 查詢病患自己的 billing 紀錄 (分頁)
$sql = "SELECT * FROM billing 
        WHERE " . implode(" AND ", $where_conditions) . "
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$records = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Billing Records - Dental Clinic</title>
    
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

        .billing-container {
            max-width: 1100px;
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

        .search-btn {
            height: 2.4rem;
            padding: 0 1rem;
            background: var(--clinic-primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
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

        .billing-card {
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

        .billing-summary {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(45, 90, 160, 0.08);
            background: var(--clinic-warm);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .summary-item {
            text-align: center;
            padding: 1rem;
            background: var(--clinic-white);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(45, 90, 160, 0.05);
        }

        .summary-item h4 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--clinic-primary);
            margin-bottom: 0.5rem;
        }

        .summary-item p {
            color: var(--clinic-muted);
            margin: 0;
            font-size: 0.9rem;
        }

        .records-grid {
            padding: 1.5rem;
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
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
            margin-bottom: 0.8rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .service-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--clinic-text);
            margin-bottom: 0.3rem;
        }

        .service-category {
            color: var(--clinic-secondary);
            font-weight: 500;
            font-size: 0.85rem;
            background: rgba(74, 147, 150, 0.1);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            display: inline-block;
        }

        .amount-display {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--clinic-primary);
            background: rgba(45, 90, 160, 0.1);
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            border: 1px solid rgba(45, 90, 160, 0.2);
        }

        .record-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.8rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--clinic-muted);
            font-size: 0.9rem;
        }

        .detail-item i {
            color: var(--clinic-secondary);
            width: 14px;
            font-size: 0.85rem;
        }

        .payment-method-badge {
            padding: 0.3rem 0.7rem;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .payment-cash {
            background: rgba(39, 174, 96, 0.1);
            color: var(--clinic-success);
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .payment-card {
            background: rgba(45, 90, 160, 0.1);
            color: var(--clinic-primary);
            border: 1px solid rgba(45, 90, 160, 0.2);
        }

        .payment-online {
            background: rgba(132, 198, 155, 0.1);
            color: var(--clinic-accent);
            border: 1px solid rgba(132, 198, 155, 0.2);
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
            margin-bottom: 1.5rem;
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

        /* Record item decorative element */
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

            .billing-container {
                padding: 0 0.5rem;
            }

            .search-form {
                flex-direction: column;
            }

            .search-input {
                width: 100%;
            }

            .record-header {
                flex-direction: column;
                gap: 0.8rem;
            }

            .record-details {
                grid-template-columns: 1fr;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .records-grid {
                padding: 1rem;
            }
        }

        /* Clinic-inspired animations */
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

        /* View Report Button Styles */
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
            <h1><i class="fas fa-file-invoice-dollar"></i> My Billing Records</h1>
            <p>View and manage your payment history and invoices</p>
        </div>
    </div>

    <div class="billing-container">
        <!-- Search Section -->
        <div class="search-card">
            <form method="GET" class="search-form">
                <div class="row g-3">
                    <!-- Service Search -->
                    <div class="col-md-3">
                        <label for="search" class="form-label text-muted small mb-1">
                            <i class="fas fa-search me-1"></i>Service or Amount
                        </label>
                        <input type="text" 
                               id="search"
                               name="search" 
                               class="form-control search-input" 
                               placeholder="Search service or amount..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    
                    <!-- Payment Method Filter -->
                    <div class="col-md-2">
                        <label for="payment_method" class="form-label text-muted small mb-1">
                            <i class="fas fa-credit-card me-1"></i>Payment Method
                        </label>
                        <select id="payment_method" name="payment_method" class="form-select search-input">
                            <option value="">All Methods</option>
                            <option value="Cash" <?= $payment_method === 'Cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="Credit Card" <?= $payment_method === 'Credit Card' ? 'selected' : '' ?>>Credit Card</option>
                            <option value="Debit Card" <?= $payment_method === 'Debit Card' ? 'selected' : '' ?>>Debit Card</option>
                            <option value="Online Payment" <?= $payment_method === 'Online Payment' ? 'selected' : '' ?>>Online Payment</option>
                            <option value="Bank Transfer" <?= $payment_method === 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                            <option value="Insurance" <?= $payment_method === 'Insurance' ? 'selected' : '' ?>>Insurance</option>
                        </select>
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
                    <div class="col-md-3">
                        <label class="form-label text-muted small mb-1 d-block">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn search-btn flex-fill">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <a href="billing.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
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
                
                <!-- Quick Date Filters -->
                <div class="row mt-3">
                    <div class="col-12">
                        <small class="text-muted me-2">Quick filters:</small>
                        <button type="button" class="btn btn-sm btn-outline-primary me-2 quick-filter" data-filter="today">Today</button>
                        <button type="button" class="btn btn-sm btn-outline-primary me-2 quick-filter" data-filter="this-week">This Week</button>
                        <button type="button" class="btn btn-sm btn-outline-primary me-2 quick-filter" data-filter="this-month">This Month</button>
                        <button type="button" class="btn btn-sm btn-outline-primary me-2 quick-filter" data-filter="last-month">Last Month</button>
                        <button type="button" class="btn btn-sm btn-outline-primary quick-filter" data-filter="this-year">This Year</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($records->num_rows > 0) { ?>
            <?php
            // Calculate totals for summary
            $total_amount = 0;
            $record_count = 0;
            $payment_methods = [];
            
            // Store records in array for display and calculate totals
            $billing_records = [];
            while ($row = $records->fetch_assoc()) {
                $billing_records[] = $row;
                $total_amount += $row['amount'];
                $record_count++;
                $payment_methods[$row['payment_method']] = ($payment_methods[$row['payment_method']] ?? 0) + 1;
            }
            ?>
            
            <div class="billing-card">
                <div class="card-header-custom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">
                            <i class="fas fa-receipt"></i>
                            Billing Records
                            <span class="clinic-badge"><?= $record_count ?> of <?= number_format($total_records) ?></span>
                        </h3>
                        <?php if ($total_pages > 1): ?>
                        <small class="text-white opacity-75">
                            Page <?= $current_page ?> of <?= $total_pages ?> 
                            (Showing <?= $start_record ?>-<?= $end_record ?>)
                        </small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Summary Section -->
                <div class="billing-summary">
                    <div class="summary-grid">
                        <div class="summary-item">
                            <h4>RM <?= number_format($total_amount, 2) ?></h4>
                            <p><i class="fas fa-dollar-sign"></i> Total Amount</p>
                        </div>
                        <div class="summary-item">
                            <h4><?= $record_count ?></h4>
                            <p><i class="fas fa-file-invoice"></i> Total Records</p>
                        </div>
                        <div class="summary-item">
                            <h4>RM <?= number_format($total_amount / $record_count, 2) ?></h4>
                            <p><i class="fas fa-calculator"></i> Average Amount</p>
                        </div>
                    </div>
                </div>

                <div class="records-grid">
                    <?php foreach ($billing_records as $row): ?>
                        <div class="record-item">
                            <div class="record-header">
                                <div class="service-info">
                                    <h4>
                                        <i class="fas fa-tooth"></i>
                                        <?= htmlspecialchars($row['service']) ?>
                                    </h4>
                                    <div class="service-category">
                                        <i class="fas fa-tag"></i> Medical Service
                                    </div>
                                </div>
                                
                                <div class="amount-display">
                                    RM <?= number_format($row['amount'], 2) ?>
                                </div>
                            </div>

                            <div class="record-details">
                                <div class="detail-item">
                                    <i class="fas fa-credit-card"></i>
                                    <span class="payment-method-badge payment-<?= strtolower(str_replace(' ', '-', $row['payment_method'])) ?>">
                                        <?= htmlspecialchars($row['payment_method']) ?>
                                    </span>
                                </div>
                                
                                <div class="detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?= date('l, F j, Y', strtotime($row['created_at'])) ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?= date('g:i A', strtotime($row['created_at'])) ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Invoice ID: #<?= str_pad(abs(crc32($row['created_at'] . $row['service'])), 6, '0', STR_PAD_LEFT) ?></span>
                                </div>
                            </div>
                            
                            <!-- View Report Button -->
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(45, 90, 160, 0.1); text-align: right;">
                                <a href="view_billing_report.php?billing_id=<?= $row['id'] ?>" 
                                   target="_blank" 
                                   class="view-report-btn">
                                    <i class="fas fa-file-invoice"></i> View Billing Report
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?= $start_record ?>-<?= $end_record ?> of <?= number_format($total_records) ?> records
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
                <i class="fas fa-file-invoice"></i>
                <h3>No Billing Records Found</h3>
                <p>You don't have any billing records yet. When you receive services, your invoices will appear here.</p>
            </div>
        <?php } ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enhanced search functionality for billing records
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('#search');
            const paymentMethodSelect = document.querySelector('#payment_method');
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
                        case 'today':
                            const todayStr = today.toISOString().split('T')[0];
                            dateFromInput.value = todayStr;
                            dateToInput.value = todayStr;
                            break;
                            
                        case 'this-week':
                            const startOfWeek = new Date(today);
                            startOfWeek.setDate(today.getDate() - today.getDay());
                            const endOfWeek = new Date(startOfWeek);
                            endOfWeek.setDate(startOfWeek.getDate() + 6);
                            
                            dateFromInput.value = startOfWeek.toISOString().split('T')[0];
                            dateToInput.value = endOfWeek.toISOString().split('T')[0];
                            break;
                            
                        case 'this-month':
                            const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                            const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                            
                            dateFromInput.value = startOfMonth.toISOString().split('T')[0];
                            dateToInput.value = endOfMonth.toISOString().split('T')[0];
                            break;
                            
                        case 'last-month':
                            const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                            const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
                            
                            dateFromInput.value = lastMonthStart.toISOString().split('T')[0];
                            dateToInput.value = lastMonthEnd.toISOString().split('T')[0];
                            break;
                            
                        case 'this-year':
                            const startOfYear = new Date(today.getFullYear(), 0, 1);
                            
                            dateFromInput.value = startOfYear.toISOString().split('T')[0];
                            dateToInput.value = today.toISOString().split('T')[0];
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

            // Debounced search for service/amount
            let searchTimeout;
            [searchInput].forEach(input => {
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

            // Clear search states when payment method changes
            if (paymentMethodSelect) {
                paymentMethodSelect.addEventListener('change', function() {
                    document.querySelectorAll('.quick-filter').forEach(b => b.classList.remove('active'));
                });
            }

            // Clear filters functionality
            document.querySelector('.btn-outline-secondary').addEventListener('click', function(e) {
                e.preventDefault();
                
                // Clear all form fields
                if (searchInput) searchInput.value = '';
                if (paymentMethodSelect) paymentMethodSelect.value = '';
                if (dateFromInput) dateFromInput.value = '';
                if (dateToInput) dateToInput.value = '';
                
                // Remove active states
                document.querySelectorAll('.quick-filter').forEach(b => b.classList.remove('active'));
                
                // Redirect to clear URL
                window.location.href = 'billing.php';
            });

            // Search form enhancement
            searchForm.addEventListener('submit', function() {
                searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                searchBtn.disabled = true;
            });

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

            // Payment method badge styling based on type
            document.querySelectorAll('.payment-method-badge').forEach(badge => {
                const method = badge.textContent.toLowerCase();
                if (method.includes('cash')) {
                    badge.classList.add('payment-cash');
                } else if (method.includes('card')) {
                    badge.classList.add('payment-card');
                } else if (method.includes('online') || method.includes('transfer')) {
                    badge.classList.add('payment-online');
                }
            });
        });
    </script>
</body>
</html>
