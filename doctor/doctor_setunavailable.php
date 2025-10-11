<?php
session_start();
include "../db.php";

// 验证医生身份
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../doctor/login.php");
    exit;
}

// 获取医生的 doctor_id
$doctor_sql = "SELECT id FROM doctors WHERE user_id = ?";
$doctor_stmt = $conn->prepare($doctor_sql);
$doctor_stmt->bind_param("i", $_SESSION['user_id']);
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();

if ($doctor_result->num_rows > 0) {
    $doctor_row = $doctor_result->fetch_assoc();
    $doctor_id = $doctor_row['id'];
} else {
    // 处理错误，未找到对应医生记录
    header("Location: ../doctor/login.php");
    exit;
}

// 删除功能
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $delete_sql = "DELETE FROM unavailable_slots WHERE id = ? AND doctor_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $delete_id, $doctor_id); // 使用 doctor_id
    $delete_stmt->execute();
    header("Location: doctor_set_unavailable.php"); // 防止刷新重复提交
    exit;
}

// 设置不可用时间段
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date = $_POST['date'];
    $from_time = $_POST['from_time'];
    $to_time = $_POST['to_time'];

    // 只允许设置未来时间
    if (strtotime($date . ' ' . $to_time) <= time()) {
        echo "<script>alert('Please select a future time slot.');</script>";
    } elseif ($from_time >= $to_time) {
        echo "<script>alert('End time must be later than start time.');</script>";
    } else {
        // 检查是否存在重叠
        $check_sql = "SELECT * FROM unavailable_slots 
                      WHERE doctor_id = ? AND date = ? 
                      AND ((from_time < ? AND to_time > ?) 
                      OR (from_time < ? AND to_time > ?) 
                      OR (from_time >= ? AND to_time <= ?))";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("isssssss", $doctor_id, $date, $to_time, $to_time, $from_time, $from_time, $from_time, $to_time);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            echo "<script>alert('This time slot overlaps with an existing one.');</script>";
        } else {
            $sql = "INSERT INTO unavailable_slots (doctor_id, date, from_time, to_time) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $doctor_id, $date, $from_time, $to_time);
            if ($stmt->execute()) {
                echo "<script>alert('Unavailable time set successfully!');</script>";
            } else {
                echo "<script>alert('Failed to set unavailable time.');</script>";
            }
        }
    }
}

