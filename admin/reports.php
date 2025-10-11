<?php
// 開啟錯誤報告以便調試
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get filter parameters
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : 0; // 0 = all months
$selected_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$selected_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$period_type = isset($_GET['period_type']) ? $_GET['period_type'] : 'yearly';

// Auto-set date ranges based on period type
if ($period_type == 'weekly' && empty($selected_start_date) && empty($selected_end_date)) {
    // Set to current week (Monday to Sunday)
    $selected_start_date = date('Y-m-d', strtotime('monday this week'));
    $selected_end_date = date('Y-m-d', strtotime('sunday this week'));
} elseif ($period_type == 'monthly' && empty($selected_start_date) && empty($selected_end_date)) {
    // Set to current month
    $selected_start_date = date('Y-m-01');
    $selected_end_date = date('Y-m-t');
} elseif ($period_type == 'custom') {
    // Keep user-selected dates as they are
    // Don't auto-set anything for custom range
}

// Build date filter conditions
$date_conditions = [];
$date_filter = "";

// Debug: Show what dates we're working with
echo "<!-- Debug: Selected start date: " . $selected_start_date . " -->";
echo "<!-- Debug: Selected end date: " . $selected_end_date . " -->";
echo "<!-- Debug: Selected year: " . $selected_year . " -->";
echo "<!-- Debug: Selected month: " . $selected_month . " -->";
echo "<!-- Debug: Period type: " . $period_type . " -->";

if (!empty($selected_start_date) && !empty($selected_end_date)) {
    // Use date range filtering
    $date_conditions[] = "created_at >= '" . $conn->real_escape_string($selected_start_date) . " 00:00:00'";
    $date_conditions[] = "created_at <= '" . $conn->real_escape_string($selected_end_date) . " 23:59:59'";
    $date_filter = implode(' AND ', $date_conditions);
} elseif (!empty($selected_start_date)) {
    // Start date only
    $date_filter = "created_at >= '" . $conn->real_escape_string($selected_start_date) . " 00:00:00'";
} elseif (!empty($selected_end_date)) {
    // End date only
    $date_filter = "created_at <= '" . $conn->real_escape_string($selected_end_date) . " 23:59:59'";
} else {
    // Use year/month filters as fallback
    $year_condition = "YEAR(created_at) = $selected_year";
    $month_condition = $selected_month > 0 ? " AND MONTH(created_at) = $selected_month" : "";
    $date_filter = $year_condition . $month_condition;
}

// Build period description for reports
$period_description = '';
if (!empty($selected_start_date) && !empty($selected_end_date)) {
    $period_description = date('M j, Y', strtotime($selected_start_date)) . ' - ' . date('M j, Y', strtotime($selected_end_date));
    if ($period_type == 'weekly') {
        $period_description .= ' (Weekly View)';
    } elseif ($period_type == 'monthly') {
        $period_description .= ' (Monthly View)';
    } elseif ($period_type == 'custom') {
        $period_description .= ' (Custom Range)';
    }
} elseif (!empty($selected_start_date)) {
    $period_description = 'From ' . date('M j, Y', strtotime($selected_start_date));
} elseif (!empty($selected_end_date)) {
    $period_description = 'Until ' . date('M j, Y', strtotime($selected_end_date));
} else {
    $period_description = $selected_month > 0 ? date('F', mktime(0, 0, 0, $selected_month, 1)) . ' ' . $selected_year : 'Full Year ' . $selected_year;
    if ($period_type == 'yearly') {
        $period_description .= ' (Yearly View)';
    }
}

// 基本統計數據
$total_patients = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'patient'")->fetch_assoc()['count'];
$total_doctors = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor'")->fetch_assoc()['count'];

// Debug: Show the date filter being used
echo "<!-- Debug: Date filter: " . htmlspecialchars($date_filter) . " -->";

// Fix appointments query - use appointment_date instead of created_at
$appointments_date_filter = str_replace('created_at', 'appointment_date', $date_filter);
$appointments_query = "SELECT COUNT(*) as count FROM appointments WHERE " . $appointments_date_filter;
echo "<!-- Debug: Appointments query: " . htmlspecialchars($appointments_query) . " -->";
$total_appointments = $conn->query($appointments_query)->fetch_assoc()['count'];

// Fix revenue query - use proper date filter
$revenue_query = "SELECT SUM(amount) as total FROM billing WHERE " . $date_filter;
echo "<!-- Debug: Revenue query: " . htmlspecialchars($revenue_query) . " -->";
$total_revenue = $conn->query($revenue_query)->fetch_assoc()['total'] ?? 0;

// Debug: Show actual revenue result
echo "<!-- Debug: Total revenue result: $total_revenue -->";

// Additional debugging for specific month
if ($selected_month > 0) {
    $debug_billing_query = "SELECT * FROM billing WHERE YEAR(created_at) = $selected_year AND MONTH(created_at) = $selected_month LIMIT 5";
    $debug_billing_result = $conn->query($debug_billing_query);
    echo "<!-- Debug: Sample billing records for month $selected_month: ";
    while($debug_row = $debug_billing_result->fetch_assoc()) {
        echo "ID: {$debug_row['id']}, Amount: {$debug_row['amount']}, Date: {$debug_row['created_at']} | ";
    }
    echo " -->";
}

// Previous period comparison (simplified for now)
$prev_year = $selected_year - 1;
if (!empty($selected_start_date) && !empty($selected_end_date)) {
    // For date ranges, compare with same period last year
    $prev_start = date('Y-m-d', strtotime($selected_start_date . ' -1 year'));
    $prev_end = date('Y-m-d', strtotime($selected_end_date . ' -1 year'));
    $prev_filter = "created_at >= '$prev_start 00:00:00' AND created_at <= '$prev_end 23:59:59'";
} else {
    $prev_filter = "YEAR(created_at) = $prev_year" . ($selected_month > 0 ? " AND MONTH(created_at) = $selected_month" : "");
}
$prev_total_revenue = $conn->query("SELECT SUM(amount) as total FROM billing WHERE $prev_filter")->fetch_assoc()['total'] ?? 0;
$revenue_growth = $prev_total_revenue > 0 ? (($total_revenue - $prev_total_revenue) / $prev_total_revenue) * 100 : 0;

// Monthly revenue trend
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
echo "<!-- Debug: Monthly revenue query: " . htmlspecialchars($monthly_revenue_query) . " -->";
$monthly_revenue = $conn->query($monthly_revenue_query);

// Dental services analysis - Fixed query with proper date filtering
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
    WHERE 1=1";

// Apply unified date filters to all queries
if (!empty($selected_start_date) && !empty($selected_end_date)) {
    // For medical services: use both visit_date and created_at for better coverage
    $service_performance_query .= " AND (
        (mr.visit_date >= '" . $conn->real_escape_string($selected_start_date) . "' AND mr.visit_date <= '" . $conn->real_escape_string($selected_end_date) . "')
        OR (mr.created_at >= '" . $conn->real_escape_string($selected_start_date) . " 00:00:00' AND mr.created_at <= '" . $conn->real_escape_string($selected_end_date) . " 23:59:59')
    )";
} elseif (!empty($selected_start_date)) {
    $service_performance_query .= " AND (
        mr.visit_date >= '" . $conn->real_escape_string($selected_start_date) . "'
        OR mr.created_at >= '" . $conn->real_escape_string($selected_start_date) . " 00:00:00'
    )";
} elseif (!empty($selected_end_date)) {
    $service_performance_query .= " AND (
        mr.visit_date <= '" . $conn->real_escape_string($selected_end_date) . "'
        OR mr.created_at <= '" . $conn->real_escape_string($selected_end_date) . " 23:59:59'
    )";
} else {
    // Use unified year/month filters - prioritize visit_date but fallback to created_at
    $service_performance_query .= " AND (
        (YEAR(mr.visit_date) = $selected_year" . ($selected_month > 0 ? " AND MONTH(mr.visit_date) = $selected_month" : "") . ")
        OR (mr.visit_date IS NULL AND YEAR(mr.created_at) = $selected_year" . ($selected_month > 0 ? " AND MONTH(mr.created_at) = $selected_month" : "") . ")
    )";
}

$service_performance_query .= " GROUP BY s.id, s.name, s.price ORDER BY total_revenue DESC LIMIT 10";
echo "<!-- Debug: Service performance query: " . htmlspecialchars($service_performance_query) . " -->";
$service_performance = $conn->query($service_performance_query);
echo "<!-- Debug: Service performance rows: " . $service_performance->num_rows . " -->";

// Store dental services data for both HTML display and JavaScript
$service_performance_data = [];
if ($service_performance->num_rows > 0) {
    while($row = $service_performance->fetch_assoc()) {
        $service_performance_data[] = $row;
    }
}

