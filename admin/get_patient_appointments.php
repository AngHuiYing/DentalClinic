<?php
include '../includes/db.php';

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
if ($email === '') {
    echo '<div class="empty-state"><i class="fas fa-calendar"></i><h3>No Email Provided</h3></div>';
    exit;
}


$sql = "SELECT appointment_date, appointment_time, doctor_id, status, message FROM appointments WHERE patient_email = ? ORDER BY appointment_date DESC, appointment_time DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo '<div class="medical-record">';
        echo '<div class="record-header">';
        echo '<span class="record-date">' . htmlspecialchars($row['appointment_date']) . ' ' . htmlspecialchars($row['appointment_time']) . '</span>';
        echo '<span class="record-service">Doctor ID: ' . htmlspecialchars($row['doctor_id']) . '</span>';
        echo '</div>';
        echo '<div class="record-notes">Status: ' . htmlspecialchars($row['status']) . '<br>';
        echo '<strong>Notes:</strong> ' . nl2br(htmlspecialchars($row['message'])) . '</div>';
        echo '</div>';
    }
} else {
    echo '<div class="empty-state"><i class="fas fa-calendar-times"></i><h3>No Appointments Found</h3></div>';
}
$stmt->close();
$conn->close();
