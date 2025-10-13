<?php
include '../includes/db.php';

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
if ($email === '') {
    echo '<div class="empty-state"><i class="fas fa-user-md"></i><h3>No Email Provided</h3></div>';
    exit;
}


$sql = "SELECT id, visit_date, diagnosis, treatment_plan, progress_notes FROM medical_records WHERE patient_email = ? ORDER BY visit_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo '<div class="medical-record" style="position: relative;">';
        echo '<div class="record-header">';
        echo '<span class="record-date">' . htmlspecialchars($row['visit_date']) . '</span>';
        echo '<span class="record-service">Diagnosis: ' . htmlspecialchars($row['diagnosis']) . '</span>';
        echo '</div>';
        echo '<div class="record-notes">';
        echo '<strong>Treatment Plan:</strong> ' . nl2br(htmlspecialchars($row['treatment_plan'])) . '<br>';
        echo '<strong>Progress Notes:</strong> ' . nl2br(htmlspecialchars($row['progress_notes'])) . '';
        echo '</div>';
        // View Report button
        echo '<div style="display: flex; justify-content: flex-end; margin-top: 10px;">';
        echo '<a href="view_report.php?record_id=' . urlencode($row['id']) . '&patient_email=' . urlencode($email) . '" class="btn btn-primary btn-sm" target="_blank" style="text-decoration:none;">';
        echo '<i class="fas fa-file-medical"></i> View Report';
        echo '</a>';
        echo '</div>';
        echo '</div>';
    }
} else {
    echo '<div class="empty-state"><i class="fas fa-notes-medical"></i><h3>No Medical Records Found</h3></div>';
}
$stmt->close();
$conn->close();