// Doctor revenue performance - Based on actual medical services provided
$doctor_revenue_query = "
    SELECT 
        d.name as doctor_name,
        d.id as doctor_id,
        COALESCE(filtered_appointments.appointment_count, 0) as appointments,
        COALESCE(filtered_treatments.treatment_count, 0) as treatments,
        COALESCE(filtered_services_revenue.total_revenue, 0) as revenue
    FROM doctors d
    LEFT JOIN (
        SELECT 
            a.doctor_id,
            COUNT(*) as appointment_count
        FROM appointments a 
        INNER JOIN doctors doc ON a.doctor_id = doc.id
        WHERE a.doctor_id IS NOT NULL AND a.doctor_id != '' AND ";

// Add appointment date filters
if (!empty($selected_start_date) && !empty($selected_end_date)) {
    $doctor_revenue_query .= "a.appointment_date >= '" . $conn->real_escape_string($selected_start_date) . "' 
                             AND a.appointment_date <= '" . $conn->real_escape_string($selected_end_date) . "'";
} elseif (!empty($selected_start_date)) {
    $doctor_revenue_query .= "a.appointment_date >= '" . $conn->real_escape_string($selected_start_date) . "'";
} elseif (!empty($selected_end_date)) {
    $doctor_revenue_query .= "a.appointment_date <= '" . $conn->real_escape_string($selected_end_date) . "'";
} else {
    $doctor_revenue_query .= "YEAR(a.appointment_date) = $selected_year" . ($selected_month > 0 ? " AND MONTH(a.appointment_date) = $selected_month" : "");
}

$doctor_revenue_query .= "
        GROUP BY a.doctor_id
    ) filtered_appointments ON d.id = filtered_appointments.doctor_id
    LEFT JOIN (
        SELECT 
            mr.doctor_id,
            COUNT(*) as treatment_count
        FROM medical_records mr 
        INNER JOIN doctors doc ON mr.doctor_id = doc.id
        WHERE mr.doctor_id IS NOT NULL AND mr.doctor_id != '' AND ";

// Add medical records date filters  
if (!empty($selected_start_date) && !empty($selected_end_date)) {
    $doctor_revenue_query .= "(mr.visit_date >= '" . $conn->real_escape_string($selected_start_date) . "' 
                             AND mr.visit_date <= '" . $conn->real_escape_string($selected_end_date) . "')
                             OR (mr.created_at >= '" . $conn->real_escape_string($selected_start_date) . " 00:00:00' 
                             AND mr.created_at <= '" . $conn->real_escape_string($selected_end_date) . " 23:59:59')";
} elseif (!empty($selected_start_date)) {
    $doctor_revenue_query .= "mr.visit_date >= '" . $conn->real_escape_string($selected_start_date) . "'
                             OR mr.created_at >= '" . $conn->real_escape_string($selected_start_date) . " 00:00:00'";
} elseif (!empty($selected_end_date)) {
    $doctor_revenue_query .= "mr.visit_date <= '" . $conn->real_escape_string($selected_end_date) . "'
                             OR mr.created_at <= '" . $conn->real_escape_string($selected_end_date) . " 23:59:59'";
} else {
    $doctor_revenue_query .= "(YEAR(mr.visit_date) = $selected_year" . ($selected_month > 0 ? " AND MONTH(mr.visit_date) = $selected_month" : "") . ")
                             OR (YEAR(mr.created_at) = $selected_year" . ($selected_month > 0 ? " AND MONTH(mr.created_at) = $selected_month" : "") . ")";
}

$doctor_revenue_query .= "
        GROUP BY mr.doctor_id
    ) filtered_treatments ON d.id = filtered_treatments.doctor_id
    LEFT JOIN (
        -- Calculate revenue based on actual medical services provided by each doctor
        SELECT 
            mr.doctor_id,
            SUM(s.price) as total_revenue
        FROM medical_records mr
        INNER JOIN doctors doc ON mr.doctor_id = doc.id
        INNER JOIN medical_record_services mrs ON mr.id = mrs.medical_record_id
        INNER JOIN services s ON mrs.service_id = s.id
        WHERE mr.doctor_id IS NOT NULL AND mr.doctor_id != '' AND ";

// Add date filters for service revenue calculation
if (!empty($selected_start_date) && !empty($selected_end_date)) {
    $doctor_revenue_query .= "(mr.visit_date >= '" . $conn->real_escape_string($selected_start_date) . "' 
                             AND mr.visit_date <= '" . $conn->real_escape_string($selected_end_date) . "')
                             OR (mr.created_at >= '" . $conn->real_escape_string($selected_start_date) . " 00:00:00' 
                             AND mr.created_at <= '" . $conn->real_escape_string($selected_end_date) . " 23:59:59')";
} elseif (!empty($selected_start_date)) {
    $doctor_revenue_query .= "mr.visit_date >= '" . $conn->real_escape_string($selected_start_date) . "'
                             OR mr.created_at >= '" . $conn->real_escape_string($selected_start_date) . " 00:00:00'";
} elseif (!empty($selected_end_date)) {
    $doctor_revenue_query .= "mr.visit_date <= '" . $conn->real_escape_string($selected_end_date) . "'
                             OR mr.created_at <= '" . $conn->real_escape_string($selected_end_date) . " 23:59:59'";
} else {
    $doctor_revenue_query .= "(YEAR(mr.visit_date) = $selected_year" . ($selected_month > 0 ? " AND MONTH(mr.visit_date) = $selected_month" : "") . ")
                             OR (YEAR(mr.created_at) = $selected_year" . ($selected_month > 0 ? " AND MONTH(mr.created_at) = $selected_month" : "") . ")";
}

$doctor_revenue_query .= "
        GROUP BY mr.doctor_id
    ) filtered_services_revenue ON d.id = filtered_services_revenue.doctor_id
    GROUP BY d.id, d.name 
    ORDER BY revenue DESC, appointments DESC, treatments DESC";
echo "<!-- Debug: Doctor revenue query: " . htmlspecialchars($doctor_revenue_query) . " -->";
$doctor_revenue = $conn->query($doctor_revenue_query);

// Payment method analysis
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
echo "<!-- Debug: Payment analysis query: " . htmlspecialchars($payment_analysis_query) . " -->";
$payment_analysis = $conn->query($payment_analysis_query);
echo "<!-- Debug: Payment analysis rows: " . $payment_analysis->num_rows . " -->";

// Add debugging for month filtering
if ($selected_month > 0) {
    $debug_query = "SELECT COUNT(*) as count FROM billing WHERE YEAR(created_at) = $selected_year AND MONTH(created_at) = $selected_month";
    $debug_result = $conn->query($debug_query);
    $debug_count = $debug_result->fetch_assoc()['count'];
    echo "<!-- Debug: Records for year $selected_year month $selected_month: $debug_count -->";
    
    // Check what months actually have data
    $months_query = "SELECT DISTINCT YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count FROM billing GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY year, month";
    $months_result = $conn->query($months_query);
    echo "<!-- Debug: Available months: ";
    while($month_row = $months_result->fetch_assoc()) {
        echo "Year: {$month_row['year']}, Month: {$month_row['month']}, Count: {$month_row['count']} | ";
    }
    echo " -->";
}

// Store payment analysis data for both HTML display and JavaScript
$payment_analysis_data = [];
if ($payment_analysis->num_rows > 0) {
    while($row = $payment_analysis->fetch_assoc()) {
        $payment_analysis_data[] = $row;
    }
}

// Appointment status analysis - Fixed to use appointment_date
$appointment_status_query = "
    SELECT 
        status,
        COUNT(*) as count,
        (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM appointments WHERE " . str_replace('created_at', 'appointment_date', $date_filter) . ")) as percentage
    FROM appointments 
    WHERE " . str_replace('created_at', 'appointment_date', $date_filter) . "
    GROUP BY status
    ORDER BY count DESC
";
echo "<!-- Debug: Appointment status query: " . htmlspecialchars($appointment_status_query) . " -->";
$appointment_status = $conn->query($appointment_status_query);

