<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Page-specific variables
$page_title = "Patient Medical History";

// 搜尋參數
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$patient_email = isset($_GET['patient_email']) ? $_GET['patient_email'] : "";

// Pagination variables
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($current_page - 1) * $limit;

// 取得所有已批准病人
$sql = "
    SELECT DISTINCT a.patient_email, a.patient_name, a.patient_phone
    FROM appointments a
    WHERE a.status = 'confirmed' AND a.patient_email IS NOT NULL
";
if (!empty($search)) {
    $sql .= " AND (a.patient_name LIKE ? OR a.patient_email LIKE ? OR a.patient_phone LIKE ?)";
    $stmt = $conn->prepare($sql);
    $searchParam = "%$search%";
    $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
    $stmt->execute();
    $patients = $stmt->get_result();
} else {
    $patients = $conn->query($sql);
}

// Additional styles for patient history
$additional_styles = "
<style>
    /* Patient History Specific Styles */
    .search-container {
        background: var(--white);
        border-radius: var(--border-radius-xl);
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        margin-bottom: 2rem;
    }

    .search-form {
        display: flex;
        gap: 1rem;
        align-items: end;
        flex-wrap: wrap;
    }

    .form-group {
        flex: 1;
        min-width: 200px;
    }

    .form-label {
        display: block;
        font-weight: 600;
        color: var(--gray-700);
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .form-input {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--border-radius);
        font-size: 0.9rem;
        transition: var(--transition);
        background: var(--white);
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: var(--border-radius);
        font-weight: 600;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        white-space: nowrap;
    }

    .btn-primary {
        background: var(--primary-color);
        color: var(--white);
    }

    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        color: var(--white);
        text-decoration: none;
    }

    .btn-secondary {
        background: var(--gray-200);
        color: var(--gray-700);
    }

    .btn-secondary:hover {
        background: var(--gray-300);
        color: var(--gray-800);
        text-decoration: none;
    }

    /* Patient Cards */
    .patients-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
    }

    .patient-card {
        background: var(--white);
        border-radius: var(--border-radius-xl);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .patient-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .patient-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }

    .patient-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .patient-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--white);
        font-size: 1.5rem;
        font-weight: 700;
    }

    .patient-info h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 0.25rem;
    }

    .patient-contact {
        font-size: 0.9rem;
        color: var(--gray-600);
    }

    .patient-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        backdrop-filter: blur(4px);
    }

    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: var(--white);
        border-radius: var(--border-radius-xl);
        width: 90%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow-xl);
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--gray-900);
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--gray-500);
        cursor: pointer;
        padding: 0.5rem;
        border-radius: var(--border-radius);
        transition: var(--transition);
    }

    .modal-close:hover {
        background: var(--gray-100);
        color: var(--gray-700);
    }

    .modal-body {
        padding: 1.5rem;
    }

    /* Medical Records */
    .medical-record {
        background: var(--gray-50);
        border-radius: var(--border-radius);
        padding: 1rem;
        margin-bottom: 1rem;
        border-left: 4px solid var(--primary-color);
    }

    .record-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }

    .record-date {
        font-weight: 600;
        color: var(--primary-color);
    }

    .record-service {
        font-size: 0.9rem;
        color: var(--gray-600);
    }

    .record-notes {
        color: var(--gray-700);
        line-height: 1.6;
        margin-top: 0.5rem;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 3rem;
        color: var(--gray-500);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: var(--gray-300);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .search-form {
            flex-direction: column;
            align-items: stretch;
        }

        .form-group {
            min-width: 100%;
        }

        .patients-grid {
            grid-template-columns: 1fr;
        }

        .patient-actions {
            flex-direction: column;
        }

        .modal-content {
            width: 95%;
            margin: 1rem;
        }
    }
</style>
";

// Include navbar
include 'includes/navbar.php';
?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Patient Medical History</h1>
            <p class="page-subtitle">View and manage patient medical records and treatment history</p>
        </div>

        <!-- Search Container -->
        <div class="search-container">
            <form method="GET" class="search-form">
                <div class="form-group">
                    <label for="search" class="form-label">
                        <i class="fas fa-search"></i>
                        Search Patients
                    </label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           class="form-input" 
                           placeholder="Search by name, email, or phone..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                    <a href="patient_history.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Patients Grid -->
        <?php if ($patients && $patients->num_rows > 0): ?>
            <div class="patients-grid">
                <?php while ($patient = $patients->fetch_assoc()): ?>
                    <div class="patient-card">
                        <div class="patient-header">
                            <div class="patient-avatar">
                                <?php echo strtoupper(substr($patient['patient_name'], 0, 1)); ?>
                            </div>
                            <div class="patient-info">
                                <h3><?php echo htmlspecialchars($patient['patient_name']); ?></h3>
                                <div class="patient-contact">
                                    <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['patient_email']); ?></div>
                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['patient_phone']); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="patient-actions">
                            <button onclick="viewMedicalHistory('<?php echo htmlspecialchars($patient['patient_email']); ?>', '<?php echo htmlspecialchars($patient['patient_name']); ?>')" 
                                    class="btn btn-primary btn-sm">
                                <i class="fas fa-history"></i>
                                View History
                            </button>
                            <button onclick="viewAppointments('<?php echo htmlspecialchars($patient['patient_email']); ?>', '<?php echo htmlspecialchars($patient['patient_name']); ?>')" 
                                    class="btn btn-secondary btn-sm">
                                <i class="fas fa-calendar"></i>
                                Appointments
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-friends"></i>
                <h3>No Patients Found</h3>
                <p>No confirmed patients match your search criteria.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Medical History Modal -->
    <div id="medicalHistoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Medical History</h2>
                <button class="modal-close" onclick="closeModal('medicalHistoryModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="medicalHistoryContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Appointments Modal -->
    <div id="appointmentsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Appointment History</h2>
                <button class="modal-close" onclick="closeModal('appointmentsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="appointmentsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function viewMedicalHistory(email, name) {
            document.getElementById('medicalHistoryModal').classList.add('show');
            document.getElementById('medicalHistoryContent').innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            
            fetch('get_medical_history.php?email=' + encodeURIComponent(email))
                .then(response => response.text())
                .then(data => {
                    document.getElementById('medicalHistoryContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('medicalHistoryContent').innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--danger-color);"><i class="fas fa-exclamation-triangle"></i> Error loading medical history</div>';
                });
        }

        function viewAppointments(email, name) {
            document.getElementById('appointmentsModal').classList.add('show');
            document.getElementById('appointmentsContent').innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            
            fetch('get_patient_appointments.php?email=' + encodeURIComponent(email))
                .then(response => response.text())
                .then(data => {
                    document.getElementById('appointmentsContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('appointmentsContent').innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--danger-color);"><i class="fas fa-exclamation-triangle"></i> Error loading appointments</div>';
                });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        });
    </script>

<?php include 'includes/footer.php'; ?>