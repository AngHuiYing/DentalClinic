<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../db.php";

// 只有 admin 可以進來
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// 當表單提交時，新增收費紀錄
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patient_name'])) {
    $patient_name  = $_POST['patient_name'];
    $patient_email = $_POST['patient_email'];
    $patient_phone = $_POST['patient_phone'];
    $service       = $_POST['service'];
    $amount        = $_POST['amount'];
    $payment_method= $_POST['payment_method'];

    $stmt = $conn->prepare("INSERT INTO billing (patient_name, patient_email, patient_phone, service, amount, payment_method, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssds", $patient_name, $patient_email, $patient_phone, $service, $amount, $payment_method);

    if ($stmt->execute()) {
        $success_msg = "Billing record added successfully!";
    } else {
        $error_msg = "Error: " . $conn->error;
    }
    $stmt->close();
}

// 當表單提交時，刪除紀錄
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $stmt = $conn->prepare("DELETE FROM billing WHERE id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Billing record deleted successfully!";
    } else {
        $_SESSION['error_msg'] = "Error deleting record: " . $conn->error;
    }
    $stmt->close();

    // 刷新頁面避免重送表單
    header("Location: billing.php");
    exit();
}

// 分頁設定
$billing_per_page = 15; // 每頁顯示賬單記錄數
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $billing_per_page;

// 先計算總記錄數
$count_result = $conn->query("SELECT COUNT(*) as total FROM billing");
$total_billing = $count_result->fetch_assoc()['total'];

// 計算分頁
$total_pages = ceil($total_billing / $billing_per_page);
$start_billing = ($current_page - 1) * $billing_per_page + 1;
$end_billing = min($current_page * $billing_per_page, $total_billing);

