<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 初始化搜索變量
$search = isset($_GET['search']) ? trim($_GET['search']) : "";

// 分頁設定
$patients_per_page = 12; // 每頁顯示病人數
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $patients_per_page;

// 先計算總病人數
$count_sql = "SELECT COUNT(DISTINCT a.patient_email) as total FROM appointments a WHERE a.status = 'confirmed'";
if (!empty($search)) {
    $count_sql .= " AND (a.patient_name LIKE ? OR a.patient_email LIKE ? OR a.patient_phone LIKE ?)";
    $count_stmt = $conn->prepare($count_sql);
    $searchParam = "%$search%";
    $count_stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
} else {
    $count_stmt = $conn->prepare($count_sql);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_patients = $total_result->fetch_assoc()['total'];
$count_stmt->close();

// 計算分頁
$total_pages = ceil($total_patients / $patients_per_page);
$start_patient = ($current_page - 1) * $patients_per_page + 1;
$end_patient = min($current_page * $patients_per_page, $total_patients);

// 只取病人資訊，每個 email 只顯示一次（分頁）
$sql = "
    SELECT 
           a.patient_name, 
           a.patient_email, 
           a.patient_phone
    FROM appointments a
    WHERE a.status = 'confirmed'
    GROUP BY a.patient_email
";

if (!empty($search)) {
    $sql = "
        SELECT 
               a.patient_name, 
               a.patient_email, 
               a.patient_phone
        FROM appointments a
        WHERE a.status = 'confirmed'
          AND (a.patient_name LIKE ? OR a.patient_email LIKE ? OR a.patient_phone LIKE ?)
        GROUP BY a.patient_email
        LIMIT ? OFFSET ?
    ";
    $stmt = $conn->prepare($sql);
    $searchParam = "%$search%";
    $stmt->bind_param("sssii", $searchParam, $searchParam, $searchParam, $patients_per_page, $offset);
} else {
    $sql .= " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $patients_per_page, $offset);
}

$stmt->execute();
$patients = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👥 Patient Records - Green Life Dental Clinic</title>
    
    <!-- Bootstrap 5.3.0 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            /* Medical Clinic Color Scheme */
            --clinic-primary: #0ea5e9;
            --clinic-secondary: #22d3ee;
            --clinic-success: #10b981;
            --clinic-warning: #f59e0b;
            --clinic-danger: #ef4444;
            --clinic-dark: #1f2937;
            --clinic-light: #f8fafc;
            --clinic-background: linear-gradient(135deg, #e0f7fa 0%, #e1f5fe 50%, #f3e5f5 100%);
        }

        body {
            background: var(--clinic-background);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--clinic-dark);
            min-height: 100vh;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
            padding: 2rem 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            color: white !important;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-top: 0.5rem;
            color: white !important;
        }

        /* Card Styling */
        .clinic-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid rgba(14, 165, 233, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .clinic-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.12);
        }

        .card-header-clinic {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
            padding: 1.5rem;
            border: none;
            font-size: 1.2rem;
            font-weight: 600;
        }

        /* Search Form Styling */
        .search-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .search-input {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--clinic-primary);
            box-shadow: 0 0 0 0.2rem rgba(14, 165, 233, 0.25);
            outline: none;
        }

        /* Button Styling */
        .btn-clinic {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-clinic-primary {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
        }

        .btn-clinic-primary:hover {
            background: linear-gradient(135deg, #0284c7, #0891b2);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
            color: white;
        }

        /* Table Styling */
        .table-clinic {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .table-clinic thead {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
        }

        .table-clinic thead th {
            border: none;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .table-clinic tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .table-clinic tbody tr:hover {
            background: rgba(14, 165, 233, 0.05);
        }

        .table-clinic tbody td {
            padding: 1rem;
            vertical-align: middle;
        }

        /* Patient Info Styling */
        .patient-name {
            font-weight: 600;
            color: var(--clinic-dark);
        }

        .patient-contact {
            font-size: 0.9rem;
            color: #64748b;
        }

        .contact-link {
            color: var(--clinic-primary);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .contact-link:hover {
            color: var(--clinic-secondary);
        }

        /* Action Button */
        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            transition: all 0.2s ease;
            background: linear-gradient(135deg, var(--clinic-success), #059669);
            color: white;
        }

        .action-btn:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
            color: white;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--clinic-dark);
            margin-bottom: 1.5rem;
        }

        .section-icon {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
            width: 3rem;
            height: 3rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }

        .empty-state-icon {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 4px solid var(--clinic-primary);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--clinic-primary);
        }

        .stats-label {
            font-size: 0.9rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }

            .table-clinic {
                font-size: 0.9rem;
            }

            .section-header {
                font-size: 1.3rem;
            }
        }
        
        /* Pagination Styles */
        .pagination-container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(240, 249, 255, 0.95));
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 8px 32px rgba(14, 165, 233, 0.15);
            border: 1px solid rgba(14, 165, 233, 0.1);
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            background: var(--clinic-light);
            border: 2px solid rgba(14, 165, 233, 0.2);
            color: var(--clinic-primary);
            padding: 0.5rem 0.75rem;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            min-width: 45px;
            justify-content: center;
        }
        
        .page-btn:hover {
            background: var(--clinic-primary);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(14, 165, 233, 0.4);
        }
        
        .page-btn.active {
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: #1a202c;
            border-color: var(--clinic-primary);
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
            text-shadow: none;
        }
        
        .page-btn.disabled {
            background: rgba(248, 250, 252, 0.8);
            color: rgba(100, 116, 139, 0.6);
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-info {
            text-align: center;
            color: var(--clinic-dark);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">
                        <i class="bi bi-people-fill me-3"></i>
                        Patient Records
                    </h1>
                    <p class="page-subtitle">👥 View all confirmed patients and their medical history</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="stats-card d-inline-block">
                        <div class="stats-number"><?= $patients->num_rows ?></div>
                        <div class="stats-label">Total Patients</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="padding-bottom: 2rem;">
        
        <!-- Search Section -->
        <div class="search-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="bi bi-search"></i>
                </div>
                <span>🔍 Search Patient Records</span>
            </div>
            <form method="GET" class="row align-items-end">
                <div class="col-md-10">
                    <label class="form-label fw-bold text-muted">Search by name, email, or phone number</label>
                    <input type="text" name="search" class="form-control search-input" 
                           placeholder="Enter patient name, email address, or phone number..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2 mt-3 mt-md-0">
                    <button type="submit" class="btn btn-clinic btn-clinic-primary w-100">
                        <i class="bi bi-search me-2"></i>Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Patients Table Section -->
        <?php if ($patients->num_rows > 0) { ?>
        <div class="clinic-card">
            <div class="card-header-clinic">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="section-header mb-0">
                        <div class="section-icon" style="width: 2.5rem; height: 2.5rem; font-size: 1.1rem;">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                        <span>📋 Confirmed Patients Directory</span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span style="background: rgba(255, 255, 255, 0.2); padding: 0.5rem 1rem; border-radius: 10px; font-size: 0.9rem; font-weight: 600;">
                            <?php echo $patients->num_rows; ?> of <?= number_format($total_patients) ?> Patients
                        </span>
                        <?php if ($total_pages > 1): ?>
                        <small style="color: rgba(255, 255, 255, 0.9); font-weight: 500;">
                            Page <?= $current_page ?> of <?= $total_pages ?>
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-clinic mb-0">
                        <thead>
                            <tr>
                                <th>👤 Patient Information</th>
                                <th>📧 Contact Details</th>
                                <th>📞 Phone Number</th>
                                <th>🏥 Medical Records</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $patients->fetch_assoc()) { ?>
                            <tr>
                                <td>
                                    <div class="patient-name">
                                        <i class="bi bi-person-circle me-2 text-primary"></i>
                                        <?= htmlspecialchars($row['patient_name'] ?? 'Not specified'); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($row['patient_email'])) { ?>
                                        <a href="mailto:<?= htmlspecialchars($row['patient_email']); ?>" class="contact-link">
                                            <i class="bi bi-envelope me-1"></i>
                                            <?= htmlspecialchars($row['patient_email']); ?>
                                        </a>
                                    <?php } else { ?>
                                        <span class="text-muted">
                                            <i class="bi bi-envelope-x me-1"></i>
                                            No email provided
                                        </span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['patient_phone'])) { ?>
                                        <a href="tel:<?= htmlspecialchars($row['patient_phone']); ?>" class="contact-link">
                                            <i class="bi bi-telephone me-1"></i>
                                            <?= htmlspecialchars($row['patient_phone']); ?>
                                        </a>
                                    <?php } else { ?>
                                        <span class="text-muted">
                                            <i class="bi bi-telephone-x me-1"></i>
                                            No phone provided
                                        </span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['patient_email'])) { ?>
                                        <a href="patient_history.php?patient_email=<?= urlencode($row['patient_email']); ?>" 
                                           class="btn action-btn">
                                            <i class="bi bi-journal-medical me-1"></i>
                                            View Patient's Medical Record History
                                        </a>
                                    <?php } else { ?>
                                        <span class="text-muted">
                                            <i class="bi bi-journal-x me-1"></i>
                                            No email for history
                                        </span>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination Navigation -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <nav aria-label="Patients pagination" class="d-flex justify-content-center">
                    <ul class="pagination pagination-clinic">
                        <!-- Previous Button -->
                        <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                               aria-label="Previous Page">
                                <i class="bi bi-chevron-left"></i>
                                <span class="d-none d-sm-inline ms-1">Previous</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>

                        <!-- Next Button -->
                        <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                               aria-label="Next Page">
                                <span class="d-none d-sm-inline me-1">Next</span>
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <!-- Pagination Info -->
                <div class="text-center mt-3">
                    <small class="text-muted pagination-info">
                        <i class="bi bi-info-circle me-1"></i>
                        Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_patients) ?> of <?= number_format($total_patients) ?> patients
                        <?php if (!empty($search)): ?>
                            <span class="ms-2">
                                <i class="bi bi-search me-1"></i>
                                Filtered by: "<strong><?= htmlspecialchars($search) ?></strong>"
                            </span>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php } else { ?>
        <div class="clinic-card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <h5 class="text-muted mb-3">👥 No Confirmed Patients Found</h5>
                    <?php if (!empty($search)) { ?>
                        <p class="text-muted">
                            No patients match your search criteria: "<strong><?= htmlspecialchars($search) ?></strong>"
                        </p>
                        <a href="patient_records.php" class="btn btn-clinic btn-clinic-primary mt-3">
                            <i class="bi bi-arrow-clockwise me-2"></i>
                            Clear Search & View All
                        </a>
                    <?php } else { ?>
                        <p class="text-muted mb-4">
                            There are currently no patients with confirmed appointments in the system.
                        </p>
                        <a href="manage_appointments.php" class="btn btn-clinic btn-clinic-primary">
                            <i class="bi bi-calendar-plus me-2"></i>
                            Manage Appointments
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>

    <!-- Bootstrap 5.3.0 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Enhanced Patient Records JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Enhanced search functionality
            const searchInput = document.querySelector('input[name="search"]');
            const form = searchInput.closest('form');
            
            // Auto-search on input with debounce
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length >= 3 || this.value.length === 0) {
                        form.submit();
                    }
                }, 500);
            });
            
            // Highlight search terms in results
            const searchTerm = '<?= htmlspecialchars($search) ?>';
            if (searchTerm) {
                const tableRows = document.querySelectorAll('.table-clinic tbody tr');
                tableRows.forEach(row => {
                    const text = row.textContent;
                    if (text.toLowerCase().includes(searchTerm.toLowerCase())) {
                        row.style.backgroundColor = 'rgba(14, 165, 233, 0.05)';
                        row.style.borderLeft = '4px solid var(--clinic-primary)';
                    }
                });
            }
            
            // Contact link enhancements
            const contactLinks = document.querySelectorAll('.contact-link');
            contactLinks.forEach(link => {
                link.addEventListener('click', function() {
                    // Add visual feedback
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
            
            // Medical history button enhancements
            const historyButtons = document.querySelectorAll('.action-btn');
            historyButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Add loading state
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Loading...';
                    this.disabled = true;
                    
                    // Re-enable after navigation (fallback)
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 2000);
                });
            });
            
            // Statistics counter animation
            const statsNumber = document.querySelector('.stats-number');
            if (statsNumber) {
                const targetNumber = parseInt(statsNumber.textContent);
                let currentNumber = 0;
                const increment = Math.ceil(targetNumber / 30);
                
                const counter = setInterval(() => {
                    currentNumber += increment;
                    if (currentNumber >= targetNumber) {
                        currentNumber = targetNumber;
                        clearInterval(counter);
                    }
                    statsNumber.textContent = currentNumber;
                }, 50);
            }
        });
    </script>
</body>
</html>
