<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../doctor/login.php");
    exit;
}

// 获取医生 ID（doctor_id）
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    die("Doctor record not found!");
}

$doctor_id = $doctor['id']; // 确保 doctor_id 取自 doctors 表

// 处理添加病历
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_record'])) {
    $patient_id = $_POST['patient_id'];
    $diagnosis = trim($_POST['diagnosis']);
    $prescription = trim($_POST['prescription']);

    // 确保医生只能给自己的病人添加病历
    $stmt = $conn->prepare("SELECT * FROM appointments WHERE doctor_id = ? AND patient_id = ?");
    $stmt->bind_param("ii", $doctor_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo "<script>alert('You are not authorized to add records for this patient.'); window.location.href='patient_records.php';</script>";
        exit;
    }

    // 插入病历记录
    $stmt = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, diagnosis, prescription, visit_date) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiss", $patient_id, $doctor_id, $diagnosis, $prescription);
    
    if ($stmt->execute()) {
        echo "<script>alert('Medical record added successfully!'); window.location.href='patient_history.php?patient_id=$patient_id';</script>";
    } else {
        echo "<script>alert('Error adding record. Try again.');</script>";
    }
}

// 获取当前医生的病人列表
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name 
    FROM users u
    JOIN appointments a ON u.id = a.patient_id
    WHERE a.doctor_id = ? AND u.role = 'patient'
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$patients = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Patient History</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-5" style="padding-top: 70px;">
    <h2>Patient Medical History</h2>
    <form method="GET">
        <div class="mb-3">
            <label class="form-label">Select Patient:</label>
            <select name="patient_id" class="form-control" required>
                <option value="">Select Patient</option>
                <?php while ($row = $patients->fetch_assoc()) { ?>
                    <option value="<?= htmlspecialchars($row['id']); ?>" <?= (isset($_GET['patient_id']) && $_GET['patient_id'] == $row['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($row['name']); ?></option>
                <?php } ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">View History</button>
    </form>

    <?php 
    if (isset($_GET['patient_id'])) {
        $patient_id = $_GET['patient_id'];

        // 确保医生只能查看自己病人的病历
        $stmt = $conn->prepare("SELECT * FROM appointments WHERE doctor_id = ? AND patient_id = ?");
        $stmt->bind_param("ii", $doctor_id, $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            echo "<div class='alert alert-danger mt-3'>You do not have permission to view this patient's records.</div>";
            exit;
        }

        // 获取病人姓名
        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $patient_name = $result->fetch_assoc()['name'];

        // 获取病人病历记录
        $stmt = $conn->prepare("
            SELECT mr.visit_date, u.name as doctor_name, mr.diagnosis, mr.prescription
            FROM medical_records mr
            JOIN users u ON mr.doctor_id = u.id
            WHERE mr.patient_id = ?
            ORDER BY mr.visit_date DESC
        ");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $records = $stmt->get_result();
    ?>
    <h3 class="mt-4">Medical History for <?= htmlspecialchars($patient_name); ?></h3>
    
    <?php if ($records->num_rows > 0) { ?>
        <table class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Doctor</th>
                    <th>Diagnosis</th>
                    <th>Prescription</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $records->fetch_assoc()) { ?>
                    <tr>
                        <td><?= htmlspecialchars($row['visit_date']); ?></td>
                        <td><?= htmlspecialchars($row['doctor_name']); ?></td>
                        <td><?= htmlspecialchars($row['diagnosis']); ?></td>
                        <td><?= htmlspecialchars($row['prescription']); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } else { ?>
        <div class="alert alert-info mt-3">No medical records found for this patient.</div>
    <?php } ?>

    <h3 class="mt-4">Add New Medical Record</h3>
    <form method="POST" class="mt-3">
        <input type="hidden" name="patient_id" value="<?= htmlspecialchars($patient_id); ?>">
        <div class="mb-3">
            <label class="form-label">Diagnosis:</label>
            <textarea name="diagnosis" class="form-control" rows="3" required></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Prescription:</label>
            <textarea name="prescription" class="form-control" rows="3" required></textarea>
        </div>
        <button type="submit" name="add_record" class="btn btn-success">Add Record</button>
    </form>
    <?php } ?>
</div>
</body>
</html>
