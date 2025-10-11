<?php
// AJAX endpoint for deleting a medical record
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_id'])) {
    $record_id = intval($_POST['record_id']);
    // 只允許刪除自己病患的紀錄
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT d.id as doctor_id FROM medical_records mr JOIN doctors d ON mr.doctor_id = d.id WHERE mr.id = ? AND d.user_id = ?");
    $stmt->bind_param("ii", $record_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo "Unauthorized";
        exit;
    }
    // 刪除紀錄
    $del_stmt = $conn->prepare("DELETE FROM medical_records WHERE id = ?");
    $del_stmt->bind_param("i", $record_id);
    if ($del_stmt->execute()) {
        echo "success";
    } else {
        echo "Error: " . $conn->error;
    }
    exit;
}

echo "Invalid request";
