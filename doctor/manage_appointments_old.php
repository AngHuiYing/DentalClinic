<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../doctor/login.php");
    exit;
}

$user_id = $_SESSION['user_id']; 

// **獲取 doctor_id**
$sql = "SELECT id FROM doctors WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    die("Doctor record not found!");
}
$doctor_id = $doctor['id'];

// **搜尋條件**
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$search_param = "%$search%";

// **今天的日期**
$today = date('Y-m-d');

// **查詢：包含今天 + 所有**
$sql = "SELECT a.id, 
               a.appointment_date, 
               a.appointment_time, 
               a.status,
               a.created_at,
               u.name AS patient_user, 
               u.email AS user_email,
               a.patient_name, 
               a.patient_email, 
               a.patient_phone,
               a.message
        FROM appointments a
        LEFT JOIN users u ON a.patient_id = u.id
        WHERE a.doctor_id = ?
          AND (u.name LIKE ? OR a.patient_name LIKE ? 
               OR u.email LIKE ? OR a.patient_email LIKE ? 
               OR a.patient_phone LIKE ? OR a.appointment_date LIKE ? 
               OR a.status LIKE ?)
        ORDER BY a.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isssssss", $doctor_id, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param);
$stmt->execute();
$result = $stmt->get_result();

// 分開 today / all
$today_appts = [];
$all_appts = [];
while ($row = $result->fetch_assoc()) {
    if ($row['appointment_date'] === $today) {
        $today_appts[] = $row;
    }
    $all_appts[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Appointments</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container mt-5" style="padding-top: 70px;">
    <h2>Manage Your Appointments</h2>

    <!-- 搜尋 -->
    <form method="GET" class="d-flex mb-3">
        <input type="text" name="search" class="form-control me-2" placeholder="Search patient, email, phone, date, status"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary">Search</button>
        <a href="manage_appointments.php" class="btn btn-secondary ms-2">Reset</a>
    </form>

    <!-- 今日預約 -->
    <h4>Today's Appointments (<?= $today ?>)</h4>
    <table class="table table-bordered mt-2">
        <thead>
            <tr>
                <th>Patient</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Date</th>
                <th>Time</th>
                <th>Message</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($today_appts) > 0) { ?>
            <?php foreach ($today_appts as $row) { ?>
                <tr>
                    <td><?= htmlspecialchars($row['patient_user'] ?? $row['patient_name'] ?? 'Anonymous'); ?></td>
                    <td><?= htmlspecialchars($row['user_email'] ?? $row['patient_email'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($row['patient_phone'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($row['appointment_date']); ?></td>
                    <td><?= htmlspecialchars($row['appointment_time']); ?></td>
                    <td>
                        <?= !empty($row['message']) 
                            ? '<span title="'.htmlspecialchars($row['message']).'">'.htmlspecialchars(mb_strimwidth($row['message'], 0, 30, "...")).'</span>' 
                            : '<span class="text-muted">N/A</span>'; ?>
                    </td>
                    <td>
                        <span class="badge <?= 
                            $row['status'] == 'confirmed' ? 'bg-success' : 
                            ($row['status'] == 'cancelled_by_patient' ? 'bg-danger' : 
                            ($row['status'] == 'cancelled_by_admin' ? 'bg-dark' : 'bg-warning')); 
                        ?>">
                            <?= ucfirst($row['status']); ?>
                        </span>
                    </td>
                </tr>
            <?php } ?>
        <?php } else { ?>
            <tr>
                <td colspan="7" class="text-center text-muted">No appointment today.</td>
            </tr>
        <?php } ?>
        </tbody>
    </table>

    <!-- 所有預約 -->
    <h4>All Appointments</h4>
    <?php if (count($all_appts) > 0) { ?>
        <table class="table table-bordered mt-2">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_appts as $row) { ?>
                    <tr>
                        <td><?= htmlspecialchars($row['patient_user'] ?? $row['patient_name'] ?? 'Anonymous'); ?></td>
                        <td><?= htmlspecialchars($row['user_email'] ?? $row['patient_email'] ?? 'N/A'); ?></td>
                        <td><?= htmlspecialchars($row['patient_phone'] ?? 'N/A'); ?></td>
                        <td><?= htmlspecialchars($row['appointment_date']); ?></td>
                        <td><?= htmlspecialchars($row['appointment_time']); ?></td>
                        <td>
                            <?= !empty($row['message']) 
                                ? '<span title="'.htmlspecialchars($row['message']).'">'.htmlspecialchars(mb_strimwidth($row['message'], 0, 30, "...")).'</span>' 
                                : '<span class="text-muted">N/A</span>'; ?>
                        </td>
                        <td>
                            <span class="badge <?= 
                                $row['status'] == 'confirmed' ? 'bg-success' : 
                                ($row['status'] == 'cancelled_by_patient' ? 'bg-danger' : 
                                ($row['status'] == 'cancelled_by_admin' ? 'bg-dark' : 'bg-warning')); 
                            ?>">
                                <?= ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($row['created_at']); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } else { ?>
        <div class="alert alert-info mt-3">You have no appointments.</div>
    <?php } ?>
</div>
</body>
</html>
