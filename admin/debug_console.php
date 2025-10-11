<?php
require_once __DIR__ . "/../includes/db.php";

$test_start = '2025-09-08';
$test_end = '2025-10-08';

// 完全相同的查詢邏輯
$selected_start_date = $test_start;
$selected_end_date = $test_end;

$date_filter = "created_at >= '" . $conn->real_escape_string($selected_start_date) . " 00:00:00' AND created_at <= '" . $conn->real_escape_string($selected_end_date) . " 23:59:59'";

// Monthly revenue
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

// Service performance
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
        (mr.visit_date >= '" . $conn->real_escape_string($selected_start_date) . "' AND mr.visit_date <= '" . $conn->real_escape_string($selected_end_date) . "')
        OR (mr.created_at >= '" . $conn->real_escape_string($selected_start_date) . " 00:00:00' AND mr.created_at <= '" . $conn->real_escape_string($selected_end_date) . " 23:59:59')
    )
    GROUP BY s.id, s.name, s.price ORDER BY total_revenue DESC LIMIT 10";
$service_performance = $conn->query($service_performance_query);

// Payment analysis
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

// Monthly data processing
$months = [];
$revenues = [];
$transactions = [];

if (!empty($selected_start_date) && !empty($selected_end_date)) {
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
    
    ksort($monthlyData);
    
    foreach($monthlyData as $data) {
        $months[] = $data['label'];
        $revenues[] = $data['revenue'];
        $transactions[] = $data['transactions'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Data Debug Console</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <h1>Reports.php 數據調試控制台</h1>
    
    <h2>數據統計:</h2>
    <ul>
        <li>Monthly Revenue Records: <?= $monthly_revenue->num_rows ?></li>
        <li>Service Performance Records: <?= count($service_performance_data) ?></li>
        <li>Payment Analysis Records: <?= count($payment_analysis_data) ?></li>
    </ul>
    
    <div style="display: flex; gap: 20px;">
        <!-- Monthly Chart -->
        <div style="width: 400px; height: 300px;">
            <h3>Monthly Revenue</h3>
            <canvas id="monthlyChart"></canvas>
        </div>
        
        <!-- Service Chart -->
        <div style="width: 400px; height: 300px;">
            <h3>Service Performance</h3>
            <canvas id="serviceChart"></canvas>
        </div>
        
        <!-- Payment Chart -->
        <div style="width: 400px; height: 300px;">
            <h3>Payment Methods</h3>
            <canvas id="paymentChart"></canvas>
        </div>
    </div>

    <script>
        console.log('=== Data Debug Console ===');
        
        // 使用與reports.php完全相同的數據準備邏輯
        const monthlyRevenueData = {
            labels: <?= json_encode($months) ?>,
            revenues: <?= json_encode($revenues) ?>,
            transactions: <?= json_encode($transactions) ?>
        };
        
        const servicePerformanceData = {
            labels: <?= json_encode(count($service_performance_data) > 0 ? array_column($service_performance_data, 'service_name') : []) ?>,
            revenues: <?= json_encode(count($service_performance_data) > 0 ? array_map('floatval', array_column($service_performance_data, 'total_revenue')) : []) ?>,
            frequencies: <?= json_encode(count($service_performance_data) > 0 ? array_map('intval', array_column($service_performance_data, 'frequency')) : []) ?>
        };
        
        const paymentMethodData = {
            labels: <?= json_encode(count($payment_analysis_data) > 0 ? array_map('ucfirst', array_column($payment_analysis_data, 'payment_method')) : []) ?>,
            amounts: <?= json_encode(count($payment_analysis_data) > 0 ? array_map('floatval', array_column($payment_analysis_data, 'total_amount')) : []) ?>,
            percentages: <?= json_encode(count($payment_analysis_data) > 0 ? array_map('floatval', array_column($payment_analysis_data, 'percentage')) : []) ?>
        };
        
        console.log('Monthly data:', monthlyRevenueData);
        console.log('Service data:', servicePerformanceData);
        console.log('Payment data:', paymentMethodData);
        
        // Create Monthly Chart
        if (monthlyRevenueData.labels.length > 0) {
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: monthlyRevenueData.labels,
                    datasets: [{
                        label: 'Revenue (RM)',
                        data: monthlyRevenueData.revenues,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        fill: true
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
            console.log('✅ Monthly chart created');
        } else {
            console.log('❌ No monthly data');
            document.getElementById('monthlyChart').parentElement.innerHTML += '<p>No monthly data available</p>';
        }
        
        // Create Service Chart
        if (servicePerformanceData.labels.length > 0) {
            const serviceCtx = document.getElementById('serviceChart').getContext('2d');
            new Chart(serviceCtx, {
                type: 'bar',
                data: {
                    labels: servicePerformanceData.labels,
                    datasets: [{
                        label: 'Revenue (RM)',
                        data: servicePerformanceData.revenues,
                        backgroundColor: 'rgba(34, 197, 94, 0.7)'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
            console.log('✅ Service chart created');
        } else {
            console.log('❌ No service data');
            document.getElementById('serviceChart').parentElement.innerHTML += '<p>No service data available</p>';
        }
        
        // Create Payment Chart
        if (paymentMethodData.labels.length > 0) {
            const paymentCtx = document.getElementById('paymentChart').getContext('2d');
            new Chart(paymentCtx, {
                type: 'doughnut',
                data: {
                    labels: paymentMethodData.labels,
                    datasets: [{
                        data: paymentMethodData.amounts,
                        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
            console.log('✅ Payment chart created');
        } else {
            console.log('❌ No payment data');
            document.getElementById('paymentChart').parentElement.innerHTML += '<p>No payment data available</p>';
        }
        
        console.log('🎯 Debug console completed');
    </script>
</body>
</html>