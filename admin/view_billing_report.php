<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 获取账单ID和患者邮箱
$billing_id = isset($_GET['billing_id']) ? (int)$_GET['billing_id'] : 0;
$patient_email = isset($_GET['patient_email']) ? trim($_GET['patient_email']) : '';

if (!$billing_id || !$patient_email) {
    die("Invalid request parameters.");
}

// 管理员有权限查看所有账单记录，无需额外验证
$billing_stmt = $conn->prepare("
    SELECT * FROM billing 
    WHERE id = ? AND patient_email = ?
");
$billing_stmt->bind_param("is", $billing_id, $patient_email);
$billing_stmt->execute();
$billing_data = $billing_stmt->get_result()->fetch_assoc();

if (!$billing_data) {
    die("Billing record not found.");
}

// 获取患者详细信息从 users 表
$user_stmt = $conn->prepare("SELECT name, email, phone, gender, date_of_birth FROM users WHERE email = ?");
$user_stmt->bind_param("s", $patient_email);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();

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
$mrn = 'MRN-' . str_pad($billing_id, 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dental Billing Report - Admin View</title>
    <style>
        body { 
            font-family: 'Inter', Arial, sans-serif; 
            color: #222; 
            margin: 0; 
            padding: 20px; 
            background: #f8f9fa;
            line-height: 1.6;
        }
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            border: 1px solid #ddd; 
            padding: 40px; 
            border-radius: 10px; 
            background: white;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #2c5aa0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 { 
            margin: 0; 
            font-size: 2.5rem; 
            color: #2c5aa0;
            font-weight: 700;
        }
        .header .clinic-info {
            color: #666;
            margin-top: 10px;
            font-size: 1rem;
        }
        .admin-badge {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 10px;
            display: inline-block;
        }
        .admin-controls {
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.1), rgba(238, 90, 82, 0.1));
            border: 2px solid rgba(255, 107, 107, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .admin-controls h3 {
            color: #ff6b6b;
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2rem;
        }
        .billing-info {
            background: linear-gradient(135deg, rgba(44, 90, 160, 0.1), rgba(123, 104, 238, 0.1));
            border: 2px solid rgba(44, 90, 160, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        .billing-info h3 {
            color: #2c5aa0;
            margin: 0 0 10px 0;
            font-size: 1.5rem;
            font-weight: 700;
        }
        h2 { 
            margin-top: 30px; 
            font-size: 1.3rem; 
            border-bottom: 2px solid #84c69b; 
            padding-bottom: 10px; 
            color: #2c5aa0;
            font-weight: 600;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        th, td { 
            padding: 12px 15px; 
            border-bottom: 1px solid #eee; 
            text-align: left; 
        }
        th {
            background: linear-gradient(135deg, #2c5aa0, #7b68ee);
            color: white;
            font-weight: 600;
            width: 200px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }
        td {
            background: white;
            transition: background-color 0.2s ease;
        }
        tr:hover td {
            background: rgba(44, 90, 160, 0.05);
        }
        .small { 
            font-size: 0.9rem; 
            color: #666;
            margin-bottom: 20px;
        }
        .print-btn {
            background: linear-gradient(135deg, #2c5aa0, #7b68ee);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin: 20px 5px;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .print-btn:hover {
            background: linear-gradient(135deg, #1e3f73, #6a5acd);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(44, 90, 160, 0.3);
        }
        .back-btn {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin: 20px 5px;
            text-decoration: none;
            display: inline-block;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background: #545b62;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
        .signature-section {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid #ddd;
        }
        .signature-line {
            border-bottom: 1px solid #333;
            width: 300px;
            display: inline-block;
            margin-left: 20px;
        }
        .amount-highlight {
            background: linear-gradient(135deg, #28a745, #20c997);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .receipt-table {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.05), rgba(32, 201, 151, 0.05));
            border-radius: 10px;
            overflow: hidden;
        }
        .receipt-table th {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        .total-row {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(32, 201, 151, 0.1));
            font-weight: 700;
            font-size: 1rem;
        }
        @media print { 
            body { padding: 0; background: white; } 
            .container { 
                border: none; 
                box-shadow: none; 
                margin: 0; 
                border-radius: 0; 
                padding: 20px;
                font-size: 0.85rem;
                max-width: 100%;
            } 
            .print-btn, .back-btn, .admin-controls, .admin-badge, .billing-info { display: none !important; }
            html, body { margin: 0; padding: 0; }
            
            /* Fix gradient text colors for print */
            .amount-highlight {
                background: none !important;
                -webkit-background-clip: initial !important;
                -webkit-text-fill-color: initial !important;
                background-clip: initial !important;
                color: #28a745 !important;
                font-weight: 700;
                font-size: 1rem;
            }
            
            /* Adjust header for print */
            .header h1 {
                font-size: 1.8rem !important;
                margin-bottom: 0.5rem;
            }
            
            .header .clinic-info {
                font-size: 0.9rem;
                margin-top: 0.5rem;
            }
            
            /* Compact table styling */
            table {
                margin-top: 10px !important;
                box-shadow: none !important;
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 8px 10px !important;
                border: 1px solid #ddd !important;
            }
            
            th {
                background: #f8f9fa !important;
                color: #2c5aa0 !important;
                font-size: 0.75rem !important;
                width: 150px !important;
            }
            
            h2 {
                font-size: 1.1rem !important;
                margin-top: 20px !important;
                margin-bottom: 10px !important;
                border-bottom: 1px solid #ddd !important;
            }
            
            /* 强制 Billing Receipt 标题在新页面开始 */
            .billing-receipt-title {
                page-break-before: always !important;
                margin-top: 0 !important;
                padding-top: 30px !important;
            }
            
            /* 第二页页眉样式 */
            .billing-receipt-title + div {
                font-size: 0.8rem !important;
                margin-bottom: 15px !important;
                page-break-after: avoid !important;
            }
            
            /* 确保第一页内容不会过度压缩 */
            .container > h2:not(.billing-receipt-title) {
                page-break-after: avoid !important;
            }
            
            /* 第一页表格样式 */
            .container > table:not(.receipt-table) {
                page-break-after: avoid !important;
            }
            
            .small {
                font-size: 0.75rem !important;
                margin-bottom: 15px !important;
            }
            
            .signature-section {
                margin-top: 30px !important;
                padding-top: 20px !important;
                page-break-inside: avoid;
            }
            
            .signature-section h2 {
                font-size: 1rem !important;
            }
            
            .signature-section p {
                font-size: 0.75rem !important;
                margin: 10px 0 !important;
            }
            
            .signature-line {
                width: 200px !important;
                margin-left: 15px !important;
            }
            
            /* Receipt table specific styling */
            .receipt-table {
                background: none !important;
                page-break-before: avoid !important; /* 避免重复分页，因为标题已经分页了 */
                page-break-inside: avoid !important;
            }
            
            .receipt-table th {
                background: #f8f9fa !important;
                color: #2c5aa0 !important;
            }
            
            .total-row {
                background: #f8f9fa !important;
                font-weight: 700 !important;
            }
            
            .total-row td {
                border-top: 2px solid #2c5aa0 !important;
            }
            
            /* Payment method styling for print */
            .receipt-table span {
                background: #2c5aa0 !important;
                color: white !important;
                padding: 2px 6px !important;
                border-radius: 3px !important;
                font-size: 0.7rem !important;
            }
            
            /* Ensure page fits on one sheet */
            @page {
                size: A4;
                margin: 12mm; /* 稍微减少边距给更多空间 */
            }
            
            /* 第一页内容分组 */
            .first-page-content {
                page-break-after: avoid;
            }
            
            /* Prevent page breaks in tables */
            table:not(.receipt-table) {
                page-break-inside: avoid;
                page-break-after: avoid;
            }
            
            .receipt-table {
                page-break-inside: avoid;
            }
            
            tr {
                page-break-inside: avoid;
            }
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
            
            .admin-badge {
                font-size: 0.8rem;
                padding: 6px 12px;
                margin-top: 8px;
            }
            
            .admin-controls {
                padding: 15px;
                margin-bottom: 20px;
            }
            
            .admin-controls h3 {
                font-size: 1rem;
                margin-bottom: 8px;
            }
            
            .admin-controls p {
                font-size: 0.85rem;
                line-height: 1.3;
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
                font-size: 0.8rem;
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
                background: linear-gradient(135deg, #2c5aa0, #7b68ee);
                color: white;
                border-right: 1px solid #ddd;
                text-transform: none;
                letter-spacing: normal;
            }
            
            td {
                font-size: 0.8rem;
                padding: 8px 6px;
                word-wrap: break-word;
                word-break: break-word;
                hyphens: auto;
                border-right: 1px solid #ddd;
                background: white;
            }
            
            /* Receipt table 特殊处理 */
            .receipt-table {
                table-layout: auto;
                background: none;
            }
            
            .receipt-table th {
                width: auto;
                padding: 6px 4px;
                font-size: 0.75rem;
                background: linear-gradient(135deg, #28a745, #20c997);
            }
            
            .receipt-table td {
                padding: 6px 4px;
                font-size: 0.8rem;
            }
            
            .total-row {
                background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(32, 201, 151, 0.1));
                font-weight: 700;
            }
            
            .total-row strong {
                font-size: 0.9rem !important;
            }
            
            .amount-highlight {
                color: #28a745;
                font-weight: 700;
                font-size: 0.9rem;
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
                font-size: 0.75rem;
            }
            
            th {
                font-size: 0.75rem;
                padding: 6px 4px;
            }
            
            td {
                font-size: 0.75rem;
                padding: 6px 4px;
            }
            
            .receipt-table th {
                font-size: 0.7rem;
                padding: 4px 2px;
            }
            
            .receipt-table td {
                font-size: 0.75rem;
                padding: 4px 2px;
            }
            
            .total-row strong {
                font-size: 0.8rem !important;
            }
            
            .admin-controls, .billing-info {
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
        <div class="admin-controls">
            <h3>
                <i style="color: #ff6b6b;">👑</i>
                Administrator Billing Control Panel
            </h3>
            <p style="margin: 0; color: #666;">
                <strong>Access Level:</strong> Full administrative access to all patient billing records<br>
                <strong>Generated By:</strong> Admin - <?= htmlspecialchars($_SESSION['admin_username'] ?? 'System Admin') ?><br>
                <strong>Billing Record ID:</strong> <?= $billing_id ?><br>
                <strong>Patient Access:</strong> 
                <span style="color: #28a745; font-weight: 600;">
                    ✅ Authorized Administrator View
                </span>
            </p>
        </div>
        
        <div class="billing-info">
            <h3>🧾 BILLING REPORT</h3>
            <p>Administrative billing receipt and medical service report.</p>
        </div>
        
        <div class="header">
            <h1>🦷 Dental Billing Report</h1>
            <div class="clinic-info">
                <strong>Green Life Dental Clinic</strong><br>
                Professional Dental Care Services
            </div>
            <div class="admin-badge">
                👑 Administrator View
            </div>
        </div>
        
        <div class="small">
            <strong>Report Generated:</strong> <?= date('F j, Y \a\t g:i A') ?><br>
            <strong>Invoice No:</strong> <?= $invoice_no ?><br>
            <strong>Accessed By:</strong> Administrator
        </div>

        <h2>👤 Patient Information</h2>
        <table>
            <tr><th>Full Name</th><td><?= htmlspecialchars($user_data['name'] ?? $billing_data['patient_name']) ?></td></tr>
            <tr><th>Date of Birth</th><td><?= $user_data['date_of_birth'] ? date('F j, Y', strtotime($user_data['date_of_birth'])) : 'Not specified' ?></td></tr>
            <tr><th>Gender</th><td><?= htmlspecialchars(ucfirst($user_data['gender'] ?? 'Not specified')) ?></td></tr>
            <tr><th>Contact Phone</th><td><?= htmlspecialchars($user_data['phone'] ?? $billing_data['patient_phone']) ?></td></tr>
            <tr><th>Email Address</th><td><?= htmlspecialchars($patient_email) ?></td></tr>
            <tr><th>Medical Record No.</th><td><?= $mrn ?></td></tr>
        </table>

        <?php if ($medical_record): ?>
        <h2>🏥 Visit Information</h2>
        <table>
            <tr><th>Visit Date</th><td><?= date('F j, Y', strtotime($medical_record['visit_date'])) ?></td></tr>
            <tr><th>Attending Doctor</th><td>Dr. <?= htmlspecialchars($medical_record['doctor_name']) ?></td></tr>
            <tr><th>Chief Complaint</th><td><?= htmlspecialchars($medical_record['chief_complaint']) ?></td></tr>
            <tr><th>Clinical Diagnosis</th><td><?= htmlspecialchars($medical_record['diagnosis']) ?></td></tr>
            <tr><th>Treatment Plan</th><td><?= htmlspecialchars($medical_record['treatment_plan'] ?: 'None specified') ?></td></tr>
            <tr><th>Prescription</th><td><?= htmlspecialchars($medical_record['prescription'] ?: 'None prescribed') ?></td></tr>
            <tr><th>Progress Notes</th><td><?= htmlspecialchars($medical_record['progress_notes'] ?: 'No additional notes') ?></td></tr>
            <tr><th>Services Performed</th><td><?= !empty($services_list) ? implode('<br>', $services_list) : 'No services recorded' ?></td></tr>
            <tr><th>Billing Amount</th><td><span class="amount-highlight">RM <?= number_format($billing_data['amount'], 2) ?></span></td></tr>
        </table>
        <?php else: ?>
        <h2>🩺 Service Information</h2>
        <table>
            <tr><th>Service Date</th><td><?= date('F j, Y', strtotime($billing_data['created_at'])) ?></td></tr>
            <tr><th>Service Description</th><td><?= htmlspecialchars($billing_data['service']) ?></td></tr>
            <tr><th>Service Amount</th><td><span class="amount-highlight">RM <?= number_format($billing_data['amount'], 2) ?></span></td></tr>
            <tr><th>Payment Method</th><td><?= htmlspecialchars($billing_data['payment_method']) ?></td></tr>
        </table>
        <?php endif; ?>

        <h2 class="billing-receipt-title">💰 Billing Receipt</h2>
        <div style="text-align: center; margin-bottom: 20px; font-size: 0.9rem; color: #666;">
            <strong>Green Life Dental Clinic</strong> | Invoice: <?= $invoice_no ?>
        </div>
        <table class="receipt-table">
            <thead>
                <tr>
                    <th>Item No.</th>
                    <th>Receipt No.</th>
                    <th>Service Date</th>
                    <th>Service Description</th>
                    <th>Payment Method</th>
                    <th>Amount (RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($services_data)): ?>
                    <?php $item_no = 1; ?>
                    <?php foreach ($services_data as $service): ?>
                    <tr>
                        <td><strong><?= $item_no ?></strong></td>
                        <td><strong><?= $invoice_no ?></strong></td>
                        <td><?= date('F j, Y', strtotime($billing_data['created_at'])) ?></td>
                        <td><?= htmlspecialchars($service['name']) ?></td>
                        <td>
                            <?php if ($item_no == 1): // 只在第一行顯示付款方式 ?>
                            <span style="background: linear-gradient(135deg, #007bff, #6610f2); color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;"><?= htmlspecialchars($billing_data['payment_method']) ?></span>
                            <?php else: ?>
                            <span style="color: #666; font-style: italic;">Same as above</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="amount-highlight">RM <?= number_format($service['price'], 2) ?></span></td>
                    </tr>
                    <?php $item_no++; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- 如果沒有具體服務數據，顯示原來的單行記錄 -->
                    <tr>
                        <td><strong>1</strong></td>
                        <td><strong><?= $invoice_no ?></strong></td>
                        <td><?= date('F j, Y', strtotime($billing_data['created_at'])) ?></td>
                        <td><?= htmlspecialchars($billing_data['service']) ?></td>
                        <td><span style="background: linear-gradient(135deg, #007bff, #6610f2); color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;"><?= htmlspecialchars($billing_data['payment_method']) ?></span></td>
                        <td><span class="amount-highlight">RM <?= number_format($billing_data['amount'], 2) ?></span></td>
                    </tr>
                <?php endif; ?>
                
                <!-- 總計行 -->
                <tr class="total-row">
                    <td colspan="5" style="text-align: right; padding-right: 20px;">
                        <strong>Total Amount Paid:</strong>
                    </td>
                    <td><strong style="color: #28a745; font-size: 1.2rem;">RM <?= number_format($billing_data['amount'], 2) ?></strong></td>
                </tr>
            </tbody>
        </table>

        <div class="signature-section">
            <h2>✍️ Authorization & Signature</h2>
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
                Please retain this document for records and insurance purposes.</em>
            </p>
            <p style="font-size: 0.9rem; color: #ff6b6b; font-weight: 600;">
                <em>Administrator Access: This billing report was accessed with full administrative privileges.</em>
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <button onclick="window.print()" class="print-btn">
                🖨️ Print Billing Report
            </button>
            <a href="billing.php" class="back-btn">
                ← Back to Billing Management
            </a>
            <a href="patient_history.php?patient_email=<?= urlencode($patient_email) ?>" class="back-btn">
                📋 Patient History
            </a>
        </div>
    </div>
    
    <script>
        // Enhanced admin features
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Admin Billing Report View Loaded');
            
            // Optional: Show admin notification
            if (window.location.search.includes('auto_print=1')) {
                setTimeout(() => window.print(), 1000);
            }
        });
    </script>
</body>
</html>