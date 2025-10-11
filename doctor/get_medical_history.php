<?php
// AJAX endpoint for patient medical history table
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    http_response_code(403);
    exit('Unauthorized');
}

$doctor_id = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor = $result->fetch_assoc();
    if ($doctor) {
        $doctor_id = $doctor['id'];
    }
}

// 分頁參數
$records_per_page = 10; // 每頁顯示記錄數
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

$patient_email = isset($_GET['patient_email']) ? trim($_GET['patient_email']) : '';
if (!$doctor_id || !$patient_email) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

// 確認醫生是否有權限
$stmt = $conn->prepare("
    SELECT patient_name 
    FROM appointments
    WHERE doctor_id = ? AND patient_email = ? AND status != 'cancelled_by_patient' AND status != 'cancelled_by_admin' AND status != 'rejected' AND status != 'cancelled'
    LIMIT 1
");
$stmt->bind_param("is", $doctor_id, $patient_email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo '<div class="alert alert-danger">You do not have permission to view this patient\'s records.</div>';
    exit;
}
$patient_name = $result->fetch_assoc()['patient_name'];

// 先計算總記錄數
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM medical_records mr
    JOIN doctors d ON mr.doctor_id = d.id
    WHERE mr.patient_email = ?
");
$count_stmt->bind_param("s", $patient_email);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// 計算分頁
$total_pages = ceil($total_records / $records_per_page);
$start_record = ($current_page - 1) * $records_per_page + 1;
$end_record = min($current_page * $records_per_page, $total_records);

// 获取病人病历记录 (分頁)
$stmt = $conn->prepare("
SELECT mr.id, mr.visit_date, mr.created_at, d.name as doctor_name, 
   mr.report_generated
FROM medical_records mr
JOIN doctors d ON mr.doctor_id = d.id
WHERE mr.patient_email = ?
ORDER BY mr.created_at DESC
LIMIT ? OFFSET ?
");
$stmt->bind_param("sii", $patient_email, $records_per_page, $offset);
$stmt->execute();
$records = $stmt->get_result();

if ($records->num_rows > 0) {
    echo '<div class="table-responsive mt-3 mb-4 px-2 py-3 rounded shadow-sm">';
    echo '<table class="table table-bordered align-middle mb-0">';
    echo '<thead class="table-light">';
    echo '<tr>';
    echo '<th>Visit Date</th><th>Doctor</th><th>Created At</th><th>Actions</th>';
    echo '</tr></thead><tbody>';
    $today = date("Y-m-d");
    while ($row = $records->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['visit_date']) . '</td>';
        echo '<td>Dr. ' . htmlspecialchars($row['doctor_name']) . '</td>';
        echo '<td>' . date('M j, Y g:i A', strtotime($row['created_at'])) . '</td>';
        echo '<td>';
        
        // View Details 按钮（如果报告已生成）
        if ($row['report_generated']) {
            echo '<a href="view_report.php?record_id=' . intval($row['id']) . '&patient_email=' . urlencode($patient_email) . '" 
                     class="btn btn-info btn-sm me-2" target="_blank" 
                     style="min-width:90px;">
                     <i class="fas fa-eye me-1"></i>View Details
                  </a>';
        }
        
        // Delete 按钮（只有当天的记录可以删除）
        if ($row['visit_date'] === $today) {
            echo '<button class="btn btn-danger btn-sm delete-record-btn" data-id="' . intval($row['id']) . '" style="min-width:70px;">
                     <i class="fas fa-trash me-1"></i>Delete
                  </button>';
        }
        
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
    
    // 添加分頁導航
    if ($total_pages > 1) {
        echo '<div class="d-flex justify-content-between align-items-center mt-3 px-2">';
        echo '<small class="text-muted">Showing ' . $start_record . '-' . $end_record . ' of ' . $total_records . ' records</small>';
        echo '<nav aria-label="Medical Records Pagination">';
        echo '<ul class="pagination pagination-sm mb-0">';
        
        // Previous
        if ($current_page > 1) {
            $prev_page = $current_page - 1;
            echo '<li class="page-item"><a class="page-btn" href="#" onclick="window.loadMedicalHistory(\'' . $patient_email . '\', ' . $prev_page . '); return false;">&laquo; Previous</a></li>';
        } else {
            echo '<li class="page-item disabled"><span class="page-link">&laquo; Previous</span></li>';
        }
        
        // Page numbers
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $current_page) {
                echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                echo '<li class="page-item"><a class="page-link" href="#" onclick="window.loadMedicalHistory(\'' . $patient_email . '\', ' . $i . '); return false;">' . $i . '</a></li>';
            }
        }
        
        // Next
        if ($current_page < $total_pages) {
            $next_page = $current_page + 1;
            echo '<li class="page-item"><a class="page-link" href="#" onclick="window.loadMedicalHistory(\'' . $patient_email . '\', ' . $next_page . '); return false;">Next &raquo;</a></li>';
        } else {
            echo '<li class="page-item disabled"><span class="page-link">Next &raquo;</span></li>';
        }
        
        echo '</ul></nav></div>';
    }
    echo '<script>
    $(document).off("click", ".delete-record-btn").on("click", ".delete-record-btn", function() {
        if(!confirm("Are you sure you want to delete this record?")) return;
        var btn = $(this);
        var id = btn.data("id");
        $.ajax({
            url: "delete_medical_record.php",
            type: "POST",
            data: { record_id: id },
            success: function(res) {
                if(res.trim() === "success") {
                    // 使用全局的 loadMedicalHistory 函数
                    if (typeof window.loadMedicalHistory === "function") {
                        window.loadMedicalHistory("' . $patient_email . '", ' . $current_page . ');
                    }
                } else {
                    alert("Delete failed: " + res);
                }
            },
            error: function() {
                alert("Delete failed.");
            }
        });
    });
    </script>';
} else {
    echo '<div class="alert alert-info mt-3">No medical records found for this patient.</div>';
}
