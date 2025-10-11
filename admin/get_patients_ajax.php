<?php
session_start();
include '../includes/db.php';

// Check if user is admin
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get all confirmed patients
    $sql = "
        SELECT DISTINCT 
            a.patient_email as email, 
            a.patient_name as name, 
            a.patient_phone as phone
        FROM appointments a
        WHERE a.status = 'confirmed' 
        AND a.patient_email IS NOT NULL 
        AND a.patient_name IS NOT NULL
        ORDER BY a.patient_name ASC
    ";
    
    $result = $conn->query($sql);
    $patients = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $patients[] = [
                'email' => $row['email'],
                'name' => $row['name'],
                'phone' => $row['phone'] ?? 'N/A'
            ];
        }
    }
    
    echo json_encode($patients);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch patients: ' . $e->getMessage()]);
}

$conn->close();
?>