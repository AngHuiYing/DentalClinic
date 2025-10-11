<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../doctor/login.php");
    exit;
}

// 获取医生 ID
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    die("Doctor record not found!");
}

$doctor_id = $doctor['id'];

// 获取报告参数
$record_id = isset($_GET['record_id']) ? (int)$_GET['record_id'] : 0;
$patient_email = isset($_GET['patient_email']) ? trim($_GET['patient_email']) : '';

if (!$record_id || !$patient_email) {
    die("Invalid request parameters.");
}

// 验证医生权限
$auth_stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE doctor_id = ? AND patient_email = ? AND (status = 'confirmed' OR status = 'completed')
");
$auth_stmt->bind_param("is", $doctor_id, $patient_email);
$auth_stmt->execute();
$auth_result = $auth_stmt->get_result()->fetch_assoc();

if ($auth_result['count'] == 0) {
    die("You do not have permission to view this patient's records.");
}

// 获取病历记录详情
$record_stmt = $conn->prepare("
    SELECT mr.*, d.name as doctor_name
    FROM medical_records mr 
    JOIN doctors d ON mr.doctor_id = d.id 
    WHERE mr.id = ? AND mr.patient_email = ? AND mr.report_generated = TRUE
");
$record_stmt->bind_param("is", $record_id, $patient_email);
$record_stmt->execute();
$record_data = $record_stmt->get_result()->fetch_assoc();

if (!$record_data) {
    die("Medical record not found or report not generated.");
}

// 获取患者详细信息从 users 表
$user_stmt = $conn->prepare("SELECT name, email, phone, gender, date_of_birth FROM users WHERE email = ?");
$user_stmt->bind_param("s", $patient_email);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();

// 获取服务列表和费用
$services_stmt = $conn->prepare("
    SELECT s.name, s.price 
    FROM medical_record_services mrs 
    JOIN services s ON mrs.service_id = s.id 
    WHERE mrs.medical_record_id = ?
");
$services_stmt->bind_param("i", $record_id);
$services_stmt->execute();
$services_result = $services_stmt->get_result();

$services_list = [];
$total_billing = 0;
while ($service = $services_result->fetch_assoc()) {
    $services_list[] = $service['name'] . ' (RM' . number_format($service['price'], 2) . ')';
    $total_billing += $service['price'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dental Medical Report</title>
    <style>
        body {
            font-family: 'Inter', Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }
        .report-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #2c5aa0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2c5aa0;
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        .header .clinic-info {
            color: #666;
            margin-top: 10px;
        }
        .report-meta {
            text-align: right;
            margin-bottom: 30px;
            font-size: 0.9rem;
            color: #666;
        }
        h2 {
            color: #2c5aa0;
            border-bottom: 2px solid #84c69b;
            padding-bottom: 10px;
            margin-top: 30px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #2c5aa0;
            width: 200px;
        }
        td {
            background-color: white;
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
        @media print {
            .print-btn, .back-btn { display: none; }
            body { background: white; padding: 0; }
            .report-container { 
                box-shadow: none; 
                margin: 0; 
                border-radius: 0;
                padding: 15px;
                font-size: 0.85rem;
                max-width: 100%;
            }
            /* 隐藏所有非报告内容 */
            .navbar, nav, header, .container:not(.report-container) { display: none !important; }
            
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
                margin-bottom: 15px !important;
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
            .report-meta {
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
            html, body { margin: 0; padding: 0; }
        }
        
        /* 移动端响应式设计 */
        @media screen and (max-width: 768px) {
            body {
                padding: 10px;
                font-size: 14px;
                line-height: 1.4;
            }
            
            .report-container {
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
            
            .report-meta {
                font-size: 0.8rem;
                margin-bottom: 20px;
                text-align: left;
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
                font-size: 0.8rem;
                padding: 8px 6px;
                word-wrap: break-word;
                word-break: break-word;
                hyphens: auto;
                border-right: 1px solid #ddd;
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
            .report-container * {
                max-width: 100%;
                box-sizing: border-box;
            }
            
            /* 长文本处理 */
            .report-container td {
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
            .report-container {
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
    <div class="report-container">
        <div class="header">
            <h1>🦷 Dental Medical Report</h1>
            <div class="clinic-info">
                <strong>Green Life Dental Clinic</strong><br>
                Professional Dental Care Services
            </div>
        </div>
        
        <div class="report-meta">
            <strong>Report Generated:</strong> <?= date('F j, Y \a\t g:i A') ?><br>
            <strong>Medical Record ID:</strong> #<?= str_pad($record_data['id'], 6, '0', STR_PAD_LEFT) ?>
        </div>

        <h2>👤 Patient Information</h2>
        <table>
            <tr><th>Full Name</th><td><?= htmlspecialchars($user_data['name'] ?? 'N/A') ?></td></tr>
            <tr><th>Date of Birth</th><td><?= $user_data['date_of_birth'] ? date('F j, Y', strtotime($user_data['date_of_birth'])) : 'Not specified' ?></td></tr>
            <tr><th>Gender</th><td><?= htmlspecialchars(ucfirst($user_data['gender'] ?? 'Not specified')) ?></td></tr>
            <tr><th>Email</th><td><?= htmlspecialchars($user_data['email']) ?></td></tr>
            <tr><th>Phone</th><td><?= htmlspecialchars($user_data['phone'] ?? 'N/A') ?></td></tr>
            <tr><th>Medical Record No.</th><td>MRN-<?= str_pad($record_data['id'], 6, '0', STR_PAD_LEFT) ?></td></tr>
        </table>

        <h2>🏥 Visit Information</h2>
        <table>
            <tr><th>Visit Date</th><td><?= date('F j, Y', strtotime($record_data['visit_date'])) ?></td></tr>
            <tr><th>Attending Doctor</th><td>Dr. <?= htmlspecialchars($record_data['doctor_name']) ?></td></tr>
            <tr><th>Chief Complaint</th><td><?= htmlspecialchars($record_data['chief_complaint']) ?></td></tr>
            <tr><th>Clinical Diagnosis</th><td><?= htmlspecialchars($record_data['diagnosis']) ?></td></tr>
            <tr><th>Treatment Plan</th><td><?= htmlspecialchars($record_data['treatment_plan'] ?: 'None specified') ?></td></tr>
            <tr><th>Prescription</th><td><?= htmlspecialchars($record_data['prescription'] ?: 'None prescribed') ?></td></tr>
            <tr><th>Progress Notes</th><td><?= htmlspecialchars($record_data['progress_notes'] ?: 'No additional notes') ?></td></tr>
            <tr><th>Services Performed</th><td><?= !empty($services_list) ? implode('<br>', $services_list) : 'No services recorded' ?></td></tr>
            <tr><th>Total Billing Amount</th><td><strong>RM <?= number_format($total_billing, 2) ?></strong></td></tr>
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
            <p style="font-size: 0.9rem; color: #666;">
                <em>This report is generated electronically and contains confidential medical information. 
                Please handle with appropriate care and maintain patient privacy.</em>
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <button onclick="window.print()" class="print-btn">
                🖨️ Print Report
            </button>
            <a href="patient_records.php" class="back-btn">
                ← Back to Patient Records
            </a>
        </div>
    </div>
    
    <script>
        // 自动显示打印对話框（可選）
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>