<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: ../patient/login.php");
    exit;
}

// 获取患者信息
$patient_id = $_SESSION['user_id'];
$patient_stmt = $conn->prepare("SELECT name, email, phone, gender, date_of_birth FROM users WHERE id = ?");
$patient_stmt->bind_param("i", $patient_id);
$patient_stmt->execute();
$patient_result = $patient_stmt->get_result();
$patient_info = $patient_result->fetch_assoc();

if (!$patient_info) {
    die("Patient record not found!");
}

$patient_email = $patient_info['email'];

// 获取账单ID
$billing_id = isset($_GET['billing_id']) ? (int)$_GET['billing_id'] : 0;

if (!$billing_id) {
    die("Invalid billing ID.");
}

// 验证患者权限 - 确保这是患者自己的账单记录
$auth_stmt = $conn->prepare("
    SELECT * FROM billing 
    WHERE id = ? AND patient_email = ?
");
$auth_stmt->bind_param("is", $billing_id, $patient_email);
$auth_stmt->execute();
$billing_data = $auth_stmt->get_result()->fetch_assoc();

if (!$billing_data) {
    die("Billing record not found or access denied.");
}

// 尝试找到相关的医疗记录（通过服务名称和日期匹配）
$medical_record = null;
$medical_stmt = $conn->prepare("
    SELECT mr.*, d.name as doctor_name
    FROM medical_records mr 
    JOIN doctors d ON mr.doctor_id = d.id 
    WHERE mr.patient_email = ? 
    AND DATE(mr.visit_date) = DATE(?)
    ORDER BY mr.created_at DESC
    LIMIT 1
");
$medical_stmt->bind_param("ss", $patient_email, $billing_data['created_at']);
$medical_stmt->execute();
$medical_result = $medical_stmt->get_result();
if ($medical_result->num_rows > 0) {
    $medical_record = $medical_result->fetch_assoc();
}

// 获取相关的服务信息
$services_list = [];
$services_data = []; // 保存服务的详细数据
$total_services_amount = 0; // 计算服务总金额

// 方法1：從 medical_record 獲取詳細服務信息
if ($medical_record) {
    $services_stmt = $conn->prepare("
        SELECT s.name, s.price 
        FROM medical_record_services mrs 
        JOIN services s ON mrs.service_id = s.id 
        WHERE mrs.medical_record_id = ?
    ");
    $services_stmt->bind_param("i", $medical_record['id']);
    $services_stmt->execute();
    $services_result = $services_stmt->get_result();
    
    while ($service = $services_result->fetch_assoc()) {
        $services_list[] = $service['name'] . ' (RM' . number_format($service['price'], 2) . ')';
        $services_data[] = $service;
        $total_services_amount += $service['price'];
    }
}

// 方法2：如果沒有 medical_record，嘗試從 billing 中解析服務信息
if (empty($services_data)) {
    // 嘗試從 billing.service 欄位解析多個服務（假設以逗號分隔）
    $billing_services = explode(',', $billing_data['service']);
    $service_count = count($billing_services);
    $average_price = $billing_data['amount'] / $service_count; // 平均分配價格
    
    foreach ($billing_services as $index => $service_name) {
        $service_name = trim($service_name);
        if (!empty($service_name)) {
            // 嘗試從 services 表中查找確切價格
            $price_stmt = $conn->prepare("SELECT price FROM services WHERE name LIKE ? LIMIT 1");
            $search_name = '%' . $service_name . '%';
            $price_stmt->bind_param("s", $search_name);
            $price_stmt->execute();
            $price_result = $price_stmt->get_result();
            
            if ($price_row = $price_result->fetch_assoc()) {
                $service_price = $price_row['price'];
            } else {
                // 如果找不到確切價格，使用平均價格
                $service_price = $average_price;
            }
            
            $services_list[] = $service_name . ' (RM' . number_format($service_price, 2) . ')';
            $services_data[] = [
                'name' => $service_name,
                'price' => $service_price
            ];
            $total_services_amount += $service_price;
        }
    }
    
    // 如果只有一個服務，直接使用 billing 金額
    if (count($services_data) == 1) {
        $services_data[0]['price'] = $billing_data['amount'];
        $total_services_amount = $billing_data['amount'];
        $services_list[0] = $services_data[0]['name'] . ' (RM' . number_format($billing_data['amount'], 2) . ')';
    }
}

// 生成发票号码
$invoice_no = '#' . str_pad(abs(crc32($billing_data['created_at'] . $billing_data['service'])), 6, '0', STR_PAD_LEFT);
$mrn = 'MRN-' . str_pad($patient_id, 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dental Billing Report - <?= htmlspecialchars($patient_info['name']) ?></title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            color: #222; 
            margin: 0; 
            padding: 20px; 
            background: #f8f9fa;
        }
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            border: 1px solid #ddd; 
            padding: 30px; 
            border-radius: 6px; 
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #2c5aa0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 { 
            margin: 0; 
            font-size: 24px; 
            color: #2c5aa0;
            font-weight: 700;
        }
        .header .clinic-info {
            color: #666;
            margin-top: 10px;
            font-size: 14px;
        }
        h2 { 
            margin-top: 25px; 
            font-size: 18px; 
            border-bottom: 1px solid #ddd; 
            padding-bottom: 6px; 
            color: #2c5aa0;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
        }
        th, td { 
            padding: 10px; 
            border: 1px solid #eee; 
            text-align: left; 
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #2c5aa0;
            width: 200px;
        }
        .small { 
            font-size: 13px; 
            color: #555;
        }
        .billing-info {
            background: #e8f4f8;
            border: 2px solid #2c5aa0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        .billing-info h3 {
            color: #2c5aa0;
            margin: 0 0 10px 0;
            border: none;
            padding: 0;
        }
        .print-btn {
            background: #2c5aa0;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 20px 0;
            font-size: 1rem;
        }
        .print-btn:hover {
            background: #1e3f73;
        }
        .back-btn {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .signature-section {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid #ddd;
        }
        .signature-line {
            border-bottom: 1px solid #333;
            width: 300px;
            display: inline-block;
            margin-left: 20px;
        }
        @media print { 
            body { padding: 0; background: white; } 
            .container { 
                border: none; 
                box-shadow: none; 
                margin: 0;
                padding: 15px;
                font-size: 0.85rem;
                max-width: 100%;
            } 
            .print-btn, .back-btn, .billing-info { display: none; }
            
            /* 优化标题 */
            .header h1 {
                font-size: 1.8rem !important;
                margin-bottom: 0.5rem;
            }
            .header .clinic-info {
                font-size: 0.9rem;
                margin-top: 0.5rem;
            }
            
            /* 紧凑表格 */
            table {
                margin-top: 10px !important;
                font-size: 0.8rem;
            }
            th, td {
                padding: 8px 10px !important;
                border: 1px solid #ddd !important;
            }
            th {
                background-color: #f8f9fa !important;
                color: #2c5aa0 !important;
                width: 150px !important;
                font-size: 0.75rem !important;
            }
            
            /* 紧凑标题 */
            h2 {
                font-size: 1.1rem !important;
                margin-top: 20px !important;
                margin-bottom: 10px !important;
                border-bottom: 1px solid #ddd !important;
            }
            
            /* 元数据 */
            .small {
                font-size: 0.75rem !important;
                margin-bottom: 15px !important;
            }
            
            /* 签名区域 */
            .signature-section {
                margin-top: 25px !important;
                padding-top: 15px !important;
                page-break-inside: avoid;
            }
            .signature-section h2 {
                font-size: 1rem !important;
            }
            .signature-section p {
                font-size: 0.75rem !important;
                margin: 8px 0 !important;
            }
            .signature-line {
                width: 200px !important;
                margin-left: 15px !important;
            }
            
            /* 页面设置 */
            @page {
                size: A4;
                margin: 15mm;
            }
            
            /* 防止分页 */
            table { page-break-inside: avoid; }
            tr { page-break-inside: avoid; }
        }
        
        /* 移动端响应式设计 */
        @media screen and (max-width: 768px) {
            body {
                padding: 10px;
                font-size: 14px;
                line-height: 1.4;
            }
            
            .container {
                padding: 20px;
                margin: 0;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                max-width: 100%;
                overflow-x: hidden;
            }
            
            .header {
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            
            .header h1 {
                font-size: 1.8rem;
                margin-bottom: 8px;
            }
            
            .header .clinic-info {
                font-size: 0.9rem;
                margin-top: 8px;
            }
            
            .billing-info {
                padding: 15px;
                margin-bottom: 20px;
            }
            
            .billing-info h3 {
                font-size: 1.2rem;
                margin-bottom: 8px;
            }
            
            .small {
                font-size: 0.8rem;
                margin-bottom: 20px;
            }
            
            h2 {
                font-size: 1.1rem;
                margin-top: 20px;
                margin-bottom: 15px;
                padding-bottom: 8px;
            }
            
            /* 移动端表格优化 */
            table {
                font-size: 0.85rem;
                border: 1px solid #ddd;
                border-radius: 6px;
                overflow: hidden;
                width: 100%;
                table-layout: fixed;
                margin-top: 10px;
            }
            
            th {
                font-size: 0.8rem;
                padding: 8px 6px;
                width: 35%;
                word-wrap: break-word;
                background-color: #f8f9fa;
                border-right: 1px solid #ddd;
            }
            
            td {
                font-size: 0.85rem;
                padding: 8px 6px;
                word-wrap: break-word;
                word-break: break-word;
                hyphens: auto;
                border-right: 1px solid #ddd;
                background-color: white;
            }
            
            /* Receipt table 特殊处理 */
            table:last-of-type {
                table-layout: auto;
            }
            
            table:last-of-type th {
                width: auto;
                padding: 6px 4px;
                font-size: 0.75rem;
            }
            
            table:last-of-type td {
                padding: 6px 4px;
                font-size: 0.8rem;
            }
            
            /* 签名区域移动端优化 */
            .signature-section {
                margin-top: 30px;
                padding-top: 20px;
            }
            
            .signature-section h2 {
                font-size: 1rem;
                margin-bottom: 15px;
            }
            
            .signature-section p {
                font-size: 0.8rem;
                margin: 10px 0;
                line-height: 1.4;
            }
            
            .signature-line {
                width: 150px;
                margin-left: 10px;
            }
            
            /* 按钮移动端优化 */
            .print-btn, .back-btn {
                font-size: 0.9rem;
                padding: 10px 20px;
                margin: 10px 5px;
                border-radius: 6px;
                display: inline-block;
                text-align: center;
                min-width: 120px;
            }
            
            /* 移动端文字统一 */
            .container * {
                max-width: 100%;
                box-sizing: border-box;
            }
            
            /* 长文本处理 */
            .container td {
                overflow-wrap: break-word;
                word-wrap: break-word;
                -ms-word-break: break-all;
                word-break: break-word;
                -ms-hyphens: auto;
                -moz-hyphens: auto;
                -webkit-hyphens: auto;
                hyphens: auto;
            }
        }
        
        /* 超小屏幕优化 */
        @media screen and (max-width: 480px) {
            .container {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            h2 {
                font-size: 1rem;
            }
            
            table {
                font-size: 0.8rem;
            }
            
            th {
                font-size: 0.75rem;
                padding: 6px 4px;
            }
            
            td {
                font-size: 0.8rem;
                padding: 6px 4px;
            }
            
            table:last-of-type th {
                font-size: 0.7rem;
                padding: 4px 2px;
            }
            
            table:last-of-type td {
                font-size: 0.75rem;
                padding: 4px 2px;
            }
            
            .billing-info {
                font-size: 0.75rem;
                padding: 12px;
            }
            
            .print-btn, .back-btn {
                font-size: 0.85rem;
                padding: 8px 16px;
                margin: 8px 3px;
                display: block;
                width: calc(50% - 6px);
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="billing-info">
            <h3>🧾 BILLING REPORT</h3>
            <p>This is your billing receipt and medical service report.</p>
        </div>
        
        <div class="header">
            <h1>🦷 Dental Billing Report</h1>
            <div class="clinic-info">
                <strong>Green Life Dental Clinic</strong><br>
                Professional Dental Care Services
            </div>
        </div>
        
        <p class="small">
            <strong>Report Generated:</strong> <?= date('F j, Y \a\t g:i A') ?><br>
            <strong>Invoice No:</strong> <?= $invoice_no ?>
        </p>

        <h2>Patient Information</h2>
        <table>
            <tr><th>Name</th><td><?= htmlspecialchars($patient_info['name']) ?></td></tr>
            <tr><th>Date of Birth</th><td><?= $patient_info['date_of_birth'] ? date('F j, Y', strtotime($patient_info['date_of_birth'])) : 'Not specified' ?></td></tr>
            <tr><th>Gender</th><td><?= htmlspecialchars(ucfirst($patient_info['gender'] ?? 'Not specified')) ?></td></tr>
            <tr><th>Contact</th><td><?= htmlspecialchars($patient_info['phone'] ?? $patient_info['email']) ?></td></tr>
            <tr><th>Email</th><td><?= htmlspecialchars($patient_info['email']) ?></td></tr>
            <tr><th>Medical Record No.</th><td><?= $mrn ?></td></tr>
        </table>

        <?php if ($medical_record): ?>
        <h2>Visit Information</h2>
        <table>
            <tr><th>Visit Date</th><td><?= date('F j, Y', strtotime($medical_record['visit_date'])) ?></td></tr>
            <tr><th>Doctor</th><td>Dr. <?= htmlspecialchars($medical_record['doctor_name']) ?></td></tr>
            <tr><th>Chief Complaint</th><td><?= htmlspecialchars($medical_record['chief_complaint']) ?></td></tr>
            <tr><th>Diagnosis</th><td><?= htmlspecialchars($medical_record['diagnosis']) ?></td></tr>
            <tr><th>Treatment Plan</th><td><?= htmlspecialchars($medical_record['treatment_plan'] ?: 'None specified') ?></td></tr>
            <tr><th>Prescription</th><td><?= htmlspecialchars($medical_record['prescription'] ?: 'None prescribed') ?></td></tr>
            <tr><th>Progress Notes</th><td><?= htmlspecialchars($medical_record['progress_notes'] ?: 'No additional notes') ?></td></tr>
            <tr><th>Services</th><td><?= !empty($services_list) ? implode('<br>', $services_list) : 'No services recorded' ?></td></tr>
            <tr><th>Billing Amount</th><td><strong>RM <?= number_format($billing_data['amount'], 2) ?></strong></td></tr>
        </table>
        <?php else: ?>
        <h2>Service Information</h2>
        <table>
            <tr><th>Service Date</th><td><?= date('F j, Y', strtotime($billing_data['created_at'])) ?></td></tr>
            <tr><th>Service</th><td><?= htmlspecialchars($billing_data['service']) ?></td></tr>
            <tr><th>Amount</th><td><strong>RM <?= number_format($billing_data['amount'], 2) ?></strong></td></tr>
            <tr><th>Payment Method</th><td><?= htmlspecialchars($billing_data['payment_method']) ?></td></tr>
        </table>
        <?php endif; ?>

        <h2>Billing Receipt</h2>
        <table>
            <tr><th>Item No.</th><th>Receipt No.</th><th>Date</th><th>Service Description</th><th>Payment Method</th><th>Amount</th></tr>
            <?php if (!empty($services_data)): ?>
                <?php $item_no = 1; ?>
                <?php foreach ($services_data as $service): ?>
                <tr>
                    <td><strong><?= $item_no ?></strong></td>
                    <td><?= $invoice_no ?></td>
                    <td><?= date('F j, Y', strtotime($billing_data['created_at'])) ?></td>
                    <td><?= htmlspecialchars($service['name']) ?></td>
                    <td>
                        <?php if ($item_no == 1): // 只在第一行顯示付款方式 ?>
                        <?= htmlspecialchars($billing_data['payment_method']) ?>
                        <?php else: ?>
                        <em style="color: #666;">Same as above</em>
                        <?php endif; ?>
                    </td>
                    <td><strong>RM <?= number_format($service['price'], 2) ?></strong></td>
                </tr>
                <?php $item_no++; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- 如果沒有具體服務數據，顯示原來的單行記錄 -->
                <tr>
                    <td><strong>1</strong></td>
                    <td><?= $invoice_no ?></td>
                    <td><?= date('F j, Y', strtotime($billing_data['created_at'])) ?></td>
                    <td><?= htmlspecialchars($billing_data['service']) ?></td>
                    <td><?= htmlspecialchars($billing_data['payment_method']) ?></td>
                    <td><strong>RM <?= number_format($billing_data['amount'], 2) ?></strong></td>
                </tr>
            <?php endif; ?>
            <tr style="background: #f8f9fa; font-weight: bold;">
                <td colspan="5" style="text-align: right; padding-right: 20px;">Total Amount:</td>
                <td><strong>RM <?= number_format($billing_data['amount'], 2) ?></strong></td>
            </tr>
        </table>

        <div class="signature-section">
            <h2>Authorization & Signature</h2>
            <p>
                <br>
                <br>
                <strong>Doctor's Signature:</strong> 
                <span class="signature-line"></span>
            </p>
            <br>
            <p>
                <strong>Date:</strong> 
                <span class="signature-line"></span>
            </p>
            <br><br>
            <p class="small">
                <em>This report is generated electronically and contains confidential billing and medical information. 
                Please retain this document for your records and insurance purposes.</em>
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <button onclick="window.print()" class="print-btn">
                🖨️ Print Report
            </button>
            <a href="billing.php" class="back-btn">
                ← Back to Billing Records
            </a>
        </div>
    </div>
</body>
</html>