// 讀取所有收費紀錄（分頁）
$result = $conn->query("SELECT * FROM billing ORDER BY created_at DESC LIMIT $billing_per_page OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💰 Billing Management - Green Life Dental Clinic</title>
    
    <!-- Neumorphism Design Framework -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Neumorphism Soft Color Palette */
            --neu-bg: #e8ebf0;
            --neu-surface: #e8ebf0;
            --neu-primary: #5d7bd6;
            --neu-secondary: #7b68ee;
            --neu-accent: #ff6b9d;
            --neu-success: #6bcf7f;
            --neu-warning: #ffd93d;
            --neu-danger: #ff6b6b;
            --neu-text-primary: #2d3748;
            --neu-text-secondary: #4a5568;
            --neu-text-light: #718096;
            
            /* Neumorphism Shadows */
            --neu-shadow-light: #ffffff;
            --neu-shadow-dark: #d1d9e6;
            --neu-inset-light: inset 8px 8px 16px #d1d9e6;
            --neu-inset-dark: inset -8px -8px 16px #ffffff;
            --neu-outset-light: 8px 8px 16px #d1d9e6;
            --neu-outset-dark: -8px -8px 16px #ffffff;
            
            /* Spacing */
            --spacing-xs: 0.5rem;
            --spacing-sm: 1rem;
            --spacing-md: 1.5rem;
            --spacing-lg: 2rem;
            --spacing-xl: 3rem;
            
            /* Border Radius */
            --radius-sm: 12px;
            --radius-md: 20px;
            --radius-lg: 30px;
            --radius-xl: 40px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--neu-bg);
            color: var(--neu-text-primary);
            min-height: 100vh;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        /* Neumorphism Components */
        .neu-card {
            background: var(--neu-surface);
            border-radius: var(--radius-md);
            box-shadow: 
                var(--neu-outset-light),
                var(--neu-outset-dark);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .neu-card:hover {
            box-shadow: 
                12px 12px 24px #d1d9e6,
                -12px -12px 24px #ffffff;
            transform: translateY(-2px);
        }

        .neu-card-pressed {
            box-shadow: 
                var(--neu-inset-light),
                var(--neu-inset-dark);
        }

        .neu-button {
            background: var(--neu-surface);
            border: none;
            border-radius: var(--radius-sm);
            padding: var(--spacing-sm) var(--spacing-lg);
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--neu-text-primary);
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            box-shadow: 
                var(--neu-outset-light),
                var(--neu-outset-dark);
        }

        .neu-button:hover {
            box-shadow: 
                6px 6px 12px #d1d9e6,
                -6px -6px 12px #ffffff;
            transform: translateY(-1px);
        }

        .neu-button:active {
            box-shadow: 
                var(--neu-inset-light),
                var(--neu-inset-dark);
            transform: translateY(0);
        }

        .neu-button-primary {
            background: linear-gradient(135deg, var(--neu-primary), var(--neu-secondary));
            color: white;
            box-shadow: 
                8px 8px 16px rgba(93, 123, 214, 0.3),
                -8px -8px 16px rgba(255, 255, 255, 0.8);
        }

        .neu-button-primary:hover {
            box-shadow: 
                12px 12px 24px rgba(93, 123, 214, 0.4),
                -12px -12px 24px rgba(255, 255, 255, 0.9);
        }

        .neu-button-success {
            background: linear-gradient(135deg, var(--neu-success), #4ade80);
            color: white;
            box-shadow: 
                8px 8px 16px rgba(107, 207, 127, 0.3),
                -8px -8px 16px rgba(255, 255, 255, 0.8);
        }

        .neu-button-danger {
            background: linear-gradient(135deg, var(--neu-danger), #ef4444);
            color: white;
            box-shadow: 
                8px 8px 16px rgba(255, 107, 107, 0.3),
                -8px -8px 16px rgba(255, 255, 255, 0.8);
            padding: var(--spacing-xs) var(--spacing-sm);
            font-size: 0.85rem;
        }

        /* Form Controls */
        .neu-input, .neu-select {
            width: 100%;
            padding: var(--spacing-md);
            background: var(--neu-surface);
            border: none;
            border-radius: var(--radius-sm);
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            color: var(--neu-text-primary);
            box-shadow: 
                var(--neu-inset-light),
                var(--neu-inset-dark);
            transition: all 0.3s ease;
            margin-bottom: var(--spacing-md);
        }

        .neu-input:focus, .neu-select:focus {
            outline: none;
            box-shadow: 
                inset 6px 6px 12px #d1d9e6,
                inset -6px -6px 12px #ffffff,
                0 0 0 3px rgba(93, 123, 214, 0.1);
        }

        .neu-input::placeholder {
            color: var(--neu-text-light);
        }

        /* Page Header */
        .page-header {
            background: var(--neu-surface);
            padding: var(--spacing-xl) 0;
            margin-bottom: var(--spacing-xl);
            box-shadow: 
                0 8px 16px #d1d9e6,
                0 -8px 16px #ffffff;
            position: relative;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 800;
            color: var(--neu-primary);
            text-align: center;
            margin-bottom: var(--spacing-sm);
            text-shadow: 2px 2px 4px rgba(209, 217, 230, 0.5);
        }

        .page-subtitle {
            text-align: center;
            color: var(--neu-text-secondary);
            font-size: 1.1rem;
            font-weight: 400;
        }

        /* Container System */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--spacing-lg);
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 calc(var(--spacing-sm) * -0.5);
        }

        .col, .col-1, .col-2, .col-3, .col-4, .col-5, .col-6,
        .col-7, .col-8, .col-9, .col-10, .col-11, .col-12 {
            padding: 0 calc(var(--spacing-sm) * 0.5);
        }

        .col { flex: 1; }
        .col-1 { flex: 0 0 8.333333%; }
        .col-2 { flex: 0 0 16.666667%; }
        .col-3 { flex: 0 0 25%; }
        .col-4 { flex: 0 0 33.333333%; }
        .col-5 { flex: 0 0 41.666667%; }
        .col-6 { flex: 0 0 50%; }
        .col-7 { flex: 0 0 58.333333%; }
        .col-8 { flex: 0 0 66.666667%; }
        .col-9 { flex: 0 0 75%; }
        .col-10 { flex: 0 0 83.333333%; }
        .col-11 { flex: 0 0 91.666667%; }
        .col-12 { flex: 0 0 100%; }

        /* Statistics Cards */
        .stat-card {
            text-align: center;
            padding: var(--spacing-lg);
        }

        .stat-icon {
            width: 80px;
            height: 80px;
            background: var(--neu-surface);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-md);
            font-size: 2rem;
            box-shadow: 
                var(--neu-outset-light),
                var(--neu-outset-dark);
            transition: all 0.3s ease;
        }

        .stat-icon:hover {
            box-shadow: 
                12px 12px 24px #d1d9e6,
                -12px -12px 24px #ffffff;
            transform: translateY(-3px);
        }

        .stat-icon.primary { color: var(--neu-primary); }
        .stat-icon.success { color: var(--neu-success); }
        .stat-icon.warning { color: var(--neu-warning); }
        .stat-icon.danger { color: var(--neu-danger); }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--neu-text-primary);
            margin-bottom: var(--spacing-xs);
        }

        .stat-label {
            color: var(--neu-text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        /* Table Styling */
        .neu-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: var(--neu-surface);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: 
                var(--neu-outset-light),
                var(--neu-outset-dark);
        }

        .neu-table thead {
            background: linear-gradient(135deg, var(--neu-primary), var(--neu-secondary));
        }

        .neu-table thead th {
            padding: var(--spacing-md);
            color: white;
            font-weight: 600;
            text-align: left;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .neu-table tbody tr {
            transition: all 0.2s ease;
        }

        .neu-table tbody tr:hover {
            background: rgba(93, 123, 214, 0.05);
            transform: scale(1.001);
        }

        .neu-table tbody td {
            padding: var(--spacing-md);
            border-bottom: 1px solid rgba(209, 217, 230, 0.3);
        }

        .neu-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Form Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }

        .section-icon {
            width: 60px;
            height: 60px;
            background: var(--neu-surface);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 
                var(--neu-outset-light),
                var(--neu-outset-dark);
            color: var(--neu-primary);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--neu-text-primary);
        }

        /* Alert Messages */
        .neu-alert {
            padding: var(--spacing-md);
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-md);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            box-shadow: 
                var(--neu-inset-light),
                var(--neu-inset-dark);
        }

        .neu-alert-success {
            background: linear-gradient(135deg, rgba(107, 207, 127, 0.1), rgba(74, 222, 128, 0.05));
            color: #047857;
            border-left: 4px solid var(--neu-success);
        }

        .neu-alert-danger {
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.1), rgba(239, 68, 68, 0.05));
            color: #991b1b;
            border-left: 4px solid var(--neu-danger);
        }

        .neu-alert-warning {
            background: linear-gradient(135deg, rgba(255, 211, 61, 0.1), rgba(243, 156, 18, 0.05));
            color: #b45309;
            border-left: 4px solid var(--neu-warning);
        }

        /* Form Labels */
        .neu-label {
            display: block;
            font-weight: 600;
            color: var(--neu-text-primary);
            margin-bottom: var(--spacing-xs);
            font-size: 0.95rem;
        }

        /* Search Box */
        .search-container {
            background: var(--neu-surface);
            border-radius: var(--radius-sm);
            padding: var(--spacing-sm);
            box-shadow: 
                var(--neu-inset-light),
                var(--neu-inset-dark);
            margin-bottom: var(--spacing-lg);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .search-container i {
            color: var(--neu-text-light);
            font-size: 1.1rem;
        }

        .search-container input {
            background: transparent;
            border: none;
            outline: none;
            flex: 1;
            font-family: 'Poppins', sans-serif;
            color: var(--neu-text-primary);
            font-size: 1rem;
        }

        .search-container input::placeholder {
            color: var(--neu-text-light);
        }

        /* Badge Styling */
        .neu-badge {
            background: var(--neu-surface);
            color: var(--neu-text-primary);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: calc(var(--radius-sm) * 0.7);
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 
                4px 4px 8px #d1d9e6,
                -4px -4px 8px #ffffff;
        }

        .neu-badge-primary {
            background: linear-gradient(135deg, var(--neu-primary), var(--neu-secondary));
            color: white;
            box-shadow: 
                4px 4px 8px rgba(93, 123, 214, 0.3),
                -4px -4px 8px rgba(255, 255, 255, 0.8);
        }

        /* Utility Classes */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mb-0 { margin-bottom: 0; }
        .mb-1 { margin-bottom: var(--spacing-xs); }
        .mb-2 { margin-bottom: var(--spacing-sm); }
        .mb-3 { margin-bottom: var(--spacing-md); }
        .mb-4 { margin-bottom: var(--spacing-lg); }
        .mt-2 { margin-top: var(--spacing-sm); }
        .mt-3 { margin-top: var(--spacing-md); }
        .d-flex { display: flex; }
        .align-items-center { align-items: center; }
        .align-items-end { align-items: end; }
        .justify-content-between { justify-content: space-between; }
        .gap-2 { gap: var(--spacing-sm); }
        .gap-3 { gap: var(--spacing-md); }
        .w-100 { width: 100%; }

        /* Form Grid */
        .form-grid {
            display: grid;
            gap: var(--spacing-md);
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: var(--spacing-xl);
            color: var(--neu-text-light);
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--neu-text-light);
            margin-bottom: var(--spacing-md);
            opacity: 0.5;
        }

        /* Loading Animation */
        .loading-animation {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .loading-animation.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 var(--spacing-sm);
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .neu-card {
                padding: var(--spacing-md);
            }
            
            .neu-table {
                font-size: 0.9rem;
            }
            
            .neu-table thead th,
            .neu-table tbody td {
                padding: var(--spacing-sm);
            }
            
            .stat-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }

        /* Hover Effects */
        .neu-hover:hover {
            box-shadow: 
                12px 12px 24px #d1d9e6,
                -12px -12px 24px #ffffff;
            transform: translateY(-3px);
        }

        /* Special Table Responsive */
        .table-responsive {
            overflow-x: auto;
            border-radius: var(--radius-md);
            box-shadow: 
                var(--neu-outset-light),
                var(--neu-outset-dark);
        }

        @media (max-width: 992px) {
            .table-responsive {
                font-size: 0.85rem;
            }
            
            .table-responsive th,
            .table-responsive td {
                padding: var(--spacing-xs) var(--spacing-sm);
                white-space: nowrap;
            }
        }

        /* Animation Keyframes */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slide-up {
            animation: slideInUp 0.6s ease-out;
        }

        /* Enhanced Interactive Effects */
        .interactive-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .interactive-card:active {
            transform: scale(0.98);
            box-shadow: 
                var(--neu-inset-light),
                var(--neu-inset-dark);
        }
        
        /* Pagination Styles */
        .pagination-container {
            background: var(--neu-bg);
            border-radius: 25px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: var(--neu-shadow-light), var(--neu-shadow-dark);
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            background: var(--neu-bg);
            box-shadow: var(--neu-shadow-light), var(--neu-shadow-dark);
            color: var(--neu-primary);
            padding: 0.5rem 0.75rem;
            border-radius: 15px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            min-width: 45px;
            justify-content: center;
            border: none;
        }
        
        .page-btn:hover {
            box-shadow: var(--neu-shadow-hover-light), var(--neu-shadow-hover-dark);
            color: var(--neu-primary);
            text-decoration: none;
            transform: translateY(-2px);
        }
        
        .page-btn.active {
            background: linear-gradient(135deg, var(--neu-primary), var(--neu-secondary));
            color: #1a202c;
            font-weight: 700;
            box-shadow: var(--neu-inset-light), var(--neu-inset-dark);
            text-shadow: none;
        }
        
        .page-btn.disabled {
            background: var(--neu-bg);
            color: var(--neu-text-muted);
            cursor: not-allowed;
            pointer-events: none;
            opacity: 0.6;
        }
        
        .pagination-info {
            text-align: center;
            color: var(--neu-text-secondary);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
    </style>
    <script>
    $(document).ready(function(){
        $('#patient_select').on('change', function(){
            let selected = $(this).find('option:selected');
            let name = selected.data('name') || '';
            let email = selected.val() || '';
            let phone = selected.data('phone') || '';
            $('#patient_name').val(name);
            $('#patient_email_display').val(email);
            $('#patient_phone').val(phone);
            $('#service').val('');
            $('#amount').val('');
            if(email) {
                // AJAX 取得該病患最新服務與金額
                $.ajax({
                    url: 'get_patient_services.php',
                    type: 'GET',
                    data: {patient_email: email},
                    success: function(data){
                        try {
                            let res = JSON.parse(data);
                            if(res.success) {
                                $('#service').val(res.services);
                                $('#amount').val(res.amount);
                            } else {
                                $('#service').val('');
                                $('#amount').val('');
                            }
                        } catch(e) {
                            $('#service').val('');
                            $('#amount').val('');
                        }
                    }
                });
            }
        });
        
        // 表單送出時，強制 name、email、phone、service、amount 都要有值
        $('#billingForm').on('submit', function(e){
            if(!$('#patient_select').val() || !$('#patient_name').val() || !$('#patient_email_display').val() || !$('#patient_phone').val() || !$('#service').val() || !$('#amount').val()){
                alert('Please select a patient with valid service record.');
                e.preventDefault();
            }
            // 將 readonly 的 email 欄位值複製到真正的 email 欄位
            if($('#patient_email_display').length && $("input[name='patient_email']").length === 0) {
                $('<input>').attr({type:'hidden',name:'patient_email',value:$('#patient_email_display').val()}).appendTo('#billingForm');
            }
        });
        
        // 預設清空
        $('#patient_select').trigger('change');

        // Page load animations
        setTimeout(() => {
            document.querySelectorAll('.loading-animation').forEach((element, index) => {
                setTimeout(() => {
                    element.classList.add('visible');
                }, index * 150);
            });
        }, 300);

        // Search functionality
        $('#searchInput').on('keyup', function() {
            let filter = $(this).val().toLowerCase();
            $('#billingTable tbody tr').each(function() {
                let text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(filter) > -1);
            });
        });

        // Form submission loading state
        $('#billingForm').on('submit', function(e) {
            let submitBtn = $(this).find('button[type="submit"]');
            if (submitBtn.length) {
                submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
                submitBtn.prop('disabled', true);
            }
        });
    });
    </script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex align-items-center justify-content-center">
                <div class="section-icon" style="margin-right: 2rem;">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="text-center">
                    <h1 class="page-title">Billing Management</h1>
                    <p class="page-subtitle">Manage patient billing and payment records with neumorphic elegance</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Success/Error Messages -->
        <?php if (!empty($success_msg)): ?>
            <div class="neu-alert neu-alert-success loading-animation">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
            <div class="neu-alert neu-alert-danger loading-animation">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['success_msg'])): ?>
            <div class="neu-alert neu-alert-success loading-animation">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($_SESSION['error_msg'])): ?>
            <div class="neu-alert neu-alert-danger loading-animation">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Section -->
        <div class="row mb-4">
            <div class="col-3">
                <div class="neu-card stat-card loading-animation">
                    <div class="stat-icon primary">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="stat-number"><?php echo $result->num_rows; ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
            </div>
            <div class="col-3">
                <div class="neu-card stat-card loading-animation">
                    <div class="stat-icon success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <?php 
                    $totalAmount = 0;
                    $result->data_seek(0);
                    while($row = $result->fetch_assoc()) { $totalAmount += $row['amount']; }
                    $result->data_seek(0);
                    ?>
                    <div class="stat-number">RM <?php echo number_format($totalAmount, 0); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
            <div class="col-3">
                <div class="neu-card stat-card loading-animation">
                    <div class="stat-icon warning">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <?php 
                    $todayCount = 0;
                    $result->data_seek(0);
                    while($row = $result->fetch_assoc()) {
                        if(date('Y-m-d', strtotime($row['created_at'])) === date('Y-m-d')) {
                            $todayCount++;
                        }
                    }
                    $result->data_seek(0);
                    ?>
                    <div class="stat-number"><?php echo $todayCount; ?></div>
                    <div class="stat-label">Today's Records</div>
                </div>
            </div>
            <div class="col-3">
                <div class="neu-card stat-card loading-animation">
                    <div class="stat-icon danger">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <?php 
                    $avgAmount = $result->num_rows > 0 ? $totalAmount / $result->num_rows : 0;
                    ?>
                    <div class="stat-number">RM <?php echo number_format($avgAmount, 0); ?></div>
                    <div class="stat-label">Average Amount</div>
                </div>
            </div>
        </div>

        <!-- Add Billing Record Form -->
        <div class="neu-card loading-animation">
            <?php
            // 檢查是否有可用的患者 - 提前執行查詢
            $patients_query = "
                SELECT DISTINCT 
                    mr.patient_email, 
                    a.patient_name, 
                    a.patient_phone,
                    MAX(mr.created_at) as latest_record_date
                FROM medical_records mr
                LEFT JOIN appointments a ON mr.patient_email = a.patient_email AND a.status = 'confirmed'
                WHERE mr.patient_email IS NOT NULL 
                AND a.patient_email IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1 FROM billing b 
                    WHERE b.patient_email = mr.patient_email 
                    AND b.created_at > mr.created_at
                )
                GROUP BY mr.patient_email, a.patient_name, a.patient_phone
                ORDER BY latest_record_date DESC, a.patient_name ASC
            ";
            $patients_result = $conn->query($patients_query);
            $has_patients = $patients_result->num_rows > 0;
            ?>
            
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div>
                    <h2 class="section-title">Add New Billing Record</h2>
                </div>
            </div>
            
            <?php if (!$has_patients): ?>
                <div class="neu-alert neu-alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>No Patients Available:</strong> All patients with medical records have already been billed. 
                    New patients will appear here after doctors add medical records for them.
                </div>
            <?php endif; ?>
            
            <form method="POST" id="billingForm" <?= !$has_patients ? 'style="opacity: 0.6; pointer-events: none;"' : '' ?>>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="neu-label">
                            <i class="fas fa-user me-2"></i>Patient
                        </label>
                        <select name="patient_email" id="patient_select" class="neu-select" required>
                            <option value="">Select Patient</option>
                            <?php
                            if (!$has_patients): ?>
                                <option value="" disabled>No patients with unbilled medical records found</option>
                            <?php else: ?>
                                <?php while($p = $patients_result->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($p['patient_email']) ?>"
                                        data-name="<?= htmlspecialchars($p['patient_name']) ?>"
                                        data-phone="<?= htmlspecialchars($p['patient_phone']) ?>"
                                    ><?= htmlspecialchars($p['patient_name']) ?> (<?= htmlspecialchars($p['patient_email']) ?>) - <?= htmlspecialchars($p['patient_phone']) ?> 
                                    <small style="color: #666;">[Latest Record: <?= date('M j, Y', strtotime($p['latest_record_date'])) ?>]</small></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted mt-2 d-block">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Note:</strong> Only patients with new medical records (not yet billed) are shown. 
                            After adding a billing record, the patient will be removed from this list until they have a new medical record.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label class="neu-label">
                            <i class="fas fa-id-badge me-2"></i>Patient Name
                        </label>
                        <input type="text" name="patient_name" id="patient_name" class="neu-input" readonly required>
                    </div>
                    
                    <div class="form-group">
                        <label class="neu-label">
                            <i class="fas fa-envelope me-2"></i>Patient Email
                        </label>
                        <input type="email" name="patient_email_display" id="patient_email_display" class="neu-input" readonly required>
                    </div>
                    
                    <div class="form-group">
                        <label class="neu-label">
                            <i class="fas fa-phone me-2"></i>Patient Phone
                        </label>
                        <input type="tel" name="patient_phone" id="patient_phone" class="neu-input" readonly required>
                    </div>
                    
                    <div class="form-group">
                        <label class="neu-label">
                            <i class="fas fa-tools me-2"></i>Service
                        </label>
                        <input type="text" name="service" id="service" class="neu-input" readonly required>
                    </div>
                    
                    <div class="form-group">
                        <label class="neu-label">
                            <i class="fas fa-dollar-sign me-2"></i>Amount (RM)
                        </label>
                        <input type="number" step="0.01" name="amount" id="amount" class="neu-input" readonly required>
                    </div>
                    
                    <div class="form-group">
                        <label class="neu-label">
                            <i class="fas fa-credit-card me-2"></i>Payment Method
                        </label>
                        <select name="payment_method" class="neu-select">
                            <option value="Cash">Cash</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Online Payment">Online Payment</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Insurance">Insurance</option>
                        </select>
                    </div>
                    
                    <div class="form-group d-flex align-items-end">
                        <button type="submit" class="neu-button neu-button-primary w-100">
                            <i class="fas fa-plus-circle"></i>
                            Add Billing Record
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Billing Records Section -->
        <div class="neu-card loading-animation">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-table"></i>
                </div>
                <div style="flex: 1;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="section-title mb-0">Billing Records</h2>
                        <div class="d-flex align-items-center gap-3">
                            <span class="neu-badge neu-badge-primary">
                                <?php echo $result->num_rows; ?> of <?= number_format($total_billing) ?> Records
                            </span>
                            <?php if ($total_pages > 1): ?>
                            <small style="color: var(--neu-text-secondary); font-weight: 500;">
                                Page <?= $current_page ?> of <?= $total_pages ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <!-- Search Box -->
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search billing records by Patient Name or Email...">
                </div>
                
                <div class="text-center mb-3">
                    <small style="color: var(--neu-text-light);">
                        <i class="fas fa-info-circle me-1"></i>
                        Only today's records can be deleted
                    </small>
                </div>
                
                <div class="table-responsive">
                    <table id="billingTable" class="neu-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                <th><i class="fas fa-user me-1"></i>Patient Name</th>
                                <th><i class="fas fa-envelope me-1"></i>Email</th>
                                <th><i class="fas fa-phone me-1"></i>Phone</th>
                                <th><i class="fas fa-tools me-1"></i>Service</th>
                                <th><i class="fas fa-dollar-sign me-1"></i>Amount (RM)</th>
                                <th><i class="fas fa-credit-card me-1"></i>Payment</th>
                                <th><i class="fas fa-calendar me-1"></i>Date</th>
                                <th><i class="fas fa-cog me-1"></i>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= $row['id'] ?></strong></td>
                                <td><?= htmlspecialchars($row['patient_name']) ?></td>
                                <td><?= htmlspecialchars($row['patient_email']) ?></td>
                                <td><?= htmlspecialchars($row['patient_phone']) ?></td>
                                <td><?= htmlspecialchars($row['service']) ?></td>
                                <td><strong>RM <?= number_format($row['amount'], 2) ?></strong></td>
                                <td>
                                    <span class="neu-badge neu-badge-primary">
                                        <?= htmlspecialchars($row['payment_method']) ?>
                                    </span>
                                </td>
                                <td>
                                    <small style="color: var(--neu-text-secondary);">
                                        <?= date('M j, Y', strtotime($row['created_at'])) ?><br>
                                        <?= date('g:i A', strtotime($row['created_at'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <a href="view_billing_report.php?billing_id=<?= intval($row['id']); ?>&patient_email=<?= urlencode($row['patient_email']); ?>" 
                                           class="neu-button neu-button-success" 
                                           style="padding: 0.5rem 1rem; font-size: 0.85rem;" 
                                           target="_blank"
                                           title="View Billing Report">
                                            <i class="fas fa-file-invoice"></i>View Report
                                        </a>
                                        <?php 
                                        $isToday = (date('Y-m-d', strtotime($row['created_at'])) === date('Y-m-d'));
                                        if ($isToday): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this billing record?');">
                                            <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="neu-button neu-button-danger" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span style="color: var(--neu-text-light);" title="Cannot delete past records">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?= $start_billing ?>-<?= $end_billing ?> of <?= number_format($total_billing) ?> billing records
                    </div>
                    
                    <div class="pagination-nav">
                        <!-- Previous Page -->
                        <?php if ($current_page > 1): ?>
                            <?php 
                            $prev_params = $_GET;
                            $prev_params['page'] = $current_page - 1;
                            ?>
                            <a href="?<?= http_build_query($prev_params) ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                <i class="fas fa-chevron-left"></i> Previous
                            </span>
                        <?php endif; ?>
                        
                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        // Show first page if not in range
                        if ($start_page > 1) {
                            $first_params = $_GET;
                            $first_params['page'] = 1;
                            echo '<a href="?' . http_build_query($first_params) . '" class="page-btn">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="page-btn disabled">...</span>';
                            }
                        }
                        
                        // Show page numbers in range
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $page_params = $_GET;
                            $page_params['page'] = $i;
                            if ($i == $current_page) {
                                echo '<span class="page-btn active">' . $i . '</span>';
                            } else {
                                echo '<a href="?' . http_build_query($page_params) . '" class="page-btn">' . $i . '</a>';
                            }
                        }
                        
                        // Show last page if not in range
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="page-btn disabled">...</span>';
                            }
                            $last_params = $_GET;
                            $last_params['page'] = $total_pages;
                            echo '<a href="?' . http_build_query($last_params) . '" class="page-btn">' . $total_pages . '</a>';
                        }
                        ?>
                        
                        <!-- Next Page -->
                        <?php if ($current_page < $total_pages): ?>
                            <?php 
                            $next_params = $_GET;
                            $next_params['page'] = $current_page + 1;
                            ?>
                            <a href="?<?= http_build_query($next_params) ?>" class="page-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                Next <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox empty-icon"></i>
                    <h5>No billing records found</h5>
                    <p>Start by adding your first billing record above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>