<?php
session_start();
include '../includes/db.php';

// 检查管理员是否登录
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 处理删除用户
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    // 不允许删除自己
    if ($user_id == $_SESSION['admin_id']) {
        echo "<script>alert('You cannot delete your own account!');</script>";
    } else {
        $delete_sql = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            // 记录管理操作
            $admin_id = $_SESSION['admin_id'];
            $action = "Deleted user with ID: $user_id";
            $conn->query("INSERT INTO user_logs (admin_id, action) VALUES ('$admin_id', '$action')");
            
            echo "<script>alert('User deleted successfully!'); window.location.href='manage_users.php';</script>";
        } else {
            echo "<script>alert('Failed to delete user.');</script>";
        }
    }
}

// 处理搜索逻辑
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
$sql = "SELECT id, name, email, role, created_at FROM users";

$conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $conditions[] = "(name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= 'ss';
}

if (!empty($role_filter)) {
    $conditions[] = "role = ?";
    $params[] = $role_filter;
    $param_types .= 's';
}

// 分頁設定
$users_per_page = 15; // 每頁顯示用戶數
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $users_per_page;

// 先計算總用戶數
$count_sql = "SELECT COUNT(*) as total FROM users";
if (!empty($conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $conditions);
}

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_users = $total_result->fetch_assoc()['total'];
$count_stmt->close();

// 計算分頁
$total_pages = ceil($total_users / $users_per_page);
$start_user = ($current_page - 1) * $users_per_page + 1;
$end_user = min($current_page * $users_per_page, $total_users);

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY role, name LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

// 準備參數，加上分頁參數
$all_params = $params;
$all_params[] = $users_per_page;
$all_params[] = $offset;
$all_param_types = $param_types . 'ii';

if (!empty($all_params)) {
    $stmt->bind_param($all_param_types, ...$all_params);
}

