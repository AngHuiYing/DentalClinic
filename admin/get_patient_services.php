<?php
// 回傳某病患最近一次沒有 billing 記錄的 medical_record 所用服務及總價
require_once __DIR__ . "/../db.php";
$email = $_GET['patient_email'] ?? $_GET['email'] ?? '';
$response = ["success" => false, "services" => "", "amount" => 0];
if ($email) {
    // 找最近一筆還沒有對應 billing 記錄的 medical_record
    $sql = "SELECT mr.id 
            FROM medical_records mr 
            WHERE mr.patient_email = ? 
            AND NOT EXISTS (
                SELECT 1 FROM billing b 
                WHERE b.patient_email = mr.patient_email 
                AND b.created_at > mr.created_at
            )
            ORDER BY mr.created_at DESC 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $mrid = $row['id'];
        // 查詢所有服務
        $sql2 = "SELECT s.name, s.price FROM medical_record_services mrs JOIN services s ON mrs.service_id = s.id WHERE mrs.medical_record_id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i", $mrid);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $total = 0;
        $service_names = [];
        while($srv = $res2->fetch_assoc()) {
            $service_names[] = $srv['name'];
            $total += floatval($srv['price']);
        }
        if(count($service_names) > 0) {
            $response['success'] = true;
            $response['services'] = implode(",", $service_names);
            $response['amount'] = $total;
        }
    }
}
echo json_encode($response);
