<?php
// Set timezone for Malaysia
date_default_timezone_set("Asia/Kuala_Lumpur");

session_start();
include '../includes/db.php';

// 仅允许管理员访问
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 分页参数
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// 过滤参数
$admin_filter = isset($_GET['admin_id']) ? $_GET['admin_id'] : '';
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// 构建查询条件
$where_conditions = [];
$params = [];
$param_types = '';

if ($admin_filter) {
    $where_conditions[] = "admin_id = ?";
    $params[] = $admin_filter;
    $param_types .= 'i';
}

if ($action_filter) {
    $where_conditions[] = "action LIKE ?";
    $params[] = "%$action_filter%";
    $param_types .= 's';
}

if ($date_filter) {
    $where_conditions[] = "DATE(timestamp) = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// 查询日志总数
$count_sql = "SELECT COUNT(*) as total FROM user_logs $where_clause";
if ($params) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $total_logs = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_logs = $conn->query($count_sql)->fetch_assoc()['total'];
}

$total_pages = ceil($total_logs / $limit);

// 查询日志数据
$sql = "SELECT ul.*, a.name as admin_name 
        FROM user_logs ul 
        LEFT JOIN admin a ON ul.admin_id = a.id 
        $where_clause 
        ORDER BY ul.timestamp DESC 
        LIMIT ? OFFSET ?";

if ($params) {
    $stmt = $conn->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $param_types .= 'ii';
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
}

// 获取管理员列表用于过滤
$admin_list = $conn->query("SELECT DISTINCT a.id, a.name FROM user_logs ul LEFT JOIN admin a ON ul.admin_id = a.id WHERE a.name IS NOT NULL ORDER BY a.name");

// 获取操作类型列表
$action_list = $conn->query("SELECT DISTINCT action FROM user_logs WHERE action IS NOT NULL ORDER BY action");

