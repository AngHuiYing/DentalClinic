<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../doctor/login.php");
    exit;
}

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

// 直接從 appointments 撈 confirmed 的病人資料
$sql = "
    SELECT DISTINCT 
           a.patient_name, 
           a.patient_email, 
           a.patient_phone
    FROM appointments a
    WHERE a.doctor_id = ? AND a.status = 'confirmed'
";

if (!empty($search)) {
    $sql .= " AND (a.patient_name LIKE ? OR a.patient_email LIKE ? OR a.patient_phone LIKE ?)";
}

$stmt = $conn->prepare($sql);

if (!empty($search)) {
    $searchParam = "%$search%";
    $stmt->bind_param("isss", $doctor_id, $searchParam, $searchParam, $searchParam);
} else {
    $stmt->bind_param("i", $doctor_id);
}

$stmt->execute();
$patients = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Patient Records</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<!-- Modal -->
<div class="modal fade" id="medicalHistoryModal" tabindex="-1" aria-labelledby="medicalHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="medicalHistoryModalLabel">Medical History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="medical-history-content">
                <div style="overflow-x:auto; max-width:100%;">
                    <!-- Medical history table will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>
<div class="container mt-5" style="padding-top: 70px;">
    <h2>My Patients (Confirmed)</h2>

    <!-- 搜索框 -->
    <form method="GET" class="mb-3">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search by name, email or phone..." value="<?= htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form>

    <?php if ($patients->num_rows > 0) { ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $patients->fetch_assoc()) { ?>
                    <tr>
                        <td><?= htmlspecialchars($row['patient_name'] ?? 'N/A'); ?></td>
                        <td><?= htmlspecialchars($row['patient_email'] ?? 'N/A'); ?></td>
                        <td><?= htmlspecialchars($row['patient_phone'] ?? 'N/A'); ?></td>
                        <td>
                            <?php if (!empty($row['patient_email'])) { ?>
                                <button type="button" class="btn btn-info btn-sm view-history-btn" data-email="<?= htmlspecialchars($row['patient_email']); ?>" data-name="<?= htmlspecialchars($row['patient_name']); ?>">View Medical History</button>
                            <?php } else { ?>
                                <span class="text-muted">No Email</span>
                            <?php } ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('.view-history-btn').on('click', function() {
        var email = $(this).data('email');
        var name = $(this).data('name');
        $('#medicalHistoryModalLabel').text('Medical History for ' + name + ' (' + email + ')');
        $('#medical-history-content').html('<div style="overflow-x:auto; max-width:100%;"><div class="text-center py-4"><span class="spinner-border"></span> Loading...</div></div>');
        $('#medicalHistoryModal').modal('show');
        $.ajax({
            url: 'get_medical_history.php',
            type: 'GET',
            data: { patient_email: email },
            success: function(data) {
                $('#medical-history-content').html('<div style="overflow-x:auto; max-width:100%;">'+data+'</div>');
            },
            error: function() {
                $('#medical-history-content').html('<div style="overflow-x:auto; max-width:100%;"><div class="alert alert-danger">Failed to load medical history.</div></div>');
            }
        });
    });
});
</script>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } else { ?>
        <div class="alert alert-warning">No confirmed patients found.</div>
    <?php } ?>
</div>
</body>
</html>