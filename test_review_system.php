<?php
session_start();
require_once 'includes/db.php';

// 模拟患者登录 (用于测试)
if (isset($_GET['test_patient'])) {
    $_SESSION['user_id'] = intval($_GET['test_patient']);
    $_SESSION['user_role'] = 'patient';
    echo "<div class='alert alert-info'>测试登录为患者 ID: " . $_SESSION['user_id'] . "</div>";
}

// Helper functions (复制自doctor_reviews.php)
function isPatient() {
    return isset($_SESSION['user_id']) && 
           ((isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'patient') || 
            (isset($_SESSION['role']) && $_SESSION['role'] === 'patient'));
}

function hasAppointmentWithDoctor($conn, $patient_id, $doctor_id) {
    $query = "SELECT COUNT(*) as count FROM appointments 
              WHERE (patient_id = ? OR patient_email = (SELECT email FROM users WHERE id = ?)) 
              AND doctor_id = ? 
              AND status IN ('completed', 'confirmed')
              AND appointment_date <= CURDATE()";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $patient_id, $patient_id, $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Review System Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h1>评价系统测试</h1>
        
        <div class="row">
            <div class="col-md-6">
                <h3>当前Session状态</h3>
                <div class="card p-3">
                    <p><strong>用户ID:</strong> <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '未登录'; ?></p>
                    <p><strong>用户角色:</strong> <?php echo isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '未设置'; ?></p>
                    <p><strong>是患者:</strong> <?php echo isPatient() ? '是' : '否'; ?></p>
                </div>
                
                <h4 class="mt-4">测试登录</h4>
                <div class="btn-group-vertical d-grid gap-2">
                    <a href="?test_patient=1" class="btn btn-primary">登录为患者 test (ID: 1)</a>
                    <a href="?test_patient=16" class="btn btn-primary">登录为患者 Nortrick (ID: 16)</a>
                    <a href="?test_patient=12" class="btn btn-primary">登录为患者 LeoIzu (ID: 12)</a>
                </div>
            </div>
            
            <div class="col-md-6">
                <h3>预约权限检查</h3>
                <?php if (isPatient()): ?>
                    <?php
                    $doctors = [1 => 'John Doe', 2 => 'Sarah Lee', 3 => 'William Brown'];
                    foreach ($doctors as $doc_id => $doc_name):
                        $can_review = hasAppointmentWithDoctor($conn, $_SESSION['user_id'], $doc_id);
                    ?>
                    <div class="card mb-2 p-3">
                        <h5>Dr. <?php echo $doc_name; ?></h5>
                        <p class="mb-2">
                            <strong>可以评价:</strong> 
                            <?php if ($can_review): ?>
                                <span class="badge bg-success">可以</span>
                                <a href="patient/rate_doctor.php?doctor_id=<?php echo $doc_id; ?>" class="btn btn-sm btn-primary ms-2">
                                    <i class="fas fa-star"></i> 评价
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary">不可以</span>
                                <small class="text-muted d-block">需要先有完成的预约</small>
                            <?php endif; ?>
                        </p>
                        <a href="doctor_reviews.php?doctor_id=<?php echo $doc_id; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i> 查看评价
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-warning">请先登录为患者</div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-4">
            <h3>预约历史 (当前患者)</h3>
            <?php if (isPatient()): ?>
                <?php
                $appointments_query = "SELECT a.*, d.name as doctor_name 
                                      FROM appointments a 
                                      LEFT JOIN doctors d ON a.doctor_id = d.id 
                                      WHERE a.patient_id = ? 
                                      ORDER BY a.appointment_date DESC LIMIT 10";
                $appointments_stmt = $conn->prepare($appointments_query);
                $appointments_stmt->bind_param("i", $_SESSION['user_id']);
                $appointments_stmt->execute();
                $appointments_result = $appointments_stmt->get_result();
                ?>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>医生</th>
                                <th>日期</th>
                                <th>状态</th>
                                <th>可评价</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($apt = $appointments_result->fetch_assoc()): ?>
                            <tr>
                                <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                                <td><?php echo $apt['appointment_date']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $apt['status'] === 'completed' ? 'success' : ($apt['status'] === 'confirmed' ? 'primary' : 'secondary'); ?>">
                                        <?php echo $apt['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (in_array($apt['status'], ['completed', 'confirmed']) && $apt['appointment_date'] <= date('Y-m-d')): ?>
                                        <span class="badge bg-success">是</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">否</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">请先登录查看预约历史</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>