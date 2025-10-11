<?php
require_once __DIR__ . "/../includes/db.php";

echo "<h2>Service Performance 調試</h2>";

$test_start = '2025-09-08';
$test_end = '2025-10-08';

// 測試 Service Performance 查詢
$service_performance_query = "
    SELECT 
        s.name as service_name,
        s.price as unit_price,
        COUNT(mrs.service_id) as frequency,
        SUM(s.price) as total_revenue,
        ROUND(AVG(s.price), 2) as avg_price
    FROM medical_record_services mrs
    JOIN services s ON mrs.service_id = s.id
    JOIN medical_records mr ON mrs.medical_record_id = mr.id
    WHERE (
        (mr.visit_date >= '" . $conn->real_escape_string($test_start) . "' AND mr.visit_date <= '" . $conn->real_escape_string($test_end) . "')
        OR (mr.created_at >= '" . $conn->real_escape_string($test_start) . " 00:00:00' AND mr.created_at <= '" . $conn->real_escape_string($test_end) . " 23:59:59')
    )
    GROUP BY s.id, s.name, s.price 
    ORDER BY total_revenue DESC 
    LIMIT 10";

echo "<h3>🔍 Service Performance 查詢:</h3>";
echo "<pre>" . htmlspecialchars($service_performance_query) . "</pre>";

$result = $conn->query($service_performance_query);
if ($result->num_rows > 0) {
    echo "<h3>✅ Service Performance 結果:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>服務名稱</th><th>單價</th><th>頻率</th><th>總收入</th><th>平均價格</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['service_name']) . "</td>";
        echo "<td>RM" . number_format($row['unit_price'], 2) . "</td>";
        echo "<td>" . $row['frequency'] . "</td>";
        echo "<td>RM" . number_format($row['total_revenue'], 2) . "</td>";
        echo "<td>RM" . number_format($row['avg_price'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ Service Performance 沒有結果！</p>";
    
    // 檢查相關表是否有數據
    echo "<h3>🔍 檢查相關表:</h3>";
    
    // 檢查 medical_record_services 表
    $mrs_check = "SELECT COUNT(*) as count FROM medical_record_services";
    $result = $conn->query($mrs_check);
    $row = $result->fetch_assoc();
    echo "<p>medical_record_services 總記錄數: " . $row['count'] . "</p>";
    
    // 檢查 services 表
    $services_check = "SELECT COUNT(*) as count FROM services";
    $result = $conn->query($services_check);
    $row = $result->fetch_assoc();
    echo "<p>services 總記錄數: " . $row['count'] . "</p>";
    
    // 檢查在指定期間內的 medical_records
    $mr_check = "SELECT COUNT(*) as count FROM medical_records 
                 WHERE (visit_date >= '$test_start' AND visit_date <= '$test_end')
                    OR (created_at >= '$test_start 00:00:00' AND created_at <= '$test_end 23:59:59')";
    $result = $conn->query($mr_check);
    $row = $result->fetch_assoc();
    echo "<p>指定期間內的 medical_records: " . $row['count'] . "</p>";
}

// 測試Payment Analysis
echo "<h3>💳 Payment Analysis 測試:</h3>";
$payment_query = "
    SELECT 
        payment_method,
        COUNT(*) as transaction_count,
        SUM(amount) as total_amount,
        AVG(amount) as avg_amount,
        (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM billing WHERE created_at >= '$test_start 00:00:00' AND created_at <= '$test_end 23:59:59')) as percentage
    FROM billing 
    WHERE created_at >= '$test_start 00:00:00' AND created_at <= '$test_end 23:59:59'
    GROUP BY payment_method 
    ORDER BY total_amount DESC";

echo "<pre>" . htmlspecialchars($payment_query) . "</pre>";

$result = $conn->query($payment_query);
if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>付款方式</th><th>交易數</th><th>總金額</th><th>平均金額</th><th>百分比</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['payment_method']) . "</td>";
        echo "<td>" . $row['transaction_count'] . "</td>";
        echo "<td>RM" . number_format($row['total_amount'], 2) . "</td>";
        echo "<td>RM" . number_format($row['avg_amount'], 2) . "</td>";
        echo "<td>" . number_format($row['percentage'], 1) . "%</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ Payment Analysis 沒有結果！</p>";
}
?>