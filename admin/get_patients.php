<?php
require_once __DIR__ . "/../db.php";

$query = $_GET['query'] ?? '';

$sql = "SELECT 
            u.id, 
            COALESCE(u.name, a.patient_name) AS name,
            COALESCE(u.email, a.patient_email) AS email,
            a.patient_phone
        FROM appointments a
        LEFT JOIN users u ON a.patient_id = u.id
        WHERE a.status = 'confirmed' 
          AND (
                u.name LIKE ? 
             OR a.patient_name LIKE ? 
             OR u.email LIKE ? 
             OR a.patient_email LIKE ?
          )
        ORDER BY name ASC
        LIMIT 10";

$stmt = $conn->prepare($sql);

$search = "%$query%";
$stmt->bind_param("ssss", $search, $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while($row = $result->fetch_assoc()){
    $data[] = [
        "name" => $row['name'],
        "email" => $row['email'],
        "patient_phone" => $row['patient_phone']
    ];
}
echo json_encode($data);
