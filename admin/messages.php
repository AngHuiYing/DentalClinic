<?php
// Start session first, before any output - ensure no whitespace or BOM before this tag
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// 连接数据库
$conn = new mysqli("localhost", "root", "", "hospital_management_system");

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// 处理搜索功能
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date_filter']) ? trim($_GET['date_filter']) : '';

$sql = "SELECT * FROM messages";
$conditions = [];

if (!empty($search)) {
    $conditions[] = "(name LIKE '%$search%' OR email LIKE '%$search%' OR tel LIKE '%$search%' OR message LIKE '%$search%')";
}

if (!empty($date_filter)) {
    switch ($date_filter) {
        case 'today':
            $conditions[] = "DATE(created_at) = CURDATE()";
            break;
        case 'yesterday':
            $conditions[] = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $conditions[] = "YEARWEEK(created_at) = YEARWEEK(CURDATE())";
            break;
        case 'this_month':
            $conditions[] = "YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
            break;
        default:
            // Custom date (YYYY-MM-DD)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_filter)) {
                $conditions[] = "DATE(created_at) = '$date_filter'";
            }
            break;
    }
}

// 分頁設定
$messages_per_page = 20; // 每頁顯示消息數
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $messages_per_page;

// 先計算總消息數
$count_sql = "SELECT COUNT(*) as total FROM messages";
if (!empty($conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $conditions);
}
$count_result = $conn->query($count_sql);
$total_messages = $count_result->fetch_assoc()['total'];

// 計算分頁
$total_pages = ceil($total_messages / $messages_per_page);
$start_message = ($current_page - 1) * $messages_per_page + 1;
$end_message = min($current_page * $messages_per_page, $total_messages);

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY created_at DESC LIMIT $messages_per_page OFFSET $offset";

