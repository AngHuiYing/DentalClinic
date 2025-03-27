<?php
// 连接数据库
$conn = new mysqli("localhost", "root", "", "hospital_management_system");

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// 处理搜索功能
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT * FROM messages";
if (!empty($search)) {
    $sql .= " WHERE name LIKE '%$search%' OR email LIKE '%$search%' OR tel LIKE '%$search%' OR message LIKE '%$search%'";
}
$sql .= " ORDER BY created_at DESC";

$result = $conn->query($sql);
$num_rows = $result->num_rows; // 获取结果行数
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Messages</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; } /* 增加整体页面的边距 */
        h2 { margin-bottom: 20px; } /* 标题下方留空 */
        .container { max-width: 900px; margin: auto; } /* 居中内容区域 */
        .search-bar { margin-bottom: 20px; } /* 搜索框底部间距 */
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #005a8d; color: white; }
        input[type="text"] { padding: 8px; width: 250px; }
        button { padding: 8px 15px; background-color: #005a8d; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #003f6b; }
        .no-results { text-align: center; font-weight: bold; color: red; padding: 20px; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container">
    <h2>Messages from Users</h2>

    <!-- 搜索框 -->
    <form method="GET" class="search-bar">
        <input type="text" name="search" placeholder="Search by name, email, tel, or message" value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Search</button>
    </form>

    <?php if ($num_rows > 0) { ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone Number</th>
                <th>Message</th>
                <th>Date</th>
                <th>Reply</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['name']; ?></td>
                    <td><?php echo $row['email']; ?></td>
                    <td><?php echo $row['tel']; ?></td>
                    <td><?php echo $row['message']; ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                    <td>
                        <a href="mailto:<?php echo $row['email']; ?>?subject=Reply from Green Life Hospital">
                            Reply by Mail
                        </a>
                    </td>
                </tr>
            <?php } ?>
        </table>
    <?php } else { ?>
        <p class="no-results">No users found</p>
    <?php } ?>
</div>

</body>
</html>

<?php $conn->close(); ?>
