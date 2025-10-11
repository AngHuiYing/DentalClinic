<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include_once('../includes/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_SESSION['user_id'];
    $gender = $_POST['gender'] ?? null;
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    
    // Validate input
    if (empty($gender) || empty($date_of_birth)) {
        echo json_encode(['success' => false, 'message' => 'Gender and date of birth are required']);
        exit;
    }
    
    // Validate gender
    if (!in_array($gender, ['male', 'female', 'other'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid gender selection']);
        exit;
    }
    
    // Validate date of birth
    $dob = DateTime::createFromFormat('Y-m-d', $date_of_birth);
    if (!$dob || $dob->format('Y-m-d') !== $date_of_birth) {
        echo json_encode(['success' => false, 'message' => 'Invalid date of birth format']);
        exit;
    }
    
    // Check if date is not in the future
    $today = new DateTime();
    if ($dob > $today) {
        echo json_encode(['success' => false, 'message' => 'Date of birth cannot be in the future']);
        exit;
    }
    
    // Check if age is reasonable (not more than 120 years old)
    $age = $today->diff($dob)->y;
    if ($age > 120) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid date of birth']);
        exit;
    }
    
    try {
        // Update user information
        $sql = "UPDATE users SET gender = ?, date_of_birth = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $gender, $date_of_birth, $patient_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Profile information updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile information']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>