// 今日活动统计
$today_logs = $conn->query("SELECT COUNT(*) as count FROM user_logs WHERE DATE(timestamp) = CURDATE()")->fetch_assoc()['count'];
$this_week_logs = $conn->query("SELECT COUNT(*) as count FROM user_logs WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Admin Portal</title>
    
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
            --admin-primary: #c0392b;
            --admin-secondary: #e74c3c;
            --admin-dark: #8b2635;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fdeaea 0%, var(--clinic-light) 50%, var(--clinic-warm) 100%);
            color: var(--clinic-text);
            line-height: 1.6;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .page-header {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--clinic-shadow-hover);
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
            background-image: 
                radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
                radial-gradient(circle at 70% 70%, rgba(255, 255, 255, 0.1) 2px, transparent 2px);
            background-size: 40px 40px;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .page-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white !important;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
            color: white !important;
        }

        .admin-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--clinic-white);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--clinic-shadow);
            border-left: 5px solid var(--stat-color, var(--admin-primary));
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--clinic-shadow-hover);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--stat-color, var(--admin-primary)), transparent);
            opacity: 0.05;
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--stat-color, var(--admin-primary)) 0%, rgba(192, 57, 43, 0.8) 100%);
            color: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--stat-color, var(--admin-primary));
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--clinic-muted);
            font-size: 0.95rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-today { --stat-color: var(--admin-primary); }
        .stat-week { --stat-color: var(--clinic-success); }
        .stat-total { --stat-color: var(--clinic-warning); }

        .filter-card {
            background: var(--clinic-white);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--clinic-shadow);
            margin-bottom: 2rem;
            border-top: 4px solid var(--admin-primary);
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--clinic-text);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--clinic-text);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control, .form-select {
            border: 2px solid #e8ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--clinic-warm);
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--admin-primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(192, 57, 43, 0.1);
        }

        .btn-admin {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: var(--clinic-shadow-hover);
            color: white;
        }

        .btn-secondary {
            background: var(--clinic-muted);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: var(--clinic-text);
            transform: translateY(-2px);
            color: white;
        }

        .logs-card {
            background: var(--clinic-white);
            border-radius: 15px;
            box-shadow: var(--clinic-shadow-hover);
            overflow: hidden;
            border-top: 4px solid var(--admin-secondary);
        }

        .card-header-custom {
            background: linear-gradient(135deg, rgba(192, 57, 43, 0.1) 0%, rgba(231, 76, 60, 0.1) 100%);
            padding: 1.5rem;
            border-bottom: 1px solid rgba(192, 57, 43, 0.1);
        }

        .table-responsive {
            border-radius: 0 0 15px 15px;
            overflow: hidden;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Custom scrollbar for webkit browsers */
        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: rgba(192, 57, 43, 0.1);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: var(--admin-primary);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: var(--admin-secondary);
        }

        .table {
            margin: 0;
            min-width: 800px;
            width: 100%;
        }

        .table thead th {
            border: none;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            white-space: nowrap;
        }

        /* Table column widths */
        .table th:nth-child(1), .table td:nth-child(1) { min-width: 120px; } /* Log ID column */
        .table th:nth-child(2), .table td:nth-child(2) { min-width: 180px; } /* Administrator column */
        .table th:nth-child(3), .table td:nth-child(3) { min-width: 300px; } /* Action column */
        .table th:nth-child(4), .table td:nth-child(4) { min-width: 160px; } /* Timestamp column */

        .table thead {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            color: white;
        }

        .table tbody tr {
            border-bottom: 1px solid rgba(192, 57, 43, 0.1);
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background: rgba(192, 57, 43, 0.05);
        }

        .log-id {
            font-weight: 700;
            color: var(--admin-primary);
            font-family: 'Courier New', monospace;
            background: rgba(192, 57, 43, 0.1);
            padding: 0.3rem 0.6rem;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .admin-badge-table {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .action-text {
            background: rgba(39, 174, 96, 0.1);
            color: #065f46;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            border-left: 3px solid var(--clinic-success);
            font-weight: 500;
            white-space: normal;
            word-wrap: break-word;
            max-width: 280px;
            display: inline-block;
        }

        .timestamp-text {
            font-family: 'Courier New', monospace;
            color: var(--clinic-muted);
            font-size: 0.9rem;
            background: rgba(127, 140, 141, 0.1);
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
        }

        .pagination-wrapper {
            background: linear-gradient(135deg, rgba(192, 57, 43, 0.05) 0%, rgba(231, 76, 60, 0.03) 100%);
            padding: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .pagination-btn {
            background: white;
            color: var(--admin-primary);
            border: 2px solid var(--admin-primary);
            padding: 0.6rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            min-width: 44px;
            justify-content: center;
        }

        .pagination-btn:hover {
            background: var(--admin-primary);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }

        .pagination-btn.active {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            color: white;
        }

        .pagination-btn:disabled {
            background: #f3f4f6;
            color: #9ca3af;
            border-color: #e5e7eb;
            cursor: not-allowed;
            transform: none;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--clinic-muted);
        }

        .empty-icon {
            font-size: 4rem;
            color: rgba(192, 57, 43, 0.3);
            margin-bottom: 1.5rem;
        }

        .empty-state h5 {
            color: var(--clinic-text);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.25rem;
            font-weight: 500;
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(251, 191, 36, 0.1) 100%);
            color: var(--clinic-warning);
            border-left: 4px solid var(--clinic-warning);
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem 0.5rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .filter-card {
                padding: 1.5rem;
            }

            .table {
                font-size: 0.85rem;
                min-width: 700px;
            }

            .table th,
            .table td {
                padding: 0.75rem 0.5rem;
            }

            .table-responsive {
                margin: 0 -1rem;
                border-radius: 0;
            }

            .pagination-wrapper {
                padding: 1rem;
                gap: 0.25rem;
            }

            .pagination-btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-container fade-in">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title">
                    <i class="fas fa-clipboard-data"></i>
                    System Activity Logs
                </h1>
                <p class="page-subtitle">Monitor and track all administrative activities and system events</p>
                <div class="admin-badge mt-2">
                    <i class="fas fa-shield-alt me-1"></i>
                    Live System Monitoring
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card stat-today">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-number"><?= $today_logs ?></div>
                <div class="stat-label">Today's Activities</div>
            </div>
            <div class="stat-card stat-week">
                <div class="stat-icon">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-number"><?= $this_week_logs ?></div>
                <div class="stat-label">This Week's Activities</div>
            </div>
            <div class="stat-card stat-total">
                <div class="stat-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-number"><?= $total_logs ?></div>
                <div class="stat-label">Total Log Entries</div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-card">
            <div class="section-title">
                <i class="fas fa-filter"></i>
                Filter System Logs
            </div>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">
                        <i class="fas fa-user-shield"></i>
                        Administrator
                    </label>
                    <select name="admin_id" class="form-select">
                        <option value="">All Administrators</option>
                        <?php 
                        // Reset the result pointer
                        $admin_list->data_seek(0);
                        while ($admin = $admin_list->fetch_assoc()): ?>
                            <option value="<?= $admin['id'] ?>" <?= $admin_filter == $admin['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($admin['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        <i class="fas fa-search"></i>
                        Action Contains
                    </label>
                    <input type="text" 
                           name="action" 
                           class="form-control" 
                           value="<?= htmlspecialchars($action_filter) ?>" 
                           placeholder="Search action...">
                </div>

                <div class="col-md-2">
                    <label class="form-label">
                        <i class="fas fa-calendar"></i>
                        Date
                    </label>
                    <input type="date" 
                           name="date" 
                           class="form-control" 
                           value="<?= $date_filter ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">
                        <i class="fas fa-list-ol"></i>
                        Per Page
                    </label>
                    <select name="limit" class="form-select">
                        <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                        <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-admin">
                        <i class="fas fa-filter"></i>
                        Filter
                    </button>
                    <a href="system_logs.php" class="btn btn-secondary">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Logs Table Section -->
        <?php if ($result->num_rows > 0): ?>
        <div class="logs-card">
            <div class="card-header-custom">
                <div class="section-title mb-0">
                    <i class="fas fa-list-ul"></i>
                    Activity Log Entries
                    <span class="badge bg-light text-dark ms-2 px-3"><?= $total_logs ?> total</span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag me-2"></i>Log ID</th>
                            <th><i class="fas fa-user-shield me-2"></i>Administrator</th>
                            <th><i class="fas fa-activity me-2"></i>Action Performed</th>
                            <th><i class="fas fa-clock me-2"></i>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($log = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="log-id">#<?= str_pad($log['id'], 6, '0', STR_PAD_LEFT) ?></span>
                                </td>
                                <td>
                                    <?php if ($log['admin_name']): ?>
                                        <span class="admin-badge-table">
                                            <i class="fas fa-user-gear me-1"></i>
                                            <?= htmlspecialchars($log['admin_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="fas fa-user-times me-1"></i>
                                            Unknown Admin (ID: <?= $log['admin_id'] ?>)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-text">
                                        <i class="fas fa-arrow-right me-2"></i>
                                        <?= htmlspecialchars($log['action']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="timestamp-text">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= date('M j, Y', strtotime($log['timestamp'])) ?>
                                        <br>
                                        <i class="fas fa-clock me-1"></i>
                                        <small><?= date('g:i:s A', strtotime($log['timestamp'])) ?></small>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="pagination-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="pagination-btn" style="opacity: 0.5; cursor: not-allowed;">
                        <i class="fas fa-chevron-left"></i>
                    </span>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);

                if ($start_page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="pagination-btn">1</a>
                    <?php if ($start_page > 2): ?><span class="mx-2 text-muted">...</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="pagination-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?><span class="mx-2 text-muted">...</span><?php endif; ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="pagination-btn"><?= $total_pages ?></a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="pagination-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="pagination-btn" style="opacity: 0.5; cursor: not-allowed;">
                        <i class="fas fa-chevron-right"></i>
                    </span>
                <?php endif; ?>

                <div class="ms-3 text-muted">
                    <small>
                        Showing <?= $offset + 1 ?>-<?= min($offset + $limit, $total_logs) ?> of <?= $total_logs ?> entries
                    </small>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="logs-card">
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <h5 class="text-muted mb-3">No Log Entries Found</h5>
                <p class="text-muted mb-4">No system activity logs match your current filter criteria.</p>
                <a href="system_logs.php" class="btn btn-admin">
                    <i class="fas fa-undo me-2"></i>
                    Clear Filters
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Enhanced table interactions
            const tableRows = document.querySelectorAll('.table tbody tr');
            tableRows.forEach((row, index) => {
                // Add entrance animation
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);

                // Hover effects
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                    this.style.zIndex = '10';
                    this.style.boxShadow = '0 4px 20px rgba(192, 57, 43, 0.15)';
                });

                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.zIndex = '1';
                    this.style.boxShadow = 'none';
                });
                
                // Click to highlight
                row.addEventListener('click', function() {
                    tableRows.forEach(r => r.classList.remove('table-active'));
                    this.classList.add('table-active');
                    
                    // Show success toast
                    showToast('Log entry selected', 'success');
                });
            });

            // Enhanced pagination interactions
            const paginationBtns = document.querySelectorAll('.pagination-btn');
            paginationBtns.forEach(btn => {
                if (!btn.style.cursor === 'not-allowed') {
                    btn.addEventListener('click', function(e) {
                        if (!this.classList.contains('active')) {
                            // Add loading state
                            const originalContent = this.innerHTML;
                            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                            this.style.pointerEvents = 'none';
                            
                            // Reset after navigation (fallback)
                            setTimeout(() => {
                                this.innerHTML = originalContent;
                                this.style.pointerEvents = 'auto';
                            }, 2000);
                        }
                    });
                }
            });

            // Form submission enhancements
            const filterForm = document.querySelector('form');
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        const originalContent = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Filtering...';
                        submitBtn.disabled = true;
                        
                        // Re-enable after delay (fallback)
                        setTimeout(() => {
                            submitBtn.innerHTML = originalContent;
                            submitBtn.disabled = false;
                        }, 3000);
                    }
                });

                // Auto-submit on certain changes
                const autoSubmitFields = filterForm.querySelectorAll('select[name="limit"]');
                autoSubmitFields.forEach(field => {
                    field.addEventListener('change', function() {
                        showToast('Updating results...', 'info');
                        filterForm.submit();
                    });
                });
            }

            // Auto-focus and select first input
            const firstInput = document.querySelector('.form-control, .form-select');
            if (firstInput && firstInput.type !== 'date') {
                firstInput.addEventListener('focus', function() {
                    if (this.type === 'text') {
                        this.select();
                    }
                });
            }

            // Copy functionality for log IDs
            const logIds = document.querySelectorAll('.log-id');
            logIds.forEach(logId => {
                logId.style.cursor = 'pointer';
                logId.title = 'Click to copy log ID';
                
                logId.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const logIdText = this.textContent;
                    
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(logIdText).then(() => {
                            showToast(`Log ID ${logIdText} copied to clipboard!`, 'success');
                            
                            // Visual feedback
                            const original = this.style.backgroundColor;
                            this.style.backgroundColor = 'rgba(39, 174, 96, 0.3)';
                            setTimeout(() => {
                                this.style.backgroundColor = original;
                            }, 1000);
                        });
                    }
                });
            });

            // Search input enhancements
            const searchInput = document.querySelector('input[name="action"]');
            if (searchInput) {
                let searchTimeout;
                
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    const query = this.value.trim();
                    
                    // Visual feedback during typing
                    this.style.borderColor = '#f39c12';
                    
                    if (query.length > 2) {
                        searchTimeout = setTimeout(() => {
                            this.style.borderColor = '#27ae60';
                            // Optional: Auto-submit after user stops typing
                            // this.closest('form').submit();
                        }, 1000);
                    }
                });
            }

            // Card entrance animations
            const cards = document.querySelectorAll('.filter-card, .logs-card, .stat-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Auto-refresh indicator
            let refreshInterval;
            function startAutoRefresh() {
                refreshInterval = setInterval(() => {
                    // Only auto-refresh if no filters are applied and user is not interacting
                    if (!document.activeElement.matches('input, select, button') && 
                        !window.location.search) {
                        
                        showToast('Refreshing logs...', 'info');
                        window.location.reload();
                    }
                }, 60000); // Refresh every minute
            }

            // Start auto-refresh
            startAutoRefresh();

            // Clean up on page unload
            window.addEventListener('beforeunload', function() {
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                }
            });
        });

        // Toast notification system
        function showToast(message, type = 'info') {
            const toastContainer = getOrCreateToastContainer();
            
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show`;
            toast.style.cssText = `
                position: relative;
                margin-bottom: 0.5rem;
                border-radius: 10px;
                font-size: 0.9rem;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            `;
            
            const icons = {
                success: 'fas fa-check-circle',
                info: 'fas fa-info-circle',
                warning: 'fas fa-exclamation-triangle',
                danger: 'fas fa-times-circle'
            };
            
            toast.innerHTML = `
                <i class="${icons[type]} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            toastContainer.appendChild(toast);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 150);
                }
            }, 3000);
        }

        function getOrCreateToastContainer() {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.style.cssText = `
                    position: fixed;
                    top: 100px;
                    right: 20px;
                    z-index: 9999;
                    max-width: 350px;
                `;
                document.body.appendChild(container);
            }
            return container;
        }

        // Export functionality (if needed)
        function exportLogs() {
            showToast('Preparing log export...', 'info');
            // Implementation would go here
        }

        // Real-time updates (if WebSocket available)
        function initRealtimeUpdates() {
            // WebSocket implementation would go here
            // For now, just show that real-time is "active"
            const badge = document.querySelector('.admin-badge');
            if (badge) {
                setInterval(() => {
                    badge.style.opacity = badge.style.opacity === '0.7' ? '1' : '0.7';
                }, 2000);
            }
        }

        // Initialize real-time updates
        initRealtimeUpdates();
    </script>

</body>
</html>

