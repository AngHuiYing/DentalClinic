<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctor_name = $_SESSION['user_name'] ?? 'Doctor';

// 处理搜索功能
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT * FROM messages";
if (!empty($search)) {
    $sql .= " WHERE name LIKE '%$search%' OR email LIKE '%$search%' OR tel LIKE '%$search%' OR message LIKE '%$search%'";
}
$sql .= " ORDER BY created_at DESC";

$result = $conn->query($sql);
$num_rows = $result->num_rows; // 获取结果行数

// Get statistics
$today_messages = 0;
$total_messages = $num_rows;
$unread_messages = $num_rows; // Assuming all are unread for now

if ($result->num_rows > 0) {
    $result->data_seek(0); // Reset pointer
    while ($row = $result->fetch_assoc()) {
        if (date('Y-m-d', strtotime($row['created_at'])) === date('Y-m-d')) {
            $today_messages++;
        }
    }
    $result->data_seek(0); // Reset pointer again for display
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Doctor Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
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

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-info h3 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
            line-height: 1;
        }

        .stat-info p {
            color: var(--gray-600);
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
        }

        .stat-icon {
            width: 4rem;
            height: 4rem;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
        .stat-icon.success { background: linear-gradient(135deg, var(--success), #059669); }
        .stat-icon.warning { background: linear-gradient(135deg, var(--warning), #d97706); }

        /* Search Section */
        .search-section {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        .search-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-lg);
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--gray-50);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15);
            background: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: var(--radius-lg);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Messages Grid */
        .messages-section {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .messages-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .messages-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .messages-grid {
            padding: 2rem;
            display: grid;
            gap: 1.5rem;
        }

        .message-card {
            background: var(--gray-50);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .message-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background: white;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .message-sender {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sender-avatar {
            width: 3rem;
            height: 3rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .sender-info h5 {
            color: var(--gray-800);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .sender-contact {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .message-date {
            color: var(--gray-500);
            font-size: 0.85rem;
            text-align: right;
        }

        .message-content {
            color: var(--gray-700);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .message-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .btn-reply {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            border: none;
            border-radius: var(--radius);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-reply:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .search-section {
                padding: 1.5rem;
            }
            
            .messages-header {
                padding: 1.5rem;
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .messages-grid {
                padding: 1.5rem;
                gap: 1rem;
            }
            
            .message-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .message-date {
                text-align: left;
            }
            
            .message-actions {
                justify-content: flex-start;
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
                        <a class="nav-link active" href="messages.php">
                            <i class="fas fa-envelope me-1"></i>
                            Messages
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-md me-1"></i>
                            Dr. <?php echo htmlspecialchars($doctor_name); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="doctor_profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="doctor_setunavailable.php">
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
                <h1 class="page-title">Patient Messages</h1>
                <p class="page-subtitle">View and respond to messages from patients and website visitors</p>
            </section>

            <!-- Statistics -->
            <section class="fade-in">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $total_messages; ?></h3>
                                <p>Total Messages</p>
                            </div>
                            <div class="stat-icon primary">
                                <i class="fas fa-envelope"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $today_messages; ?></h3>
                                <p>Today's Messages</p>
                            </div>
                            <div class="stat-icon success">
                                <i class="fas fa-envelope-open"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <h3><?php echo $unread_messages; ?></h3>
                                <p>Pending Responses</p>
                            </div>
                            <div class="stat-icon warning">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Search Section -->
            <section class="search-section fade-in">
                <h3 class="search-title">
                    <i class="fas fa-search"></i>
                    Search Messages
                </h3>
                
                <form method="GET" class="row g-3">
                    <div class="col-md-9">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name, email, phone, or message content..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </div>
                </form>
            </section>

            <!-- Messages Section -->
            <section class="messages-section fade-in">
                <div class="messages-header">
                    <h3 class="messages-title">
                        <i class="fas fa-inbox"></i>
                        Patient Messages
                    </h3>
                    <span class="badge bg-light text-dark fs-6">
                        <?php echo $num_rows; ?> Messages
                    </span>
                </div>
                
                <?php if ($num_rows > 0) { ?>
                    <div class="messages-grid">
                        <?php while ($row = $result->fetch_assoc()) { ?>
                            <div class="message-card">
                                <div class="message-header">
                                    <div class="message-sender">
                                        <div class="sender-avatar">
                                            <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
                                        </div>
                                        <div class="sender-info">
                                            <h5><?php echo htmlspecialchars($row['name']); ?></h5>
                                            <div class="sender-contact">
                                                <i class="fas fa-envelope me-1"></i>
                                                <?php echo htmlspecialchars($row['email']); ?>
                                                <?php if (!empty($row['tel'])): ?>
                                                    <br>
                                                    <i class="fas fa-phone me-1"></i>
                                                    <?php echo htmlspecialchars($row['tel']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="message-date">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
                                        <br>
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('g:i A', strtotime($row['created_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="message-content">
                                    <?php echo htmlspecialchars($row['message']); ?>
                                </div>
                                
                                <div class="message-actions">
                                    <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>?subject=Reply from Green Life Dental Clinic" 
                                       class="btn-reply">
                                        <i class="fas fa-reply"></i>
                                        Reply by Email
                                    </a>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <h4 class="empty-title">No Messages Found</h4>
                        <p class="empty-description">
                            <?php if (!empty($search)): ?>
                                No messages found matching your search criteria. Try adjusting your search terms.
                            <?php else: ?>
                                You don't have any messages from patients yet. Messages will appear here when patients contact you through the website.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php } ?>
            </section>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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

        // Add hover effects to message cards
        document.querySelectorAll('.message-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(-2px)';
            });
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>