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
$sql = "SELECT id, name, email, role, created_at FROM users";
if (!empty($search)) {
    $sql .= " WHERE name LIKE ? OR email LIKE ?";
}

$sql .= " ORDER BY role, name";
$stmt = $conn->prepare($sql);

if (!empty($search)) {
    $searchParam = "%$search%";
    $stmt->bind_param("ss", $searchParam, $searchParam);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-5" style="padding-top: 70px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Users</h2>
        <a href="add_user.php" class="btn btn-success">Add New User</a>
    </div>

    <!-- 搜索表单 -->
    <form method="GET" class="mb-3">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search by name or email" value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if (!empty($search)) : ?>
                <a href="manage_users.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($users && $users->num_rows > 0) { ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $users->fetch_assoc()) { ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id']); ?></td>
                            <td><?= htmlspecialchars($row['name']); ?></td>
                            <td><?= htmlspecialchars($row['email']); ?></td>
                            <td>
                                <span class="badge <?= 
                                    $row['role'] == 'admin' ? 'bg-danger' : 
                                    ($row['role'] == 'doctor' ? 'bg-primary' : 'bg-success'); 
                                ?>">
                                    <?= ucfirst(htmlspecialchars($row['role'])); ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['created_at']); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="edit_user.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <?php if ($row['id'] != $_SESSION['admin_id']) { ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="user_id" value="<?= $row['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <div class="alert alert-info">No users found.</div>
    <?php } ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
