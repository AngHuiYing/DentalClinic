<?php
include '../includes/db.php';

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
if ($email === '') {
    echo '<div class="empty-state"><i class="fas fa-calendar"></i><h3>No Email Provided</h3></div>';
    exit;
}



$sql = "SELECT a.appointment_date, a.appointment_time, a.doctor_id, d.name AS doctor_name, a.status, a.message FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.id WHERE a.patient_email = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo '<div class="medical-record">';
        echo '<div class="record-header" style="display: flex; justify-content: space-between; align-items: flex-start;">';
        echo '<span class="record-date">' . htmlspecialchars($row['appointment_date']) . ' ' . htmlspecialchars($row['appointment_time']) . '</span>';
        echo '<div style="text-align: right; min-width: 120px;">';
        echo '<span class="record-service" style="display: block;">Doctor ID: ' . htmlspecialchars($row['doctor_id']) . '</span>';
        if (!empty($row['doctor_name'])) {
            echo '<span style="font-size:0.88em;color:#4f46e5;display:block;margin-top:2px;">Dr. ' . htmlspecialchars($row['doctor_name']) . '</span>';
        }
        echo '</div>';
        echo '</div>';
        echo '<div class="record-notes" style="margin-bottom: 2px;"><span>Status: ' . htmlspecialchars($row['status']) . '</span></div>';
        echo '<div class="record-notes"><strong>Notes:</strong> ' . nl2br(htmlspecialchars($row['message'])) . '</div>';
        echo '</div>';
    }
} else {
    echo '<div class="empty-state"><i class="fas fa-calendar-times"></i><h3>No Appointments Found</h3></div>';
}
$stmt->close();
$conn->close();