$stmt->execute();
$users = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #10b981;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --shadow-light: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-large: 0 10px 25px rgba(0, 0, 0, 0.1);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Header Section */
        .page-header {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-color);
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
            background: var(--gradient-primary);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-title i {
            color: var(--primary-color);
            font-size: 1.8rem;
        }

        .add-user-btn {
            background: var(--gradient-success);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-medium);
        }

        .add-user-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-large);
            color: white;
            text-decoration: none;
        }

        /* Search Section */
        .search-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
        }

        .search-form {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 280px;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 50px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--light-bg);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }

        .role-filter {
            min-width: 160px;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 50px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--light-bg);
            cursor: pointer;
        }

        .role-filter:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }

        .search-btn, .reset-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-btn {
            background: var(--primary-color);
            color: white;
        }

        .reset-btn {
            background: var(--text-secondary);
            color: white;
        }

        .search-btn:hover, .reset-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-medium);
            color: white;
            text-decoration: none;
        }

        /* Users Table */
        .users-table-container {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 12px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin: 0;
        }

        .users-table thead th {
            background: var(--light-bg);
            color: var(--text-primary);
            font-weight: 600;
            text-align: left;
            padding: 1rem;
            border: none;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .users-table tbody td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .users-table tbody tr:hover {
            background: rgba(37, 99, 235, 0.02);
        }

        .users-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* User ID Badge */
        .user-id {
            background: var(--primary-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        /* Role Badges */
        .role-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-admin {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(220, 38, 38, 0.2);
        }

        .role-doctor {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(37, 99, 235, 0.2);
        }

        .role-patient {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(5, 150, 105, 0.2);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
        }

        .btn-edit {
            background: rgba(217, 119, 6, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(217, 119, 6, 0.2);
        }

        .btn-delete {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(220, 38, 38, 0.2);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-light);
            text-decoration: none;
        }

        .btn-edit:hover {
            background: var(--warning-color);
            color: white;
        }

        .btn-delete:hover {
            background: var(--danger-color);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        /* Statistics Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .header-content {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }

            .page-title {
                font-size: 1.5rem;
                justify-content: center;
            }

            .search-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input {
                min-width: 100%;
            }

            .role-filter {
                min-width: 100%;
            }

            .users-table-container {
                padding: 1rem;
            }

            .users-table thead th,
            .users-table tbody td {
                padding: 0.75rem 0.5rem;
                font-size: 0.85rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .btn {
                justify-content: center;
                padding: 10px 16px;
            }

            .stats-container {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        /* Loading Animation */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .loading {
            animation: pulse 1.5s ease-in-out infinite;
        }

        /* Custom Scrollbar */
        .table-wrapper::-webkit-scrollbar {
            height: 8px;
        }

        .table-wrapper::-webkit-scrollbar-track {
            background: var(--light-bg);
            border-radius: 4px;
        }

        .table-wrapper::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }

        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }
        
        /* Pagination Styles */
        .pagination-container {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            background: var(--light-bg);
            border: 2px solid #e5e7eb;
            color: var(--primary-color);
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
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
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .page-btn.active {
            background: var(--primary-color);
            color: #1a202c;
            border-color: var(--primary-color);
            font-weight: 700;
            text-shadow: none;
        }
        
        .page-btn.disabled {
            background: #f3f4f6;
            color: #9ca3af;
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
    
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title">
                    <i class="bi bi-people-fill"></i>
                    User Management
                </h1>
                <a href="add_user.php" class="add-user-btn">
                    <i class="bi bi-person-plus-fill"></i>
                    Add New User
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-container">
            <?php
            // Get user statistics
            $total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
            $total_admins = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
            $total_doctors = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor'")->fetch_assoc()['count'];
            $total_patients = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'patient'")->fetch_assoc()['count'];
            ?>
            <div class="stat-card">
                <div class="stat-number"><?= $total_users ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_admins ?></div>
                <div class="stat-label">Administrators</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_doctors ?></div>
                <div class="stat-label">Doctors</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_patients ?></div>
                <div class="stat-label">Patients</div>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <form method="GET" class="search-form">
                <input 
                    type="text" 
                    name="search" 
                    class="search-input" 
                    placeholder="🔍 Search by name or email..."
                    value="<?= htmlspecialchars($search) ?>"
                >
                <select name="role" class="role-filter">
                    <option value="">👥 All Roles</option>
                    <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>🛡️ Admin</option>
                    <option value="doctor" <?= $role_filter === 'doctor' ? 'selected' : '' ?>>⚕️ Doctor</option>
                    <option value="patient" <?= $role_filter === 'patient' ? 'selected' : '' ?>>👤 Patient</option>
                </select>
                <button type="submit" class="search-btn">
                    <i class="bi bi-search"></i>
                    Search
                </button>
                <?php if (!empty($search) || !empty($role_filter)) : ?>
                    <a href="manage_users.php" class="reset-btn">
                        <i class="bi bi-arrow-clockwise"></i>
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Users Table -->
        <?php if ($users && $users->num_rows > 0) { ?>
            <div class="users-table-container">
                <div class="d-flex justify-content-between align-items-center mb-4" style="padding: 0 1rem;">
                    <h3 style="margin: 0; color: var(--text-primary); font-weight: 600;">
                        <i class="bi bi-people-fill" style="margin-right: 0.5rem; color: var(--primary-color);"></i>
                        Users List
                    </h3>
                    <div class="d-flex align-items-center gap-3">
                        <span style="background: var(--light-bg); padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.9rem; font-weight: 600; color: var(--text-primary);">
                            <?php echo $users->num_rows; ?> of <?= number_format($total_users) ?> Users
                        </span>
                        <?php if ($total_pages > 1): ?>
                        <small style="color: var(--text-secondary); font-weight: 500;">
                            Page <?= $current_page ?> of <?= $total_pages ?>
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User Details</th>
                                <th>Role</th>
                                <th>Registration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $users->fetch_assoc()) { ?>
                            <tr>
                                <td>
                                    <span class="user-id">#<?= htmlspecialchars($row['id']); ?></span>
                                </td>
                                <td>
                                    <div>
                                        <div style="font-weight: 600; margin-bottom: 4px;">
                                            <?= htmlspecialchars($row['name']); ?>
                                        </div>
                                        <div style="color: var(--text-secondary); font-size: 0.9rem;">
                                            <i class="bi bi-envelope"></i> <?= htmlspecialchars($row['email']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?= $row['role'] ?>">
                                        <i class="bi bi-<?= 
                                            $row['role'] == 'admin' ? 'shield-fill' : 
                                            ($row['role'] == 'doctor' ? 'heart-pulse-fill' : 'person-fill'); 
                                        ?>"></i>
                                        <?= ucfirst(htmlspecialchars($row['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="color: var(--text-secondary); font-size: 0.9rem;">
                                        <i class="bi bi-calendar3"></i> 
                                        <?= date('M d, Y', strtotime($row['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit_user.php?id=<?= $row['id']; ?>" class="btn btn-edit">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <?php if ($row['id'] != $_SESSION['admin_id']) { ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ Are you sure you want to delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="user_id" value="<?= $row['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-delete">
                                                    <i class="bi bi-trash3"></i> Delete
                                                </button>
                                            </form>
                                        <?php } else { ?>
                                            <span class="btn" style="background: var(--light-bg); color: var(--text-secondary); cursor: not-allowed;">
                                                <i class="bi bi-shield-check"></i> Current User
                                            </span>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <?= $start_user ?>-<?= $end_user ?> of <?= number_format($total_users) ?> users
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
        <?php } else { ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <h3>No Users Found</h3>
                <p>
                    <?php if (!empty($search)) { ?>
                        No users match your search criteria. Try different keywords or 
                        <a href="manage_users.php" style="color: var(--primary-color);">clear the search</a>.
                    <?php } else { ?>
                        There are no users in the system yet. Click "Add New User" to get started.
                    <?php } ?>
                </p>
            </div>
        <?php } ?>
    </div>

    <script>
        // Add smooth scrolling and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading animation to buttons when clicked
            const buttons = document.querySelectorAll('.btn, .add-user-btn, .search-btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    if (!this.classList.contains('loading')) {
                        this.classList.add('loading');
                        setTimeout(() => {
                            this.classList.remove('loading');
                        }, 1000);
                    }
                });
            });

            // Enhanced search input focus effect
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                searchInput.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            }

            // Table row hover effects
            const tableRows = document.querySelectorAll('.users-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                    this.style.transition = 'all 0.3s ease';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });
        });
    </script>
</body>
</html>
