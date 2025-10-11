<?php
require_once __DIR__ . "/../includes/db.php";

$test_start = '2025-09-08';
$test_end = '2025-10-08';

// 與reports.php完全相同的查詢
$date_filter = "created_at >= '" . $conn->real_escape_string($test_start) . " 00:00:00' AND created_at <= '" . $conn->real_escape_string($test_end) . " 23:59:59'";

// Monthly revenue查詢
$monthly_revenue_query = "
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
$monthly_revenue = $conn->query($monthly_revenue_query);

// Service performance查詢
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
$service_performance = $conn->query($service_performance_query);

// Payment analysis查詢
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
$payment_analysis = $conn->query($payment_analysis_query);

// 存儲數據
$service_performance_data = [];
if ($service_performance->num_rows > 0) {
    while($row = $service_performance->fetch_assoc()) {
        $service_performance_data[] = $row;
    }
}

$payment_analysis_data = [];
if ($payment_analysis->num_rows > 0) {
    while($row = $payment_analysis->fetch_assoc()) {
        $payment_analysis_data[] = $row;
    }
}

// 準備Monthly數據（使用與reports.php相同的邏輯）
$months = [];
$revenues = [];
$transactions = [];

$monthlyData = [];
$monthly_revenue->data_seek(0);
while($row = $monthly_revenue->fetch_assoc()) {
    $monthKey = $row['year'] . '-' . sprintf('%02d', $row['month']);
    $monthlyData[$monthKey] = [
        'revenue' => floatval($row['revenue']),
        'transactions' => intval($row['transaction_count']),
        'label' => date('M Y', mktime(0, 0, 0, $row['month'], 1, $row['year']))
    ];
}

// 排序
ksort($monthlyData);

foreach($monthlyData as $data) {
    $months[] = $data['label'];
    $revenues[] = $data['revenue'];
    $transactions[] = $data['transactions'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Chart Test</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container { 
            width: 800px; 
            height: 400px; 
            margin: 20px; 
            border: 1px solid #ccc; 
            padding: 20px;
        }
        table { 
            border-collapse: collapse; 
            margin: 20px 0; 
        }
        th, td { 
            border: 1px solid #ccc; 
            padding: 8px; 
        }
    </style>
</head>
<body>
    <h1>圖表測試頁面</h1>
    
    <h2>數據概覽:</h2>
    <p>Monthly Data: <?= count($months) ?> months</p>
    <p>Service Data: <?= count($service_performance_data) ?> services</p>
    <p>Payment Data: <?= count($payment_analysis_data) ?> methods</p>
    
    <!-- Monthly Revenue Chart -->
    <div class="chart-container">
        <h3>Monthly Revenue Chart</h3>
        <canvas id="monthlyChart" width="800" height="400"></canvas>
    </div>
    
    <!-- Service Performance Chart -->
    <div class="chart-container">
        <h3>Service Performance Chart</h3>
        <canvas id="serviceChart" width="800" height="400"></canvas>
    </div>
    
    <!-- Payment Methods Chart -->
    <div class="chart-container">
        <h3>Payment Methods Chart</h3>
        <canvas id="paymentChart" width="800" height="400"></canvas>
    </div>

    <!-- Data Tables -->
    <h3>Service Performance Data:</h3>
    <?php if (count($service_performance_data) > 0): ?>
    <table>
        <tr><th>Service</th><th>Frequency</th><th>Revenue</th></tr>
        <?php foreach($service_performance_data as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['service_name']) ?></td>
            <td><?= $row['frequency'] ?></td>
            <td>RM<?= number_format($row['total_revenue'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>No service data</p>
    <?php endif; ?>

    <h3>Payment Methods Data:</h3>
    <?php if (count($payment_analysis_data) > 0): ?>
    <table>
        <tr><th>Method</th><th>Amount</th><th>Percentage</th></tr>
        <?php foreach($payment_analysis_data as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['payment_method']) ?></td>
            <td>RM<?= number_format($row['total_amount'], 2) ?></td>
            <td><?= number_format($row['percentage'], 1) ?>%</td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>No payment data</p>
    <?php endif; ?>

    <script>
        console.log('🚀 Starting chart tests...');
        
        // Prepare data
        const monthlyData = {
            labels: <?= json_encode($months) ?>,
            revenues: <?= json_encode($revenues) ?>,
            transactions: <?= json_encode($transactions) ?>
        };
        
        const serviceData = {
            labels: <?= json_encode(array_column($service_performance_data, 'service_name')) ?>,
            revenues: <?= json_encode(array_map('floatval', array_column($service_performance_data, 'total_revenue'))) ?>
        };
        
        const paymentData = {
            labels: <?= json_encode(array_map('ucfirst', array_column($payment_analysis_data, 'payment_method'))) ?>,
            amounts: <?= json_encode(array_map('floatval', array_column($payment_analysis_data, 'total_amount'))) ?>
        };
        
        console.log('Monthly data:', monthlyData);
        console.log('Service data:', serviceData);
        console.log('Payment data:', paymentData);
        
        // Monthly Revenue Chart
        if (monthlyData.labels.length > 0) {
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: monthlyData.labels,
                    datasets: [{
                        label: 'Revenue (RM)',
                        data: monthlyData.revenues,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            console.log('✅ Monthly chart created');
        } else {
            console.log('❌ No monthly data');
        }
        
        // Service Performance Chart
        if (serviceData.labels.length > 0) {
            const serviceCtx = document.getElementById('serviceChart').getContext('2d');
            new Chart(serviceCtx, {
                type: 'bar',
                data: {
                    labels: serviceData.labels,
                    datasets: [{
                        label: 'Revenue (RM)',
                        data: serviceData.revenues,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            console.log('✅ Service chart created');
        } else {
            console.log('❌ No service data');
        }
        
        // Payment Methods Chart
        if (paymentData.labels.length > 0) {
            const paymentCtx = document.getElementById('paymentChart').getContext('2d');
            new Chart(paymentCtx, {
                type: 'doughnut',
                data: {
                    labels: paymentData.labels,
                    datasets: [{
                        data: paymentData.amounts,
                        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            console.log('✅ Payment chart created');
        } else {
            console.log('❌ No payment data');
        }
        
        console.log('🎯 All charts initialized');
    </script>
</body>
</html>