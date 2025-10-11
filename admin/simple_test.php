<?php
require_once __DIR__ . "/../includes/db.php";

$test_start = '2025-09-08';
$test_end = '2025-10-08';

// Build date filter
$date_filter = "created_at >= '" . $conn->real_escape_string($test_start) . " 00:00:00' AND created_at <= '" . $conn->real_escape_string($test_end) . " 23:59:59'";

echo "<h2>簡化測試 - 檢查查詢結果</h2>";

// Service performance query (exactly as in reports.php)
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

echo "<h3>🔍 Service Performance 查詢結果:</h3>";
$service_performance = $conn->query($service_performance_query);
echo "<p>行數: " . $service_performance->num_rows . "</p>";

if ($service_performance->num_rows > 0) {
    echo "<h4>✅ 有數據，生成表格:</h4>";
    ?>
    <table border="1" style="border-collapse: collapse; margin: 10px 0;">
        <thead>
            <tr>
                <th>Service</th>
                <th>Frequency</th>
                <th>Unit Price</th>
                <th>Total Revenue</th>
                <th>Performance</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $max_revenue = 0;
            $services_data = [];
            while($row = $service_performance->fetch_assoc()) {
                $services_data[] = $row;
                if ($row['total_revenue'] > $max_revenue) {
                    $max_revenue = $row['total_revenue'];
                }
            }
            foreach($services_data as $row): 
                $performance_pct = $max_revenue > 0 ? ($row['total_revenue'] / $max_revenue) * 100 : 0;
            ?>
            <tr>
                <td><?= htmlspecialchars($row['service_name']) ?></td>
                <td><?= $row['frequency'] ?>×</td>
                <td>RM<?= number_format($row['unit_price'], 2) ?></td>
                <td>RM<?= number_format($row['total_revenue'], 2) ?></td>
                <td><?= round($performance_pct) ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
} else {
    echo "<p>❌ 沒有數據</p>";
}

// Payment analysis query
$payment_analysis_query = "
    SELECT 
        payment_method,
        COUNT(*) as transaction_count,
        SUM(amount) as total_amount,
        AVG(amount) as avg_amount,
        (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM billing WHERE " . $date_filter . ")) as percentage
    FROM billing 
    WHERE " . $date_filter . "
    GROUP BY payment_method 
    ORDER BY total_amount DESC
";

echo "<h3>💳 Payment Analysis 查詢結果:</h3>";
$payment_analysis = $conn->query($payment_analysis_query);
echo "<p>行數: " . $payment_analysis->num_rows . "</p>";

if ($payment_analysis->num_rows > 0) {
    echo "<h4>✅ 有數據，生成表格:</h4>";
    ?>
    <table border="1" style="border-collapse: collapse; margin: 10px 0;">
        <thead>
            <tr>
                <th>Payment Method</th>
                <th>Transactions</th>
                <th>Total Amount</th>
                <th>Avg Amount</th>
                <th>Percentage</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $payment_analysis->fetch_assoc()): ?>
            <tr>
                <td><?= ucfirst($row['payment_method']) ?></td>
                <td><?= $row['transaction_count'] ?></td>
                <td>RM<?= number_format($row['total_amount'], 2) ?></td>
                <td>RM<?= number_format($row['avg_amount'], 2) ?></td>
                <td><?= number_format($row['percentage'], 1) ?>%</td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php
} else {
    echo "<p>❌ 沒有數據</p>";
}

echo "<hr>";
echo "<p><a href='reports.php?start_date={$test_start}&end_date={$test_end}'>🔗 返回完整報表</a></p>";
?>