// Generate available years for filter
$available_years = $conn->query("
    SELECT DISTINCT YEAR(created_at) as year 
    FROM billing 
    ORDER BY year DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Advanced Analytics Dashboard - Green Life Dental Clinic</title>
    
    <!-- Enhanced CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Advanced Chart.js with plugins -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
    
    <!-- Export Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    
    <style>
        :root {
            /* Professional Analytics Color Scheme */
            --primary-blue: #2563eb;
            --secondary-blue: #3b82f6;
            --success-green: #059669;
            --warning-amber: #d97706;
            --danger-red: #dc2626;
            --dark-gray: #1f2937;
            --light-gray: #f8fafc;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --gradient-success: linear-gradient(135deg, #d4e157 0%, #42a5f5 100%);
            --shadow-light: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-heavy: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--dark-gray);
            min-height: 100vh;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
        }

        /* Enhanced Typography */
        h1, h2, h3, h4, h5, h6 {
            font-weight: 700;
            letter-spacing: -0.025em;
            line-height: 1.2;
        }

        /* Enhanced Responsive Design */
        @media (max-width: 1200px) {
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .analytics-header {
                padding: 2rem 0;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1.5rem;
            }
            
            .export-controls {
                flex-direction: column;
                width: 100%;
                margin-top: 1rem;
            }
            
            .btn-export {
                justify-content: center;
                width: 100%;
            }
            
            .filter-panel {
                padding: 1.5rem;
            }
            
            .period-type-group {
                justify-content: center;
            }
            
            .chart-container {
                padding: 1rem;
            }
            
            .canvas-wrapper {
                height: 300px;
            }
        }

        @media (max-width: 576px) {
            .page-title {
                font-size: 1.6rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .stat-value {
                font-size: 2rem;
            }
            
            .period-type-btn {
                font-size: 0.8rem;
                padding: 0.5rem 0.8rem;
            }
        }

        /* Enhanced Page Header */
        .analytics-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 2.5rem 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .analytics-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .page-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            color: white !important;
        }

        .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.85;
            margin: 0;
            font-weight: 400;
            display: flex;
            align-items: center;
            color: white !important;
        }

        .page-subtitle i {
            opacity: 0.8;
        }

        .export-controls {
            display: flex;
            justify-content: flex-end;
            position: relative;
            z-index: 1;
        }

        .btn-export {
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            text-decoration: none;
            backdrop-filter: blur(10px);
        }

        .btn-export:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            color: white;
        }

        .btn-export:active {
            transform: translateY(0);
        }

        /* Advanced Filter Panel */
        .filter-panel {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(226, 232, 240, 0.8);
            position: relative;
            overflow: hidden;
        }

        .filter-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .filter-panel h5 {
            color: #334155;
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .filter-panel h5 i {
            margin-right: 0.75rem;
            font-size: 1.3rem;
            color: #667eea;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .form-label i {
            font-size: 0.8rem;
            opacity: 0.7;
        }

        /* Enhanced Date Picker Styling */
        .date-picker {
            position: relative;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .date-picker:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
            background: #fafbfc;
        }

        .date-picker:hover {
            border-color: #94a3b8;
            background: #f8fafc;
        }

        .form-select {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .form-select:hover {
            border-color: #94a3b8;
            background: #f8fafc;
        }

        /* Period Type Button Group */
        .period-type-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .period-type-btn {
            padding: 0.6rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: white;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .period-type-btn:hover {
            border-color: #2563eb;
            color: #2563eb;
            background: linear-gradient(145deg, #eff6ff 0%, #dbeafe 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }

        .period-type-btn.active {
            border-color: #2563eb;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.3);
        }

        .period-type-btn.active:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            color: white;
        }

        /* Action Buttons */
        .btn-action {
            border-radius: 12px;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-primary.btn-action {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
        }

        .btn-outline-secondary.btn-action {
            border-color: #e2e8f0;
            color: #64748b;
        }

        .btn-outline-secondary.btn-action:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #475569;
        }

        /* Period Status */
        .period-status {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            height: 100%;
        }

        .period-info {
            background: linear-gradient(145deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 0.8rem 1.2rem;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .period-text {
            font-weight: 500;
            color: #334155;
        }

        .badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
        }

        /* Remove default arrow/dropdown appearance */
        .date-picker::-webkit-calendar-picker-indicator {
            background: transparent;
            bottom: 0;
            color: transparent;
            cursor: pointer;
            height: auto;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
            width: auto;
        }

        /* Custom calendar icon */
        .date-picker {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%236b7280' viewBox='0 0 24 24'%3E%3Cpath d='M19 3h-1V1h-2v2H8V1H6v2H5C3.89 3 3.01 3.89 3.01 5L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            padding-right: 45px;
        }

        /* Enhanced Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            border: 1px solid rgba(226, 232, 240, 0.6);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: height 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        }

        .stat-card:hover::before {
            height: 6px;
        }

        /* Individual card color themes */
        .patients-card::before {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }

        .doctors-card::before {
            background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%);
        }

        .appointments-card::before {
            background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
        }

        .revenue-card::before {
            background: linear-gradient(90deg, #8b5cf6 0%, #7c3aed 100%);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1e293b;
            line-height: 1;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .stat-label {
            font-size: 0.95rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-bottom: 0.75rem;
        }

        .stat-icon {
            width: 4rem;
            height: 4rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.75rem;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }

        .patients-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .doctors-icon {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .appointments-icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .revenue-icon {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }

        .stat-change {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            background: rgba(248, 250, 252, 0.8);
            border: 1px solid rgba(226, 232, 240, 0.6);
        }

        .stat-change.positive {
            color: #059669;
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.2);
        }

        .stat-change.negative {
            color: #dc2626;
            background: rgba(220, 38, 38, 0.1);
            border-color: rgba(220, 38, 38, 0.2);
        }

        .stat-change.info {
            color: #0369a1;
            background: rgba(3, 105, 161, 0.1);
            border-color: rgba(3, 105, 161, 0.2);
        }

        /* Chart Containers */
        .chart-container {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            border: 1px solid rgba(226, 232, 240, 0.6);
            position: relative;
            overflow: hidden;
        }

        .chart-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
            position: relative;
        }

        .chart-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            letter-spacing: -0.025em;
        }

        .chart-title i {
            margin-right: 0.75rem;
            color: #2563eb;
            font-size: 1.3rem;
        }

        .chart-controls {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .chart-controls .badge {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-size: 0.8rem;
        }

        .canvas-wrapper {
            position: relative;
            height: 420px;
            margin: 1rem 0;
            background: rgba(248, 250, 252, 0.3);
            border-radius: 16px;
            padding: 1rem;
        }

        .canvas-wrapper.small {
            height: 320px;
        }

        /* Data Tables */
        .data-table-container {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            border: 1px solid rgba(226, 232, 240, 0.6);
            position: relative;
        }

        .data-table-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .table-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 2rem;
        }

        .table-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            letter-spacing: -0.025em;
        }

        .table-title i {
            margin-right: 0.75rem;
            font-size: 1.3rem;
            opacity: 0.9;
        }

        .professional-table {
            width: 100%;
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
        }

        .professional-table th {
            background: linear-gradient(145deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1.2rem 1.5rem;
            font-weight: 700;
            color: #334155;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.075em;
            position: relative;
        }

        .professional-table th:first-child {
            border-top-left-radius: 12px;
        }

        .professional-table th:last-child {
            border-top-right-radius: 12px;
        }

        .professional-table td {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }

        .professional-table tbody tr {
            transition: all 0.3s ease;
        }

        .professional-table tbody tr:hover {
            background: linear-gradient(145deg, #f8fafc 0%, #f0f4ff 100%);
            transform: scale(1.01);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .professional-table tbody tr:last-child td {
            border-bottom: none;
        }

        .professional-table tbody tr:last-child td:first-child {
            border-bottom-left-radius: 12px;
        }

        .professional-table tbody tr:last-child td:last-child {
            border-bottom-right-radius: 12px;
        }

        /* Table data formatting */
        .professional-table .text-success {
            color: #059669 !important;
            font-weight: 600;
        }

        .professional-table .text-info {
            color: #0369a1 !important;
            font-weight: 600;
        }

        .professional-table .text-warning {
            color: #d97706 !important;
            font-weight: 600;
        }

        /* Modern Data Cards Design */
        .data-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            padding: 1rem;
        }

        .data-card {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            border: 1px solid rgba(226, 232, 240, 0.6);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .data-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: height 0.3s ease;
        }

        .data-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        }

        .data-card:hover::before {
            height: 6px;
        }

        /* Ranking System */
        .rank-gold::before {
            background: linear-gradient(90deg, #ffd700 0%, #ffb347 100%);
        }

        .rank-silver::before {
            background: linear-gradient(90deg, #c0c0c0 0%, #a8a8a8 100%);
        }

        .rank-bronze::before {
            background: linear-gradient(90deg, #cd7f32 0%, #b87333 100%);
        }

        .rank-default::before {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        /* Card Header */
        .data-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        /* Service Cards */
        .service-info h4.service-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .service-rank, .doctor-rank, .payment-rank {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .rank-icon {
            color: #f59e0b;
            font-size: 0.9rem;
        }

        .service-revenue, .doctor-revenue, .payment-total {
            text-align: right;
        }

        .revenue-amount, .total-amount {
            font-size: 1.5rem;
            font-weight: 800;
            color: #059669;
            line-height: 1;
            margin-bottom: 0.3rem;
        }

        .revenue-label, .total-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        /* Card Body */
        .data-card-body {
            space-y: 1rem;
        }

        .service-metrics, .doctor-metrics, .payment-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .metric-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem;
            background: rgba(248, 250, 252, 0.8);
            border-radius: 12px;
            border: 1px solid rgba(226, 232, 240, 0.6);
        }

        .metric-icon {
            width: 2rem;
            height: 2rem;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .metric-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1;
        }

        .metric-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        /* Performance Sections */
        .performance-section, .efficiency-section, .market-share-section {
            margin-bottom: 1rem;
        }

        .performance-label, .efficiency-label, .market-share-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .performance-bar, .market-share-bar {
            position: relative;
            background: #f1f5f9;
            border-radius: 8px;
            height: 24px;
            overflow: hidden;
        }

        .performance-fill, .share-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            border-radius: 8px;
            transition: width 0.8s ease;
            position: relative;
        }

        .performance-text {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            font-size: 0.8rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        .share-percentage {
            font-weight: 700;
            color: #059669;
        }

        /* Doctor Cards */
        .doctor-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .doctor-avatar {
            width: 3rem;
            height: 3rem;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .doctor-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.3rem;
            line-height: 1.3;
        }

        .efficiency-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .efficiency-badge.excellent {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .efficiency-badge.good {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .efficiency-badge.needs-improvement {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        /* Payment Cards */
        .payment-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .payment-icon {
            width: 3rem;
            height: 3rem;
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .payment-method {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.3rem;
            line-height: 1.3;
        }

        .payment-cards .data-card {
            min-height: 280px;
        }

        .rating-section {
            margin-top: 1rem;
        }

        .rating-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            width: 100%;
            justify-content: center;
        }

        .rating-badge.excellent {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .rating-badge.good {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .rating-badge.needs-improvement {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        /* Responsive Design for Cards */
        @media (max-width: 768px) {
            .data-cards-grid {
                grid-template-columns: 1fr;
                padding: 0.5rem;
                gap: 1rem;
            }
            
            .data-card {
                padding: 1rem;
            }
            
            .data-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .service-revenue, .doctor-revenue, .payment-total {
                text-align: left;
            }
        }

        /* Unified Analytics Section */
        .unified-analytics-section {
            margin: 2rem 0;
        }

        .analytics-container {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            border: 1px solid rgba(226, 232, 240, 0.6);
            position: relative;
            overflow: hidden;
        }

        .analytics-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .section-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .section-title {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .section-subtitle {
            font-size: 1.1rem;
            color: #64748b;
            font-weight: 500;
        }

        .comprehensive-data-grid {
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
        }

        .data-section {
            background: rgba(248, 250, 252, 0.5);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .data-section-header {
            margin-bottom: 1.5rem;
        }

        .data-section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }

        .data-section-title i {
            color: #667eea;
            font-size: 1.2rem;
        }

        .data-cards-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        /* Compact Card Design */
        .compact-card {
            background: white;
            border-radius: 16px;
            padding: 1.2rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            border: 1px solid rgba(226, 232, 240, 0.8);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .compact-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: height 0.3s ease;
        }

        .compact-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }

        .compact-card:hover::before {
            height: 4px;
        }

        .compact-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .compact-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 0.2rem 0;
            line-height: 1.3;
        }

        .compact-rank {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
        }

        .compact-value {
            font-size: 1.3rem;
            font-weight: 800;
            color: #059669;
            text-align: right;
            line-height: 1;
        }

        .compact-metrics {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .metric-compact {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
        }

        .compact-progress {
            height: 6px;
            background: #f1f5f9;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            border-radius: 3px;
            transition: width 0.8s ease;
        }

        .efficiency-compact {
            display: flex;
            justify-content: center;
        }

        .efficiency-badge-compact {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
        }

        .efficiency-badge-compact.excellent {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .efficiency-badge-compact.good {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .efficiency-badge-compact.needs-improvement {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .rating-compact {
            display: flex;
            justify-content: center;
        }

        .rating-badge-compact {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
        }

        .rating-badge-compact.excellent {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .rating-badge-compact.good {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .rating-badge-compact.needs-improvement {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        /* Responsive Design for Unified Section */
        @media (max-width: 768px) {
            .data-cards-row {
                grid-template-columns: 1fr;
            }
            
            .section-title {
                font-size: 1.6rem;
            }
            
            .analytics-container {
                padding: 1.5rem;
            }
            
            .data-section {
                padding: 1rem;
            }
        }

        /* Enhanced Export Buttons */
        .export-controls {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            z-index: 1001;
            position: relative;
        }

        .btn-export {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            cursor: pointer;
            z-index: 1000;
            position: relative;
        }

        .btn-export-pdf {
            background: #dc2626;
            color: white;
        }

        .btn-export-pdf:hover {
            background: #b91c1c;
            color: white;
            transform: translateY(-2px);
        }

        /* Percentage Badges */
        .percentage-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .percentage-positive {
            background: #dcfce7;
            color: #166534;
        }

        .percentage-negative {
            background: #fee2e2;
            color: #991b1b;
        }

        .percentage-neutral {
            background: #f3f4f6;
            color: #374151;
        }

        /* Empty State Styling */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h5 {
            margin: 1rem 0 0.5rem 0;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .empty-state p {
            margin: 0.5rem 0;
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.5;
        }

        .empty-state p:first-of-type {
            margin-top: 0;
        }

        .empty-state p:last-of-type {
            font-size: 0.9rem;
            color: #9ca3af;
            font-weight: 400;
            font-style: italic;
        }

        /* Enhanced Interactive Elements */
        .btn, .form-control, .form-select {
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .form-control:focus,
        .form-select:focus {
            transform: translateY(-1px);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
        }

        /* Unified Payment Methods Table */
        .unified-payment-section {
            margin-top: 2rem;
        }

        .payment-summary-table {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.3);
        }

        .modern-table {
            margin: 0;
            border: none;
        }

        .modern-table thead th {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            border: none;
            padding: 1.2rem 1rem;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .modern-table tbody tr {
            transition: all 0.3s ease;
            border: none;
        }

        .modern-table tbody tr:hover {
            background: linear-gradient(145deg, #eff6ff 0%, #dbeafe 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.1);
        }

        .modern-table tbody td {
            padding: 1.2rem 1rem;
            border: none;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .rank-icon {
            font-size: 1.3rem;
            filter: drop-shadow(0 1px 2px rgba(0,0,0,0.1));
        }

        .progress {
            border-radius: 12px;
            background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 100%);
            height: 8px;
        }

        .progress-bar {
            border-radius: 12px;
            transition: width 0.8s ease;
        }

        .progress-bar.bg-success {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%) !important;
        }

        .progress-bar.bg-primary {
            background: linear-gradient(90deg, #2563eb 0%, #1d4ed8 100%) !important;
        }

        .progress-bar.bg-warning {
            background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%) !important;
        }

        .modern-table .badge.bg-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            color: white;
            font-weight: 600;
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-size: 0.75rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .modern-table .badge.bg-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
            color: white;
            font-weight: 600;
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-size: 0.75rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .modern-table .badge.bg-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            color: white;
            font-weight: 600;
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-size: 0.75rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .modern-table .badge.bg-light {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;
            color: #475569;
            border: 1px solid #cbd5e1;
            font-weight: 600;
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        .modern-table .text-success {
            color: #059669 !important;
            font-weight: 700;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Enhanced Analytics Header -->
    <div class="analytics-header">
        <div class="container">
            <div class="row align-items-center justify-content-between">
                <div class="col-lg-7 col-md-8">
                    <div class="header-content">
                        <h1 class="page-title">
                            <i class="fas fa-chart-bar me-3"></i>
                            Analytics Dashboard
                        </h1>
                        <p class="page-subtitle">
                            <i class="fas fa-tooth me-2"></i>
                            Professional insights for dental clinic operations
                        </p>
                    </div>
                </div>
                <div class="col-lg-5 col-md-4">
                    <div class="export-controls">
                        <button class="btn-export btn-export-pdf" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf me-2"></i>
                            <span>Export PDF</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="padding-bottom: 2rem;">
        <!-- Enhanced Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card patients-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($total_patients) ?></div>
                        <div class="stat-label">Total Patients</div>
                        <div class="stat-change positive">
                            <i class="fas fa-user-plus me-1"></i>
                            Active patient base
                        </div>
                    </div>
                    <div class="stat-icon patients-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card doctors-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($total_doctors) ?></div>
                        <div class="stat-label">Medical Staff</div>
                        <div class="stat-change positive">
                            <i class="fas fa-stethoscope me-1"></i>
                            Active practitioners
                        </div>
                    </div>
                    <div class="stat-icon doctors-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card appointments-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($total_appointments) ?></div>
                        <div class="stat-label">Appointments</div>
                        <div class="stat-change info">
                            <i class="fas fa-calendar-check me-1"></i>
                            <?= $period_description ?>
                        </div>
                    </div>
                    <div class="stat-icon appointments-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card revenue-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <div class="stat-value">RM<?= number_format($total_revenue, 0) ?></div>
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-change <?= $revenue_growth >= 0 ? 'positive' : 'negative' ?>">
                            <i class="fas fa-<?= $revenue_growth >= 0 ? 'trend-up' : 'trend-down' ?> me-1"></i>
                            <?= abs(round($revenue_growth, 1)) ?>% vs last year
                        </div>
                    </div>
                    <div class="stat-icon revenue-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Filter Panel -->
        <div class="filter-panel">
            <h5><i class="fas fa-sliders-h"></i>Filters & Settings</h5>
            <form method="GET" id="filterForm">
                <div class="row g-3 align-items-end">
                    <!-- Date Range Section -->
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label fw-semibold text-muted">
                            <i class="fas fa-calendar-alt me-1"></i>Start Date
                        </label>
                        <input type="date" name="start_date" class="form-control date-picker" 
                               value="<?= htmlspecialchars($selected_start_date) ?>" 
                               onchange="updateFilters()">
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label fw-semibold text-muted">
                            <i class="fas fa-calendar-alt me-1"></i>End Date
                        </label>
                        <input type="date" name="end_date" class="form-control date-picker" 
                               value="<?= htmlspecialchars($selected_end_date) ?>" 
                               onchange="updateFilters()">
                    </div>
                    
                    <!-- Time Period Section -->
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label fw-semibold text-muted">
                            <i class="fas fa-calendar me-1"></i>Year
                        </label>
                        <select name="year" class="form-select" onchange="updateFilters()">
                            <?php 
                            // Reset the result pointer for available_years
                            $available_years->data_seek(0);
                            while($year_row = $available_years->fetch_assoc()): ?>
                            <option value="<?= $year_row['year'] ?>" <?= $year_row['year'] == $selected_year ? 'selected' : '' ?>>
                                <?= $year_row['year'] ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label fw-semibold text-muted">
                            <i class="fas fa-calendar-check me-1"></i>Month
                        </label>
                        <select name="month" class="form-select" onchange="updateFilters()">
                            <option value="0" <?= $selected_month == 0 ? 'selected' : '' ?>>All Months</option>
                            <?php for($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $selected_month == $i ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <!-- Period Type Buttons -->
                    <div class="col-lg-4 col-md-12">
                        <label class="form-label fw-semibold text-muted">
                            <i class="fas fa-clock me-1"></i>Period Type
                        </label>
                        <div class="period-type-group">
                            <a href="?period_type=yearly&year=<?= $selected_year ?>&month=0<?= !empty($selected_start_date) ? '&start_date=' . $selected_start_date : '' ?><?= !empty($selected_end_date) ? '&end_date=' . $selected_end_date : '' ?>" 
                               class="period-type-btn <?= $period_type == 'yearly' ? 'active' : '' ?>"
                               onclick="showNotification('🔄 Switching to yearly view...', 'info', 1000);">
                                <i class="fas fa-calendar-alt"></i>
                                Yearly
                            </a>
                            <a href="?period_type=monthly&year=<?= $selected_year ?>&month=<?= $selected_month > 0 ? $selected_month : date('n') ?><?= !empty($selected_start_date) ? '&start_date=' . $selected_start_date : '' ?><?= !empty($selected_end_date) ? '&end_date=' . $selected_end_date : '' ?>" 
                               class="period-type-btn <?= $period_type == 'monthly' ? 'active' : '' ?>"
                               onclick="showNotification('🔄 Switching to monthly view...', 'info', 1000);">
                                <i class="fas fa-calendar"></i>
                                Monthly
                            </a>
                            <a href="?period_type=weekly&year=<?= $selected_year ?><?= !empty($selected_start_date) ? '&start_date=' . $selected_start_date : '' ?><?= !empty($selected_end_date) ? '&end_date=' . $selected_end_date : '' ?>" 
                               class="period-type-btn <?= $period_type == 'weekly' ? 'active' : '' ?>"
                               onclick="showNotification('🔄 Switching to weekly view...', 'info', 1000);">
                                <i class="fas fa-calendar-week"></i>
                                Weekly
                            </a>
                            <a href="?period_type=custom&year=<?= $selected_year ?>&start_date=<?= htmlspecialchars($selected_start_date) ?>&end_date=<?= htmlspecialchars($selected_end_date) ?>" 
                               class="period-type-btn <?= $period_type == 'custom' ? 'active' : '' ?>"
                               onclick="showNotification('🔄 Switching to custom range...', 'info', 1000);">
                                <i class="fas fa-calendar-plus"></i>
                                Custom
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons and Status -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-primary btn-action" onclick="refreshData()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh Data
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-action" onclick="resetFilters()">
                                <i class="fas fa-undo me-2"></i>Reset All
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="period-status">
                            <div class="period-info">
                                <strong class="text-primary">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Active Filters:
                                </strong>
                                <div class="filter-status mt-2">
                                    <?php if (!empty($selected_start_date) && !empty($selected_end_date)): ?>
                                        <span class="badge bg-primary me-2">
                                            <i class="fas fa-calendar-alt me-1"></i>
                                            <?= date('M j, Y', strtotime($selected_start_date)) ?> - <?= date('M j, Y', strtotime($selected_end_date)) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-info me-2">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?= $selected_month > 0 ? date('F', mktime(0, 0, 0, $selected_month, 1)) . ' ' : 'All Months ' ?><?= $selected_year ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="badge bg-secondary me-2">
                                        <i class="fas fa-clock me-1"></i>
                                        <?= ucfirst($period_type) ?> View
                                    </span>
                                    <?php if ($total_revenue > 0): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-chart-line me-1"></i>
                                            Data Available
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            No Data Found
                                        </span>
                                    <?php endif; ?>
                                <span class="period-text"><?= $period_description ?></span>
                                <?php if ($period_type == 'custom'): ?>
                                <span class="badge bg-info">Custom Range</span>
                                <?php elseif ($period_type == 'weekly'): ?>
                                <span class="badge bg-success">Weekly View</span>
                                <?php elseif ($period_type == 'monthly'): ?>
                                <span class="badge bg-warning text-dark">Monthly View</span>
                                <?php else: ?>
                                <span class="badge bg-primary">Yearly View</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Advanced Charts Section -->
        <div class="row g-4">
            <!-- Monthly Revenue Trend with Dual Axis -->
            <div class="col-12">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-line"></i>
                            Monthly Revenue & Transaction Trends (<?= $period_description ?>)
                        </h3>
                        <div class="chart-controls">
                            <span class="badge bg-primary">Dual Axis Chart</span>
                        </div>
                    </div>
                    <div class="canvas-wrapper">
                        <canvas id="monthlyTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Dental Services Analysis -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">
                            <i class="fas fa-tooth"></i>
                            Dental Services Analysis
                        </h3>
                    </div>
                    <div class="canvas-wrapper small">
                        <canvas id="serviceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Payment Method Distribution -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">
                            <i class="fas fa-credit-card"></i>
                            Payment Methods Analysis
                        </h3>
                    </div>
                    <div class="canvas-wrapper small">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comprehensive Analytics Dashboard -->
        <div class="unified-analytics-section">
            <div class="analytics-container">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-credit-card me-3"></i>
                        Payment Methods Analysis
                    </h2>
                    <p class="section-subtitle">Comprehensive payment method insights and transaction analysis</p>
                </div>

                <!-- All Data Cards in One Grid -->
                <div class="comprehensive-data-grid">
                    <!-- Payment Methods Unified Display -->
                    <?php if (count($payment_analysis_data) > 0): ?>
                        <div class="unified-payment-section">
                            <div class="payment-summary-table">
                                <table class="table table-hover modern-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Payment Method</th>
                                            <th scope="col">Total Amount</th>
                                            <th scope="col">Transactions</th>
                                            <th scope="col">Market Share</th>
                                            <th scope="col">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($payment_analysis_data as $index => $row): 
                                            $rating = $row['percentage'] >= 40 ? 'excellent' : ($row['percentage'] >= 20 ? 'good' : 'needs-improvement');
                                            $badge_class = $rating == 'excellent' ? 'success' : ($rating == 'good' ? 'primary' : 'warning');
                                            $rank_icon = $index == 0 ? '🥇' : ($index == 1 ? '🥈' : ($index == 2 ? '🥉' : ''));
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="rank-icon me-2"><?= $rank_icon ?></span>
                                                    <strong><?= htmlspecialchars(ucwords($row['payment_method'])) ?></strong>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-bold text-success">RM<?= number_format($row['total_amount'], 0) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark"><?= number_format($row['transaction_count']) ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress me-2" style="width: 60px; height: 8px;">
                                                        <div class="progress-bar bg-<?= $badge_class ?>" style="width: <?= $row['percentage'] ?>%"></div>
                                                    </div>
                                                    <small class="fw-bold"><?= round($row['percentage'], 1) ?>%</small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $badge_class ?>">
                                                    <?= $rating == 'excellent' ? 'Excellent' : ($rating == 'good' ? 'Good' : 'Fair') ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state text-center py-5">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No records found for the selected period (<?= htmlspecialchars($period_description) ?>).</p>
                            <p class="text-muted"><small>Try selecting a different time period or check if there are any records in the database.</small></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <!-- Enhanced JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Professional Analytics & Export JavaScript -->
    <script>
        // Simple test function to check if JavaScript is working
        function testPDF() {
            alert('PDF button clicked! JavaScript is working.');
            console.log('PDF button test successful');
            return false; // Prevent any default behavior
        }
        
        // Test on page load
        window.onload = function() {
            console.log('🎯 Window loaded, testing button accessibility...');
            
            const pdfBtn = document.querySelector('.btn-export-pdf');
            
            if (pdfBtn) {
                console.log('✅ PDF button found:', pdfBtn);
                console.log('PDF button onclick:', pdfBtn.onclick);
            } else {
                console.error('❌ PDF button not found');
            }
        };
        
        // Global variables for data
        let monthlyRevenueData = [];
        let servicePerformanceData = [];
        let paymentMethodData = [];
        let appointmentStatusData = [];

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 JavaScript loaded successfully!');
            console.log('🔧 Testing export button functions...');
            
            // Test if export functions exist
            if (typeof testPDF === 'function') {
                console.log('✅ testPDF function exists');
            } else {
                console.error('❌ testPDF function missing');
            }
            
            console.log('Page loaded, initializing charts...');
            console.log('📊 Chart.js available:', typeof Chart !== 'undefined');
            console.log('🎯 Canvas elements found:', document.querySelectorAll('canvas').length);
            
            // Debug: Check data availability
            <?php 
            $monthly_revenue->data_seek(0);
            $debug_months = [];
            $debug_revenues = [];
            while($row = $monthly_revenue->fetch_assoc()) {
                $debug_months[] = date('M', mktime(0, 0, 0, $row['month'], 1));
                $debug_revenues[] = floatval($row['revenue']);
            }
            ?>
            console.log('📈 Debug - Monthly data found:', <?= json_encode($debug_months) ?>);
            console.log('📈 Debug - Monthly revenues:', <?= json_encode($debug_revenues) ?>);
            console.log('🦷 Debug - Service data rows:', <?= count($service_performance_data) ?>);
            console.log('💳 Debug - Payment data rows:', <?= count($payment_analysis_data) ?>);
            
            try {
                initializeCharts();
                setupEventListeners();
                console.log('Charts and listeners initialized successfully');
            } catch (error) {
                console.error('Error during initialization:', error);
                showNotification('⚠️ Error loading dashboard: ' + error.message, 'warning');
            }
        });

        // Prepare and initialize all charts
        function initializeCharts() {
            console.log('Starting chart initialization...');
            
            try {
                // Check if Chart.js is loaded
                if (typeof Chart === 'undefined') {
                    throw new Error('Chart.js library not loaded');
                }
                
                console.log('🔍 Debug - Starting data preparation...');
                console.log('📋 Current filter settings:');
                console.log('- Start Date: <?= $selected_start_date ?>');
                console.log('- End Date: <?= $selected_end_date ?>');
                console.log('- Year: <?= $selected_year ?>');
                console.log('- Month: <?= $selected_month ?>');
                console.log('- Period Type: <?= $period_type ?>');
                
                // Debug: Check database data availability
                console.log('📊 Data availability check:');
                console.log('- Monthly revenue rows: <?= $monthly_revenue->num_rows ?>');
                console.log('- Service performance rows: <?= count($service_performance_data) ?>');
                console.log('- Payment analysis rows: <?= count($payment_analysis_data) ?>');
                
                // If no data at all, show empty state (not sample data)
                if (<?= $monthly_revenue->num_rows ?> === 0) {
                    console.warn('⚠️ No data found in database for the selected period.');
                    // Create empty data structure instead of sample data
                    monthlyRevenueData = {
                        labels: [],
                        revenues: [],
                        transactions: []
                    };
                    
                    servicePerformanceData = {
                        labels: [],
                        revenues: [],
                        frequencies: []
                    };
                    
                    paymentMethodData = {
                        labels: [],
                        amounts: [],
                        percentages: []
                    };
                } else {
                <?php 
                $monthly_revenue->data_seek(0);
                $months = [];
                $revenues = [];
                $transactions = [];
                
                echo "<!-- Debug: Processing monthly revenue data... -->";
                
                // Check if we're using date range filtering
                if (!empty($selected_start_date) && !empty($selected_end_date)) {
                    // For date ranges, only show months that have data
                    $monthlyData = [];
                    while($row = $monthly_revenue->fetch_assoc()) {
                        $monthKey = $row['year'] . '-' . sprintf('%02d', $row['month']);
                        $monthlyData[$monthKey] = [
                            'revenue' => floatval($row['revenue']),
                            'transactions' => intval($row['transaction_count']),
                            'label' => date('M Y', mktime(0, 0, 0, $row['month'], 1, $row['year']))
                        ];
                    }
                    
                    // Sort by month key
                    ksort($monthlyData);
                    
                    foreach($monthlyData as $data) {
                        $months[] = $data['label'];
                        $revenues[] = $data['revenue'];
                        $transactions[] = $data['transactions'];
                    }
                } elseif ($selected_month > 0) {
                    // Single month selected - show context months for better visualization
                    $monthlyDataMap = [];
                    
                    // Collect data from query
                    while($row = $monthly_revenue->fetch_assoc()) {
                        $monthlyDataMap[$row['month']] = [
                            'revenue' => floatval($row['revenue']),
                            'transactions' => intval($row['transaction_count'])
                        ];
                    }
                    
                    // Show 3 months before and after the selected month for context
                    $startMonth = max(1, $selected_month - 2);
                    $endMonth = min(12, $selected_month + 2);
                    
                    for($i = $startMonth; $i <= $endMonth; $i++) {
                        $months[] = date('M', mktime(0, 0, 0, $i, 1));
                        if (isset($monthlyDataMap[$i])) {
                            $revenues[] = $monthlyDataMap[$i]['revenue'];
                            $transactions[] = $monthlyDataMap[$i]['transactions'];
                        } else {
                            $revenues[] = 0;
                            $transactions[] = 0;
                        }
                    }
                } else {
                    // Full year view - show all 12 months with proper data mapping
                    $monthlyDataMap = [];
                    
                    // First, collect all data from the query
                    while($row = $monthly_revenue->fetch_assoc()) {
                        $monthlyDataMap[$row['month']] = [
                            'revenue' => floatval($row['revenue']),
                            'transactions' => intval($row['transaction_count'])
                        ];
                    }
                    
                    // Then create the arrays for all 12 months
                    for($i = 1; $i <= 12; $i++) {
                        $months[] = date('M', mktime(0, 0, 0, $i, 1));
                        if (isset($monthlyDataMap[$i])) {
                            $revenues[] = $monthlyDataMap[$i]['revenue'];
                            $transactions[] = $monthlyDataMap[$i]['transactions'];
                        } else {
                            $revenues[] = 0;
                            $transactions[] = 0;
                        }
                    }
                }
                
                echo "<!-- Debug: Monthly data processed - Months: " . count($months) . ", Revenues: " . count($revenues) . " -->";
                ?>
                
                monthlyRevenueData = {
                    labels: <?= json_encode($months) ?>,
                    revenues: <?= json_encode($revenues) ?>,
                    transactions: <?= json_encode($transactions) ?>
                };
                console.log('📊 Monthly data prepared:', monthlyRevenueData);
                
                // Safety check for monthly data
                if (!monthlyRevenueData.labels) {
                    console.error('❌ Monthly labels missing!');
                    monthlyRevenueData.labels = [];
                }
                if (!monthlyRevenueData.revenues) {
                    console.error('❌ Monthly revenues missing!');
                    monthlyRevenueData.revenues = [];
                }

                // Dental services data - Fixed to use stored data with safety checks
                servicePerformanceData = {
                    labels: <?= json_encode(count($service_performance_data) > 0 ? array_column($service_performance_data, 'service_name') : []) ?>,
                    revenues: <?= json_encode(count($service_performance_data) > 0 ? array_map('floatval', array_column($service_performance_data, 'total_revenue')) : []) ?>,
                    frequencies: <?= json_encode(count($service_performance_data) > 0 ? array_map('intval', array_column($service_performance_data, 'frequency')) : []) ?>
                };
                console.log('🦷 Service data prepared:', servicePerformanceData);

                // Payment method data - Fixed to use stored data with safety checks
                paymentMethodData = {
                    labels: <?= json_encode(count($payment_analysis_data) > 0 ? array_map('ucfirst', array_column($payment_analysis_data, 'payment_method')) : []) ?>,
                    amounts: <?= json_encode(count($payment_analysis_data) > 0 ? array_map('floatval', array_column($payment_analysis_data, 'total_amount')) : []) ?>,
                    percentages: <?= json_encode(count($payment_analysis_data) > 0 ? array_map('floatval', array_column($payment_analysis_data, 'percentage')) : []) ?>
                };
                console.log('💳 Payment data prepared:', paymentMethodData);
                
                } // Close the else block for database data check
                
                // Final data verification with filter consistency check
                console.log('✅ Final data check:');
                console.log('- Monthly data length:', monthlyRevenueData.labels?.length || 0);
                console.log('- Service data length:', servicePerformanceData.labels?.length || 0);  
                console.log('- Payment data length:', paymentMethodData.labels?.length || 0);
                
                // Verify data consistency across different sources
                const hasMonthlyData = monthlyRevenueData.labels?.length > 0;
                const hasServiceData = servicePerformanceData.labels?.length > 0;
                const hasPaymentData = paymentMethodData.labels?.length > 0;
                
                if (!hasMonthlyData && !hasServiceData && !hasPaymentData) {
                    console.warn('⚠️ No data found for current filter settings');
                    showNotification('No data available for the selected period. Try adjusting your filters.', 'info');
                } else if (hasMonthlyData !== hasServiceData || hasMonthlyData !== hasPaymentData) {
                    console.warn('⚠️ Data inconsistency detected across different sections');
                    console.log('- Monthly data available:', hasMonthlyData);
                    console.log('- Service data available:', hasServiceData);
                    console.log('- Payment data available:', hasPaymentData);
                }

                // Initialize individual charts
                createMonthlyTrendChart();
                createServicePerformanceChart();
                createPaymentMethodChart();
                
                console.log('✅ All charts initialized successfully');
                
                // Final status report
                setTimeout(() => {
                    console.log('🎯 Chart Status Report:');
                    console.log('- Monthly Chart:', document.getElementById('monthlyTrendChart') ? '✅ Found' : '❌ Missing');
                    console.log('- Service Chart:', document.getElementById('serviceChart') ? '✅ Found' : '❌ Missing');
                    console.log('- Payment Chart:', document.getElementById('paymentChart') ? '✅ Found' : '❌ Missing');
                }, 1000);
                
            } catch (error) {
                console.error('❌ Error in initializeCharts:', error);
                showNotification('Error loading charts: ' + error.message, 'danger');
            }
        }

        // Advanced Monthly Trend Chart with Dual Axis
        function createMonthlyTrendChart() {
            console.log('📈 Creating monthly trend chart...');
            const ctx = document.getElementById('monthlyTrendChart');
            if (!ctx) {
                console.error('❌ Monthly trend chart canvas not found');
                return;
            }

            console.log('📊 Monthly chart data:', monthlyRevenueData);
            
            // Handle different data scenarios
            let chartData = monthlyRevenueData;
            let showEmptyMessage = false;
            
            if (!monthlyRevenueData.labels || monthlyRevenueData.labels.length === 0) {
                console.warn('⚠️ No monthly data available for chart');
                // Create a zero line at RM0
                chartData = {
                    labels: ['Start', 'End'],
                    revenues: [0, 0],
                    transactions: [0, 0]
                };
                showEmptyMessage = true;
            } else if (monthlyRevenueData.labels.length === 1) {
                console.log('📊 Single data point detected, creating line');
                // Create a line from single point
                const singleLabel = monthlyRevenueData.labels[0];
                const singleRevenue = monthlyRevenueData.revenues[0];
                const singleTransaction = monthlyRevenueData.transactions[0];
                
                chartData = {
                    labels: [singleLabel + ' Start', singleLabel, singleLabel + ' End'],
                    revenues: [singleRevenue, singleRevenue, singleRevenue],
                    transactions: [singleTransaction, singleTransaction, singleTransaction]
                };
            }

            try {
                console.log('✅ Creating chart with processed data:', {
                    labels: chartData.labels,
                    revenues: chartData.revenues,
                    transactions: chartData.transactions
                });

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            label: 'Revenue (RM)',
                            data: chartData.revenues,
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            borderColor: 'rgba(37, 99, 235, 1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y',
                            pointRadius: 6,
                            pointHoverRadius: 8,
                            pointBackgroundColor: 'rgba(37, 99, 235, 1)',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2
                        }, {
                            label: 'Transactions',
                            data: chartData.transactions,
                            backgroundColor: 'rgba(5, 150, 105, 0.1)',
                            borderColor: 'rgba(5, 150, 105, 1)',
                            borderWidth: 3,
                            fill: false,
                            tension: 0.4,
                            yAxisID: 'y1',
                            pointRadius: 6,
                            pointHoverRadius: 8,
                            pointBackgroundColor: 'rgba(5, 150, 105, 1)',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        elements: {
                            point: {
                                radius: function(context) {
                                    // Make points more visible for datasets with few data points
                                    const dataLength = context.dataset.data.length;
                                    return dataLength <= 3 ? 8 : 6;
                                },
                                hoverRadius: function(context) {
                                    const dataLength = context.dataset.data.length;
                                    return dataLength <= 3 ? 10 : 8;
                                }
                            },
                            line: {
                                tension: function(context) {
                                    // Reduce tension for datasets with few data points
                                    const dataLength = context.dataset.data.length;
                                    return dataLength <= 3 ? 0.1 : 0.4;
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: {
                                        size: 12,
                                        weight: '600'
                                    }
                                }
                            },
                            title: {
                                display: showEmptyMessage,
                                text: showEmptyMessage ? 'No data for selected period (showing baseline)' : '',
                                color: '#94a3b8',
                                font: {
                                    size: 14
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: 'white',
                                bodyColor: 'white',
                                borderColor: 'rgba(37, 99, 235, 1)',
                                borderWidth: 1,
                                callbacks: {
                                    title: function(context) {
                                        return context[0].label;
                                    },
                                    label: function(context) {
                                        const label = context.dataset.label || '';
                                        if (label === 'Revenue (RM)') {
                                            return label + ': RM' + context.parsed.y.toLocaleString();
                                        }
                                        return label + ': ' + context.parsed.y;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        weight: '500'
                                    }
                                }
                            },
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Revenue (RM)',
                                    font: {
                                        weight: '600'
                                    }
                                },
                                ticks: {
                                    callback: function(value) {
                                        return 'RM' + value.toLocaleString();
                                    }
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Number of Transactions',
                                    font: {
                                        weight: '600'
                                    }
                                },
                                grid: {
                                    drawOnChartArea: false,
                                },
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });
                console.log('✅ Monthly trend chart created successfully');
            } catch (error) {
                console.error('❌ Error creating monthly trend chart:', error);
                showNotification('Failed to create monthly trend chart', 'warning');
            }
        }

        // Dental Services Bar Chart
        function createServicePerformanceChart() {
            console.log('🦷 Creating service performance chart...');
            const ctx = document.getElementById('serviceChart');
            if (!ctx) {
                console.error('❌ Service chart canvas not found');
                return;
            }

            console.log('📊 Service chart data:', servicePerformanceData);
            
            if (!servicePerformanceData.labels || servicePerformanceData.labels.length === 0) {
                console.warn('⚠️ No service data available for chart');
                // Create a zero-value chart showing services at RM0
                const chartData = {
                    labels: ['General Cleaning', 'Dental Checkup', 'Tooth Filling'], // Default services
                    revenues: [0, 0, 0],
                    frequencies: [0, 0, 0]
                };
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            label: 'Revenue (RM)',
                            data: chartData.revenues,
                            backgroundColor: [
                                'rgba(37, 99, 235, 0.3)',
                                'rgba(5, 150, 105, 0.3)', 
                                'rgba(217, 119, 6, 0.3)'
                            ],
                            borderColor: [
                                'rgba(37, 99, 235, 0.8)',
                                'rgba(5, 150, 105, 0.8)', 
                                'rgba(217, 119, 6, 0.8)'
                            ],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            title: {
                                display: true,
                                text: 'No service data for selected period',
                                color: '#666',
                                font: {
                                    size: 14
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Revenue (RM)'
                                },
                                ticks: {
                                    callback: function(value) {
                                        return 'RM' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
                console.log('✅ Empty service chart created');
                return;
            }

            try {
                console.log('✅ Creating service chart with data:', {
                    labels: servicePerformanceData.labels,
                    revenues: servicePerformanceData.revenues,
                    frequencies: servicePerformanceData.frequencies
                });

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: servicePerformanceData.labels,
                        datasets: [{
                            label: 'Revenue (RM)',
                            data: servicePerformanceData.revenues,
                            backgroundColor: [
                                'rgba(37, 99, 235, 0.8)',
                                'rgba(5, 150, 105, 0.8)', 
                                'rgba(217, 119, 6, 0.8)',
                                'rgba(220, 38, 38, 0.8)',
                                'rgba(147, 51, 234, 0.8)',
                                'rgba(236, 72, 153, 0.8)',
                                'rgba(14, 165, 233, 0.8)',
                                'rgba(34, 197, 94, 0.8)',
                                'rgba(249, 115, 22, 0.8)',
                                'rgba(239, 68, 68, 0.8)'
                            ],
                            borderColor: [
                                'rgba(37, 99, 235, 1)',
                                'rgba(5, 150, 105, 1)', 
                                'rgba(217, 119, 6, 1)',
                                'rgba(220, 38, 38, 1)',
                                'rgba(147, 51, 234, 1)',
                                'rgba(236, 72, 153, 1)',
                                'rgba(14, 165, 233, 1)',
                                'rgba(34, 197, 94, 1)',
                                'rgba(249, 115, 22, 1)',
                                'rgba(239, 68, 68, 1)'
                            ],
                            borderWidth: 2,
                            borderRadius: 8,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: 'white',
                                bodyColor: 'white',
                                borderColor: 'rgba(37, 99, 235, 1)',
                                borderWidth: 1,
                                callbacks: {
                                    label: function(context) {
                                        const revenue = context.parsed.y;
                                        const frequency = servicePerformanceData.frequencies[context.dataIndex];
                                        return [
                                            `Revenue: RM${revenue.toLocaleString()}`,
                                            `Frequency: ${frequency} times`
                                        ];
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    maxRotation: 45,
                                    font: {
                                        size: 10,
                                        weight: '500'
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Revenue (RM)',
                                    font: {
                                        weight: '600'
                                    }
                                },
                                ticks: {
                                    callback: function(value) {
                                        return 'RM' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
                console.log('✅ Service performance chart created successfully');
            } catch (error) {
                console.error('❌ Error creating service performance chart:', error);
                showNotification('Failed to create service performance chart', 'warning');
            }
        }

        // Payment Method Doughnut Chart
        function createPaymentMethodChart() {
            console.log('💳 Creating payment method chart...');
            const ctx = document.getElementById('paymentChart');
            if (!ctx) {
                console.error('❌ Payment chart canvas not found');
                return;
            }

            console.log('📊 Payment chart data:', paymentMethodData);
            
            if (!paymentMethodData.labels || paymentMethodData.labels.length === 0) {
                console.warn('⚠️ No payment data available for chart');
                
                // Hide the canvas and show HTML empty state instead
                ctx.style.display = 'none';
                
                // Create HTML empty state content matching the table style
                const canvasWrapper = ctx.parentElement;
                const emptyStateHTML = `
                    <div class="empty-state text-center py-5">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No records found for the selected period (<?= htmlspecialchars($period_description) ?>).</p>
                        <p class="text-muted"><small>Try selecting a different time period or check if there are any records in the database.</small></p>
                    </div>
                `;
                
                // Insert the empty state HTML
                canvasWrapper.innerHTML = emptyStateHTML;
                
                console.log('✅ Empty payment chart with HTML message created');
                return;
            }

            try {
                console.log('✅ Creating payment chart with data:', {
                    labels: paymentMethodData.labels,
                    amounts: paymentMethodData.amounts,
                    percentages: paymentMethodData.percentages
                });

                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: paymentMethodData.labels,
                        datasets: [{
                            data: paymentMethodData.percentages,
                            backgroundColor: [
                                'rgba(37, 99, 235, 0.8)',
                                'rgba(5, 150, 105, 0.8)',
                                'rgba(217, 119, 6, 0.8)',
                                'rgba(220, 38, 38, 0.8)',
                                'rgba(147, 51, 234, 0.8)'
                            ],
                            borderColor: [
                                'rgba(37, 99, 235, 1)',
                                'rgba(5, 150, 105, 1)',
                                'rgba(217, 119, 6, 1)',
                                'rgba(220, 38, 38, 1)',
                                'rgba(147, 51, 234, 1)'
                            ],
                            borderWidth: 3,
                            hoverBorderWidth: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: {
                                        size: 12,
                                        weight: '600'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: 'white',
                                bodyColor: 'white',
                                borderColor: 'rgba(37, 99, 235, 1)',
                                borderWidth: 1,
                                callbacks: {
                                    label: function(context) {
                                        const method = context.label;
                                        const percentage = context.parsed;
                                        const amount = paymentMethodData.amounts[context.dataIndex];
                                        return [
                                            `${method}: ${percentage.toFixed(1)}%`,
                                            `Amount: RM${amount.toLocaleString()}`
                                        ];
                                    }
                                }
                            }
                        },
                        cutout: '60%'
                    }
                });
                console.log('✅ Payment method chart created successfully');
            } catch (error) {
                console.error('❌ Error creating payment method chart:', error);
                showNotification('Failed to create payment method chart', 'warning');
            }
        }

        // Notification system
        function showNotification(message, type = 'info', duration = 4000) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} position-fixed`;
            notification.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                border-radius: 10px;
                box-shadow: 0 8px 25px rgba(0,0,0,0.15);
                animation: slideInRight 0.4s ease-out;
            `;
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : type === 'warning' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.4s ease-out';
                setTimeout(() => notification.remove(), 400);
            }, duration);
        }

        // PDF Export Function
        function exportToPDF() {
            console.log('🔄 Starting PDF export...');
            
            try {
                // Check if jsPDF is available
                if (typeof window.jspdf === 'undefined') {
                    showNotification('❌ PDF export library not loaded. Please refresh the page.', 'danger');
                    return;
                }
                
                // Show loading notification
                showNotification('🔄 Generating PDF report...', 'info', 2000);
                
                // Create new PDF document
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                
                // Set up PDF styling
                doc.setFontSize(20);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(37, 99, 235);
                doc.text('Green Life Dental Clinic', 20, 30);
                
                doc.setFontSize(16);
                doc.setTextColor(0, 0, 0);
                doc.text('Analytics Report', 20, 45);
                
                doc.setFontSize(12);
                doc.setFont(undefined, 'normal');
                doc.text('Period: <?= $period_description ?>', 20, 60);
                doc.text('Generated: ' + new Date().toLocaleDateString(), 20, 70);
                
                // Add content
                let yPos = 90;
                
                // === SUMMARY SECTION ===
                doc.setFontSize(14);
                doc.setFont(undefined, 'bold');
                doc.text('SUMMARY STATISTICS', 20, yPos);
                yPos += 15;
                
                doc.setFontSize(11);
                doc.setFont(undefined, 'bold');
                doc.text('Total Patients:', 25, yPos);
                doc.setFont(undefined, 'normal');
                doc.text('<?= number_format($total_patients) ?> patients', 120, yPos);
                yPos += 8;
                
                doc.setFont(undefined, 'bold');
                doc.text('Total Doctors:', 25, yPos);
                doc.setFont(undefined, 'normal');
                doc.text('<?= number_format($total_doctors) ?> doctors', 120, yPos);
                yPos += 8;
                
                doc.setFont(undefined, 'bold');
                doc.text('Total Appointments:', 25, yPos);
                doc.setFont(undefined, 'normal');
                doc.text('<?= number_format($total_appointments) ?> appointments', 120, yPos);
                yPos += 8;
                
                doc.setFont(undefined, 'bold');
                doc.text('Total Revenue:', 25, yPos);
                doc.setFont(undefined, 'normal');
                doc.text('RM <?= number_format($total_revenue, 2) ?>', 120, yPos);
                yPos += 20;
                
                // === FINANCIAL SUMMARY BOX ===
                doc.setFontSize(14);
                doc.setFont(undefined, 'bold');
                doc.text('FINANCIAL SUMMARY', 20, yPos);
                yPos += 5;
                
                // Draw a box around total revenue
                doc.setDrawColor(37, 99, 235);
                doc.setLineWidth(1);
                doc.rect(20, yPos, 170, 20);
                
                doc.setFontSize(12);
                doc.setFont(undefined, 'bold');
                doc.text('TOTAL REVENUE FOR PERIOD:', 25, yPos + 8);
                doc.setFontSize(16);
                doc.setTextColor(37, 99, 235);
                doc.text('RM <?= number_format($total_revenue, 2) ?>', 25, yPos + 16);
                
                // === FOOTER ===
                yPos += 35;
                doc.setTextColor(100, 100, 100);
                doc.setFontSize(8);
                doc.text('Green Life Dental Clinic - Confidential Report', 20, yPos);
                doc.text('Generated: ' + new Date().toLocaleString(), 20, yPos + 5);
                doc.text('Authorized by: Clinic Administration', 20, yPos + 10);
                
                // Save PDF
                const currentDate = new Date().toISOString().slice(0, 10);
                doc.save(`green_life_dental_report_${currentDate}.pdf`);
                
                showNotification('✅ PDF report exported successfully!', 'success');
                console.log('✅ PDF export completed successfully');
                
            } catch (error) {
                console.error('❌ PDF export error:', error);
                showNotification('❌ PDF export failed: ' + error.message, 'danger');
            }
        }

        // Event listeners setup
        function setupEventListeners() {
            console.log('🔧 Setting up event listeners...');
            
            try {
                // Add hover effects to stat cards
                document.querySelectorAll('.stat-card').forEach(card => {
                    card.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-8px) scale(1.02)';
                        this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                    });
                    
                    card.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(0) scale(1)';
                    });
                });

                // Add click handlers for period type buttons
                document.querySelectorAll('.period-type-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        // Remove active class from all buttons
                        document.querySelectorAll('.period-type-btn').forEach(b => b.classList.remove('active'));
                        
                        // Add active class to clicked button
                        this.classList.add('active');
                        
                        // Show loading notification
                        showNotification('🔄 Switching to ' + this.textContent.trim() + ' view...', 'info', 1500);
                        
                        // Navigate to the new URL
                        setTimeout(() => {
                            window.location.href = this.href;
                        }, 500);
                    });
                });

                // Test export libraries
                console.log('📊 Checking export libraries...');
                console.log('jsPDF available:', typeof window.jspdf !== 'undefined');
                
                // Add fallback for missing libraries
                if (typeof window.jspdf === 'undefined') {
                    console.warn('⚠️ jsPDF library not loaded');
                    showNotification('⚠️ PDF export library not loaded. Some features may not work.', 'warning');
                }

                // Add form validation listeners
                const filterForm = document.getElementById('filterForm');
                if (filterForm) {
                    filterForm.addEventListener('submit', function(e) {
                        const startDate = this.querySelector('[name="start_date"]').value;
                        const endDate = this.querySelector('[name="end_date"]').value;
                        
                        // Validate date range
                        if (startDate && endDate && startDate > endDate) {
                            e.preventDefault();
                            showNotification('⚠️ Start date cannot be after end date', 'warning');
                            return false;
                        }
                    });
                }

                console.log('✅ Event listeners setup completed successfully');
                
            } catch (error) {
                console.error('❌ Error setting up event listeners:', error);
                showNotification('⚠️ Some interactive features may not work properly', 'warning');
            }
        }

        // Enhanced filter and refresh functions
        function updateFilters() {
            console.log('🔄 Updating filters...');
            
            // Get current form values
            const form = document.getElementById('filterForm');
            const startDate = form.querySelector('[name="start_date"]').value;
            const endDate = form.querySelector('[name="end_date"]').value;
            const year = form.querySelector('[name="year"]').value;
            const month = form.querySelector('[name="month"]').value;
            
            console.log('📋 Filter values:', { startDate, endDate, year, month });
            
            // Validate date range if both dates are provided
            if (startDate && endDate && startDate > endDate) {
                showNotification('⚠️ Start date cannot be after end date', 'warning');
                return false;
            }
            
            // Show loading state
            showNotification('🔄 Applying filters...', 'info', 1000);
            
            // Submit the form
            form.submit();
        }

        function refreshData() {
            console.log('🔄 Refreshing all data...');
            showNotification('🔄 Refreshing dashboard...', 'info', 1000);
            window.location.reload();
        }

        function resetFilters() {
            console.log('🔄 Resetting filters to defaults...');
            
            // Reset all filter inputs to default values
            const form = document.getElementById('filterForm');
            
            // Reset year to current year
            const yearSelect = form.querySelector('select[name="year"]');
            if (yearSelect) {
                yearSelect.value = '<?= date('Y') ?>';
            }
            
            // Reset month to "All Months"
            const monthSelect = form.querySelector('select[name="month"]');
            if (monthSelect) {
                monthSelect.value = '0';
            }
            
            // Clear date inputs
            const startDateInput = form.querySelector('input[name="start_date"]');
            const endDateInput = form.querySelector('input[name="end_date"]');
            if (startDateInput) startDateInput.value = '';
            if (endDateInput) endDateInput.value = '';
            
            showNotification('🔄 Resetting filters...', 'info', 1000);
            
            // Submit with reset values
            form.submit();
        }

        // Test function for debugging
        function testExportFunction() {
            console.log('Export button clicked');
            console.log('jsPDF available:', typeof window.jspdf !== 'undefined');
            console.log('Monthly data:', monthlyRevenueData);
            console.log('Service data:', servicePerformanceData);
            console.log('Payment data:', paymentMethodData);
            return false; // Prevent default behavior
        }

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>