// 获取医生的不可用时间段
$unavailable_sql = "SELECT * FROM unavailable_slots WHERE doctor_id = ? ORDER BY date, from_time";
$unavailable_stmt = $conn->prepare($unavailable_sql);
$unavailable_stmt->bind_param("i", $doctor_id);
$unavailable_stmt->execute();
$unavailable_result = $unavailable_stmt->get_result();
$unavailable_slots = [];
while ($row = $unavailable_result->fetch_assoc()) {
    $unavailable_slots[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - Doctor Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <style>
        :root {
            /* Medical Professional Color Palette */
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary: #059669;
            --accent: #7c3aed;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            
            /* Sophisticated Grays */
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            
            /* Professional Shadows */
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px rgba(0, 0, 0, 0.25);
            
            /* Modern Border Radius */
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-2xl: 2rem;
            --radius-full: 9999px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Navigation Bar */
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            box-shadow: var(--shadow-lg);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .navbar-brand i {
            font-size: 2rem;
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 0.75rem 1rem !important;
            border-radius: var(--radius-lg);
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }

        .dropdown-menu {
            border: none;
            box-shadow: var(--shadow-xl);
            border-radius: var(--radius-lg);
            padding: 0.5rem;
        }

        .dropdown-item {
            border-radius: var(--radius);
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: var(--gray-100);
            transform: translateX(4px);
        }

        /* Main Content */
        .main-content {
            padding: 2rem 0;
        }

        /* Header Section */
        .page-header {
            background: white;
            border-radius: var(--radius-2xl);
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .page-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--gray-600);
            font-size: 1.1rem;
            margin: 0;
        }

        /* Form Section */
        .schedule-form {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-control,
        .form-select {
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-lg);
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--gray-50);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15);
            background: white;
        }

        .form-floating label {
            color: var(--gray-600);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: var(--radius-lg);
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Schedule Table */
        .schedule-section {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .schedule-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .schedule-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .table {
            margin: 0;
        }

        .table th {
            background: var(--gray-50);
            font-weight: 700;
            color: var(--gray-800);
            border: none;
            padding: 1.25rem 1.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 1.5rem;
            vertical-align: middle;
            border-color: var(--gray-200);
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: var(--gray-50);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            border: none;
            border-radius: var(--radius);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Search Box */
        .search-box {
            margin: 2rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-500);
        }

        .empty-icon {
            width: 5rem;
            height: 5rem;
            background: var(--gray-100);
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--gray-400);
            font-size: 2rem;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .empty-description {
            color: var(--gray-600);
            font-size: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem 0;
            }
            
            .page-header {
                padding: 2rem 1.5rem;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .schedule-form {
                padding: 1.5rem;
            }
            
            .schedule-header {
                padding: 1.5rem;
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .table th,
            .table td {
                padding: 1rem;
            }
        }

        /* Animation */
        .fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tooth"></i>
                Doctor Portal
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-1"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_appointments.php">
                            <i class="fas fa-calendar me-1"></i>
                            Appointments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="patient_records.php">
                            <i class="fas fa-file-medical me-1"></i>
                            Records
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="fas fa-envelope me-1"></i>
                            Messages
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-md me-1"></i>
                            Doctor
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="doctor_profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item active" href="doctor_setunavailable.php">
                                <i class="fas fa-clock me-2"></i>Schedule
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Header -->
            <section class="page-header fade-in">
                <h1 class="page-title">Schedule Management</h1>
                <p class="page-subtitle">Set your unavailable time slots to manage your practice schedule</p>
            </section>

            <!-- Schedule Form -->
            <section class="schedule-form fade-in">
                <h3 class="form-title">
                    <i class="fas fa-calendar-plus"></i>
                    Set Unavailable Time
                </h3>
                
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="date" name="date" placeholder="YYYY-MM-DD" required>
                                <label for="date">Select Date</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" id="from_time" name="from_time" required>
                                    <option value="">Select start time</option>
                                    <option value="09:00">09:00 AM</option>
                                    <option value="09:30">09:30 AM</option>
                                    <option value="10:00">10:00 AM</option>
                                    <option value="10:30">10:30 AM</option>
                                    <option value="11:00">11:00 AM</option>
                                    <option value="11:30">11:30 AM</option>
                                    <option value="14:00">02:00 PM</option>
                                    <option value="14:30">02:30 PM</option>
                                    <option value="15:00">03:00 PM</option>
                                    <option value="15:30">03:30 PM</option>
                                </select>
                                <label for="from_time">From Time</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" id="to_time" name="to_time" required>
                                    <option value="">Select end time</option>
                                    <option value="09:30">09:30 AM</option>
                                    <option value="10:00">10:00 AM</option>
                                    <option value="10:30">10:30 AM</option>
                                    <option value="11:00">11:00 AM</option>
                                    <option value="11:30">11:30 AM</option>
                                    <option value="14:30">02:30 PM</option>
                                    <option value="15:00">03:00 PM</option>
                                    <option value="15:30">03:30 PM</option>
                                    <option value="16:00">04:00 PM</option>
                                </select>
                                <label for="to_time">To Time</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Set Unavailable Time
                        </button>
                    </div>
                </form>
            </section>

            <!-- Schedule Table -->
            <section class="schedule-section fade-in">
                <div class="schedule-header">
                    <h3 class="schedule-title">
                        <i class="fas fa-list-alt"></i>
                        Your Unavailable Time Slots
                    </h3>
                    <span class="badge bg-light text-dark fs-6">
                        <?php echo count($unavailable_slots); ?> Slots
                    </span>
                </div>

                <div class="search-box">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="searchInput" placeholder="Search by date...">
                        <label for="searchInput">Search by date (e.g., 2024-01-15)</label>
                    </div>
                </div>
                
                <?php if (empty($unavailable_slots)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <h4 class="empty-title">No Unavailable Time Slots</h4>
                        <p class="empty-description">
                            You haven't set any unavailable time slots yet. Use the form above to block time slots when you're not available.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-calendar me-2"></i>Date</th>
                                    <th><i class="fas fa-clock me-2"></i>From</th>
                                    <th><i class="fas fa-clock me-2"></i>To</th>
                                    <th><i class="fas fa-cogs me-2"></i>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($unavailable_slots as $slot): ?>
                                <tr>
                                    <td>
                                        <strong><?= date('M j, Y', strtotime($slot['date'])) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= date('l', strtotime($slot['date'])) ?></small>
                                    </td>
                                    <td><?= date('g:i A', strtotime($slot['from_time'])) ?></td>
                                    <td><?= date('g:i A', strtotime($slot['to_time'])) ?></td>
                                    <td>
                                        <a class="btn btn-danger btn-sm" 
                                           href="?delete_id=<?= $slot['id'] ?>" 
                                           onclick="return confirm('Are you sure you want to delete this time slot?');">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
        // Initialize date picker
        flatpickr("#date", {
            dateFormat: "Y-m-d",
            minDate: "today",
            theme: "material_blue"
        });

        // Search functionality
        document.getElementById("searchInput").addEventListener("keyup", function () {
            let filter = this.value.toUpperCase();
            let table = document.querySelector("table");
            let trs = table.getElementsByTagName("tr");

            for (let i = 1; i < trs.length; i++) { // Start from 1 to skip header
                let td = trs[i].getElementsByTagName("td")[0]; // Check date column
                if (td) {
                    let txtValue = td.textContent || td.innerText;
                    trs[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
                }
            }
        });

        // Form validation
        document.querySelector("form").addEventListener("submit", function(e) {
            const from = document.getElementById("from_time").value;
            const to = document.getElementById("to_time").value;

            if (from >= to) {
                e.preventDefault();
                alert("End time must be later than start time.");
            }
        });

        // Add fade-in animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, observerOptions);

        // Observe all sections
        document.querySelectorAll('section').forEach(section => {
            observer.observe(section);
        });

        // Enhanced delete confirmation
        document.querySelectorAll('.btn-danger').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const row = this.closest('tr');
                const date = row.cells[0].textContent.trim().split('\n')[0];
                const fromTime = row.cells[1].textContent.trim();
                const toTime = row.cells[2].textContent.trim();
                
                if (!confirm(`Are you sure you want to delete this unavailable time slot?\n\nDate: ${date}\nTime: ${fromTime} - ${toTime}`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>