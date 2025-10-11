<?php
require_once __DIR__ . "/../includes/db.php";

echo "<h2>數據庫數據診斷 - 2025年9月到10月</h2>";
echo "<p>檢查期間: 2025-09-08 到 2025-10-08</p>";

// 檢查 billing 表
$billing_check = "SELECT 
    DATE(created_at) as date,
    COUNT(*) as count,
    SUM(amount) as total_amount,
    payment_method
FROM billing 
WHERE created_at >= '2025-09-08 00:00:00' 
AND created_at <= '2025-10-08 23:59:59'
GROUP BY DATE(created_at), payment_method
ORDER BY date DESC";

echo "<h3>🔍 Billing 表數據:</h3>";
$result = $conn->query($billing_check);
if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>日期</th><th>記錄數</th><th>總金額</th><th>付款方式</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['date'] . "</td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "<td>RM" . number_format($row['total_amount'], 2) . "</td>";
        echo "<td>" . $row['payment_method'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ <strong>沒有找到 billing 數據！</strong></p>";
}

// 檢查所有 billing 數據（不限日期）
$all_billing = "SELECT 
    DATE(created_at) as date,
    COUNT(*) as count,
    SUM(amount) as total_amount
FROM billing 
GROUP BY DATE(created_at)
ORDER BY date DESC
LIMIT 10";

echo "<h3>📊 最近的 billing 數據 (所有日期):</h3>";
$result = $conn->query($all_billing);
if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>日期</th><th>記錄數</th><th>總金額</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['date'] . "</td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "<td>RM" . number_format($row['total_amount'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ billing 表完全沒有數據！</p>";
}

// 檢查 appointments 表
$appointments_check = "SELECT 
    appointment_date,
    COUNT(*) as count,
    status
FROM appointments 
WHERE appointment_date >= '2025-09-08' 
AND appointment_date <= '2025-10-08'
GROUP BY appointment_date, status
ORDER BY appointment_date DESC";

echo "<h3>📅 Appointments 表數據:</h3>";
$result = $conn->query($appointments_check);
if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>預約日期</th><th>數量</th><th>狀態</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['appointment_date'] . "</td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ 沒有找到指定期間的 appointments 數據！</p>";
}

// 檢查 medical_records 表
$medical_check = "SELECT 
    visit_date,
    DATE(created_at) as created_date,
    COUNT(*) as count
FROM medical_records 
WHERE (visit_date >= '2025-09-08' AND visit_date <= '2025-10-08')
   OR (created_at >= '2025-09-08 00:00:00' AND created_at <= '2025-10-08 23:59:59')
GROUP BY visit_date, DATE(created_at)
ORDER BY visit_date DESC, created_date DESC";

echo "<h3>🏥 Medical Records 表數據:</h3>";
$result = $conn->query($medical_check);
if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>就診日期</th><th>創建日期</th><th>記錄數</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . ($row['visit_date'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['created_date'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ 沒有找到指定期間的 medical records 數據！</p>";
}

// 測試當前的過濾邏輯
echo "<h3>🧪 測試當前過濾查詢:</h3>";

$test_start = '2025-09-08';
$test_end = '2025-10-08';

// 建立和報表相同的過濾條件
$date_filter = "created_at >= '" . $conn->real_escape_string($test_start) . " 00:00:00' AND created_at <= '" . $conn->real_escape_string($test_end) . " 23:59:59'";

echo "<p><strong>使用的過濾條件:</strong> $date_filter</p>";

// 測試 monthly revenue 查詢
$monthly_test = "
    SELECT 
        MONTH(created_at) as month,
        YEAR(created_at) as year,
        SUM(amount) as revenue,
        COUNT(*) as transaction_count
    FROM billing 
    WHERE " . $date_filter . "
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY year, month
";

echo "<p><strong>Monthly Revenue 測試查詢:</strong></p>";
echo "<pre>" . htmlspecialchars($monthly_test) . "</pre>";

$result = $conn->query($monthly_test);
if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>年</th><th>月</th><th>收入</th><th>交易數</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['year'] . "</td>";
        echo "<td>" . $row['month'] . "</td>";
        echo "<td>RM" . number_format($row['revenue'], 2) . "</td>";
        echo "<td>" . $row['transaction_count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ Monthly Revenue 查詢沒有結果！</p>";
}

echo "<hr>";
echo "<p><a href='reports.php?start_date={$test_start}&end_date={$test_end}'>📊 測試報表頁面</a></p>";
?>