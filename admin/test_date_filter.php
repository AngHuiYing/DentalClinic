<?php
// 簡單的測試腳本來驗證日期過濾修復
require_once __DIR__ . "/../includes/db.php";

// 測試日期範圍
$test_start = '2025-09-01';
$test_end = '2025-09-30';

echo "<h2>日期過濾測試結果</h2>";
echo "<p>測試期間: {$test_start} 到 {$test_end}</p>";

// 測試 billing 表 (使用 created_at)
$billing_query = "SELECT COUNT(*) as count, SUM(amount) as total FROM billing 
                  WHERE created_at >= '{$test_start} 00:00:00' 
                  AND created_at <= '{$test_end} 23:59:59'";
$billing_result = $conn->query($billing_query);
$billing_data = $billing_result->fetch_assoc();
echo "<h3>Billing 表:</h3>";
echo "<p>記錄數: {$billing_data['count']}, 總收入: RM" . number_format($billing_data['total'] ?? 0, 2) . "</p>";

// 測試 appointments 表 (使用 appointment_date)
$appointments_query = "SELECT COUNT(*) as count FROM appointments 
                       WHERE appointment_date >= '{$test_start}' 
                       AND appointment_date <= '{$test_end}'";
$appointments_result = $conn->query($appointments_query);
$appointments_data = $appointments_result->fetch_assoc();
echo "<h3>Appointments 表:</h3>";
echo "<p>預約數: {$appointments_data['count']}</p>";

// 測試 medical_records 表 (使用 visit_date 和 created_at)
$medical_query = "SELECT COUNT(*) as count FROM medical_records 
                  WHERE (visit_date >= '{$test_start}' AND visit_date <= '{$test_end}')
                  OR (created_at >= '{$test_start} 00:00:00' AND created_at <= '{$test_end} 23:59:59')";
$medical_result = $conn->query($medical_query);
$medical_data = $medical_result->fetch_assoc();
echo "<h3>Medical Records 表:</h3>";
echo "<p>醫療記錄數: {$medical_data['count']}</p>";

// 測試 Service Performance
$service_query = "SELECT 
    s.name as service_name,
    COUNT(mrs.service_id) as frequency,
    SUM(s.price) as total_revenue
FROM medical_record_services mrs
JOIN services s ON mrs.service_id = s.id
JOIN medical_records mr ON mrs.medical_record_id = mr.id
WHERE (mr.visit_date >= '{$test_start}' AND mr.visit_date <= '{$test_end}')
   OR (mr.created_at >= '{$test_start} 00:00:00' AND mr.created_at <= '{$test_end} 23:59:59')
GROUP BY s.id, s.name
ORDER BY total_revenue DESC
LIMIT 5";

$service_result = $conn->query($service_query);
echo "<h3>Service Performance (Top 5):</h3>";
if ($service_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>服務名稱</th><th>頻率</th><th>總收入</th></tr>";
    while($row = $service_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['service_name']) . "</td>";
        echo "<td>" . $row['frequency'] . "</td>";
        echo "<td>RM" . number_format($row['total_revenue'] ?? 0, 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>沒有找到服務數據</p>";
}

echo "<hr>";
echo "<p><strong>測試完成！</strong> 如果看到數據，說明日期過濾修復成功。</p>";
echo "<p><a href='reports.php?start_date={$test_start}&end_date={$test_end}'>測試實際報表</a></p>";
?>