$result = $conn->query($sql);
$num_rows = $result->num_rows; // 获取结果行数
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💬 Messages Management - Green Life Dental Clinic</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Modern Medical Color Palette */
            --primary-color: #0ea5e9;
            --primary-dark: #0284c7;
            --secondary-color: #22d3ee;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --light-bg: #f8fafc;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            
            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-2xl: 2rem;
            
            /* Spacing */
            --space-xs: 0.5rem;
            --space-sm: 1rem;
            --space-md: 1.5rem;
            --space-lg: 2rem;
            --space-xl: 3rem;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--light-bg) 0%, var(--gray-50) 100%);
            color: var(--gray-700);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        /* Main Container */
        .main-container {
            background: var(--white);
            margin: 2rem auto;
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            position: relative;
            max-width: 1200px;
        }

        /* Header Section */
        .header-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: var(--space-xl) var(--space-lg);
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
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            transform: translate(100px, -100px);
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: var(--space-xs);
            display: flex;
            align-items: center;
            gap: var(--space-md);
            color: white !important;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 400;
            margin-bottom: 0;
            color: white !important;
        }

        .title-icon {
            background: rgba(255, 255, 255, 0.2);
            padding: var(--space-md);
            border-radius: var(--radius-lg);
            font-size: 2rem;
        }

        /* Content Section */
        .content-section {
            padding: var(--space-xl) var(--space-lg);
        }

        /* Stats Cards */
        .stats-section {
            margin-bottom: var(--space-xl);
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: var(--space-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: var(--space-md);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: var(--space-xs);
        }

        .stat-label {
            color: var(--gray-600);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.875rem;
        }

        /* Search Section */
        .search-section {
            background: var(--gray-50);
            border-radius: var(--radius-xl);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            border: 1px solid var(--gray-200);
        }

        .search-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: var(--space-md);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .form-control {
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: var(--space-sm) var(--space-md);
            font-size: 1rem;
            transition: all 0.2s ease;
            background: var(--white);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }

        .input-group-text {
            background: var(--white);
            border: 2px solid var(--gray-200);
            border-right: none;
            color: var(--gray-500);
        }

        .input-group .form-control {
            border-left: none;
        }

        .input-group:focus-within .input-group-text {
            border-color: var(--primary-color);
        }

        /* Buttons */
        .btn {
            border-radius: var(--radius-md);
            font-weight: 600;
            padding: var(--space-sm) var(--space-lg);
            transition: all 0.2s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), #0369a1);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        .btn-outline-success {
            border: 2px solid var(--success-color);
            color: var(--success-color);
            background: var(--white);
        }

        .btn-outline-success:hover {
            background: var(--success-color);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn-clear {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 2px solid var(--gray-300);
        }

        .btn-clear:hover {
            background: var(--gray-200);
            color: var(--gray-800);
            border-color: var(--gray-400);
        }

        /* Table Styling */
        .table-container {
            background: var(--white);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
        }

        .table {
            margin-bottom: 0;
            font-size: 0.95rem;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--gray-50), var(--gray-100));
            color: var(--gray-700);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.875rem;
            padding: var(--space-md);
            border: none;
            position: relative;
        }

        .table tbody td {
            padding: var(--space-md);
            border-color: var(--gray-200);
            vertical-align: middle;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(14, 165, 233, 0.05);
            transform: scale(1.001);
        }

        /* Message Content Styling */
        .message-content {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: help;
            position: relative;
        }

        .message-content:hover {
            white-space: normal;
            word-wrap: break-word;
        }

        /* Badge Styling */
        .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius);
            font-weight: 600;
        }

        .badge-primary {
            background: var(--primary-color);
            color: white;
        }

        /* No Results Styling */
        .no-results {
            text-align: center;
            padding: var(--space-xl);
            color: var(--gray-500);
        }

        .no-results-icon {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: var(--space-md);
        }

        .no-results h5 {
            color: var(--gray-600);
            font-weight: 600;
            margin-bottom: var(--space-sm);
        }

        /* Loading Animation */
        .loading-animation {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .loading-animation.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-container {
                margin: 1rem;
                border-radius: var(--radius-xl);
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
                flex-direction: column;
                text-align: center;
                gap: var(--space-sm);
            }
            
            .header-section {
                padding: var(--space-lg);
            }
            
            .content-section {
                padding: var(--space-lg) var(--space-md);
            }
            
            .search-section {
                padding: var(--space-md);
            }
            
            .stat-card {
                margin-bottom: var(--space-md);
            }
            
            .table-responsive {
                border-radius: var(--radius-lg);
            }
            
            .table {
                font-size: 0.875rem;
            }
            
            .table thead th,
            .table tbody td {
                padding: var(--space-sm);
            }
            
            .message-content {
                max-width: 150px;
                font-size: 0.875rem;
            }
        }

        @media (max-width: 576px) {
            .main-container {
                margin: 0.5rem;
                border-radius: var(--radius-lg);
            }
            
            .page-title {
                font-size: 1.75rem;
            }
            
            .content-section {
                padding: var(--space-md) var(--space-sm);
            }
            
            .search-section {
                padding: var(--space-sm);
            }
            
            .table {
                font-size: 0.8rem;
            }
            
            .message-content {
                max-width: 100px;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-400);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-500);
        }

        /* Animation for page load */
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

        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Pagination Styles */
        .pagination-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(59, 130, 246, 0.1);
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            background: var(--bg-white);
            border: 2px solid var(--border-light);
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
            box-shadow: var(--shadow-md);
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
            color: var(--text-muted);
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

    <div class="main-container animate-fade-in">
        <!-- Header Section -->
        <div class="header-section">
            <div class="header-content">
                <h1 class="page-title">
                    <div class="title-icon">
                        <i class="bi bi-chat-left-text"></i>
                    </div>
                    <div>
                        Messages Management
                    </div>
                </h1>
                <p class="page-subtitle">Manage and respond to user inquiries from the contact form</p>
            </div>
        </div>

        <!-- Content Section -->
        <div class="content-section">
            <!-- Statistics Section -->
            <div class="stats-section">
                <div class="row g-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card loading-animation">
                            <div class="stat-icon">
                                <i class="bi bi-envelope-fill"></i>
                            </div>
                            <div class="stat-number"><?php echo $num_rows; ?></div>
                            <div class="stat-label">
                                Showing Messages
                                <?php if ($total_pages > 1): ?>
                                <br><small style="opacity: 0.8; font-size: 0.8em;">Page <?= $current_page ?> of <?= $total_pages ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card loading-animation">
                            <div class="stat-icon">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <?php
                            // Count today's messages
                            $today = date('Y-m-d');
                            $todayQuery = "SELECT COUNT(*) as today_count FROM messages WHERE DATE(created_at) = '$today'";
                            $todayResult = $conn->query($todayQuery);
                            $todayCount = $todayResult->fetch_assoc()['today_count'];
                            ?>
                            <div class="stat-number"><?php echo $todayCount; ?></div>
                            <div class="stat-label">Today's Messages</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card loading-animation">
                            <div class="stat-icon">
                                <i class="bi bi-calendar-week"></i>
                            </div>
                            <?php
                            // Count this week's messages
                            $weekStart = date('Y-m-d', strtotime('monday this week'));
                            $weekQuery = "SELECT COUNT(*) as week_count FROM messages WHERE DATE(created_at) >= '$weekStart'";
                            $weekResult = $conn->query($weekQuery);
                            $weekCount = $weekResult->fetch_assoc()['week_count'];
                            ?>
                            <div class="stat-number"><?php echo $weekCount; ?></div>
                            <div class="stat-label">This Week</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card loading-animation">
                            <div class="stat-icon">
                                <i class="bi bi-reply-fill"></i>
                            </div>
                            <div class="stat-number">
                                <?php echo $num_rows > 0 ? '100%' : '0%'; ?>
                            </div>
                            <div class="stat-label">Response Rate</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search Section -->
            <div class="search-section loading-animation">
                <h3 class="search-title">
                    <i class="bi bi-funnel"></i>
                    Filter Messages
                </h3>
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="search" class="form-label">Search Messages</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" 
                                   id="search"
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Search by name, email, phone, or message..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="date_filter" class="form-label">Filter by Date</label>
                        <select name="date_filter" id="date_filter" class="form-control">
                            <option value="">📅 All Dates</option>
                            <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>📍 Today</option>
                            <option value="yesterday" <?= $date_filter === 'yesterday' ? 'selected' : '' ?>>⏪ Yesterday</option>
                            <option value="this_week" <?= $date_filter === 'this_week' ? 'selected' : '' ?>>📅 This Week</option>
                            <option value="this_month" <?= $date_filter === 'this_month' ? 'selected' : '' ?>>📆 This Month</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="bi bi-funnel"></i>
                                Filter
                            </button>
                            <?php if (!empty($search) || !empty($date_filter)): ?>
                                <a href="messages.php" class="btn btn-clear">
                                    <i class="bi bi-x-circle"></i>
                                    Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Messages Table -->
            <div class="table-container loading-animation">
                <?php if ($num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">
                                        <i class="bi bi-hash me-1"></i>
                                        ID
                                    </th>
                                    <th scope="col">
                                        <i class="bi bi-person me-1"></i>
                                        Name
                                    </th>
                                    <th scope="col">
                                        <i class="bi bi-envelope me-1"></i>
                                        Email
                                    </th>
                                    <th scope="col">
                                        <i class="bi bi-telephone me-1"></i>
                                        Phone
                                    </th>
                                    <th scope="col">
                                        <i class="bi bi-chat-text me-1"></i>
                                        Message
                                    </th>
                                    <th scope="col">
                                        <i class="bi bi-calendar me-1"></i>
                                        Date
                                    </th>
                                    <th scope="col">
                                        <i class="bi bi-reply me-1"></i>
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-primary"><?php echo $row['id']; ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-gray-900">
                                            <?php echo htmlspecialchars($row['name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-primary">
                                            <i class="bi bi-envelope-at me-1"></i>
                                            <?php echo htmlspecialchars($row['email']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-success">
                                            <i class="bi bi-phone me-1"></i>
                                            <?php echo htmlspecialchars($row['tel']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="message-content" 
                                             title="<?php echo htmlspecialchars($row['message']); ?>">
                                            <?php echo htmlspecialchars($row['message']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-muted">
                                            <i class="bi bi-calendar2 me-1"></i>
                                            <?php 
                                            $date = new DateTime($row['created_at']);
                                            echo $date->format('M j, Y');
                                            ?>
                                            <br>
                                            <small class="text-secondary">
                                                <i class="bi bi-clock me-1"></i>
                                                <?php echo $date->format('g:i A'); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>?subject=Reply from Green Life Dental Clinic&body=Dear <?php echo htmlspecialchars($row['name']); ?>,%0D%0A%0D%0AThank you for contacting Green Life Dental Clinic. We have received your message:%0D%0A%0D%0A'<?php echo htmlspecialchars($row['message']); ?>'%0D%0A%0D%0ARegarding your inquiry..."
                                               class="btn btn-outline-success btn-sm"
                                               title="Reply to <?php echo htmlspecialchars($row['name']); ?>">
                                                <i class="bi bi-reply-fill"></i>
                                                Reply
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-results">
                        <div class="no-results-icon">
                            <i class="bi bi-inbox"></i>
                        </div>
                        <h5>No messages found</h5>
                        <?php if (!empty($search) || !empty($date_filter)): ?>
                            <p class="mb-3">
                                No messages match your current criteria:
                                <?php if (!empty($search)): ?>
                                    <br><strong>Search:</strong> "<?php echo htmlspecialchars($search); ?>"
                                <?php endif; ?>
                                <?php if (!empty($date_filter)): ?>
                                    <?php 
                                    $filter_labels = [
                                        'today' => 'Today',
                                        'yesterday' => 'Yesterday', 
                                        'this_week' => 'This Week',
                                        'this_month' => 'This Month'
                                    ];
                                    $filter_label = isset($filter_labels[$date_filter]) ? $filter_labels[$date_filter] : $date_filter;
                                    ?>
                                    <br><strong>Date Filter:</strong> <?php echo htmlspecialchars($filter_label); ?>
                                <?php endif; ?>
                            </p>
                            <a href="messages.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left me-1"></i>
                                View All Messages
                            </a>
                        <?php else: ?>
                            <p class="mb-0">No user messages have been received yet.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <?= $start_message ?>-<?= $end_message ?> of <?= number_format($total_messages) ?> messages
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
    </div>

    <!-- Bootstrap JS -->
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
        });

        // Enhanced table interactions
        document.querySelectorAll('.table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.002)';
                this.style.boxShadow = '0 4px 12px rgba(14, 165, 233, 0.15)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.boxShadow = 'none';
            });
        });

        // Auto-focus search input when page loads (disabled to prevent auto-scroll)
        const searchInput = document.getElementById('search');
        // Commented out to prevent auto-scroll to filter section
        // if (searchInput && !searchInput.value) {
        //     searchInput.focus();
        // }

        // Add keyboard shortcut for search (Ctrl+K) - manual focus only
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.getElementById('search');
                if (searchInput) {
                    // Scroll to search input smoothly when manually triggered
                    searchInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    searchInput.focus();
                    searchInput.select();
                }
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>
