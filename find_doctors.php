<?php
session_start();
include 'includes/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find a Doctor - Health Care Clinic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #2c4964;
            --secondary-color: #1977cc;
            --accent-color: #3fbbc0;
        }

        body {
            font-family: "Open Sans", sans-serif;
            color: #444444;
            margin: 0;
            padding: 0;
            padding-top: 80px;
        }

        /* Navigation Bar */
        .main-navbar {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 15px 0;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 24px;
            padding: 0;
        }

        .nav-link {
            color: var(--primary-color) !important;
            font-weight: 500;
            padding: 10px 15px !important;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            color: var(--secondary-color) !important;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--secondary-color);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after {
            width: 70%;
        }

        .appointment-btn {
            background: var(--secondary-color);
            color: white !important;
            border-radius: 50px;
            padding: 10px 25px !important;
            white-space: nowrap;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        .appointment-btn:hover {
            background: var(--primary-color);
            transform: scale(1.05);
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(rgba(44, 73, 100, 0.8), rgba(44, 73, 100, 0.8)),
                        url('https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            padding: 80px 0;
            text-align: center;
            color: white;
            margin-bottom: 50px;
        }

        .page-header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        /* Doctor Cards Styling */
        .doctor-card {
            text-align: center;
            padding: 30px 20px;
            transition: all 0.3s ease;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            background-color: white;
        }

        .doctor-card:hover {
            background: rgba(25, 119, 204, 0.1);
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        }

        .doctor-image img {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border: 5px solid rgba(25, 119, 204, 0.2);
            padding: 5px;
            transition: all 0.3s ease;
        }

        .doctor-card:hover .doctor-image img {
            border-color: var(--secondary-color);
        }

        .doctor-card h4 {
            color: var(--primary-color);
            font-size: 22px;
            margin: 20px 0 10px;
        }

        .doctor-card .specialty {
            color: var(--secondary-color);
            font-weight: 600;
            margin-bottom: 15px;
        }

        .doctor-card .description {
            color: #666;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .doctor-card .btn-book {
            background-color: var(--secondary-color);
            color: white;
            padding: 8px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .doctor-card .btn-book:hover {
            background-color: var(--primary-color);
            transform: scale(1.05);
        }

        /* Doctor Search Section */
        .doctor-search-section {
            background-color: #f8f9fa;
            padding: 40px 0;
            margin-bottom: 50px;
            border-radius: 10px;
        }

        .search-form {
            max-width: 800px;
            margin: 0 auto;
        }

        .search-form .form-control {
            border-radius: 50px;
            padding: 12px 25px;
            height: auto;
            box-shadow: none;
            border: 1px solid #ddd;
        }

        .search-form .btn-search {
            background-color: var(--secondary-color);
            color: white;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            border: none;
        }

        .search-form .btn-search:hover {
            background-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>Find a Doctor</h1>
            <p>Connect with our experienced healthcare professionals</p>
        </div>
    </div>

    <!-- Doctor Search Section -->
    <section class="doctor-search-section">
        <div class="container">
            <h2 class="text-center mb-4" style="color: var(--primary-color);">Search for a Doctor</h2>
            <div class="search-form">
                <form action="" method="GET">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <select class="form-select" name="specialty">
                                <option value="">Any Specialty</option>
                                <option value="General Practitioner">General Practitioner</option>
                                <option value="Internal Medicine">Internal Medicine</option>
                                <option value="Pediatrician">Pediatrician</option>
                                <option value="Professional Specialist">Professional Specialist</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="name" placeholder="Doctor Name">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-search w-100">
                                <i class="bi bi-search me-2"></i> Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Our Doctors Section -->
    <section class="container mb-5">
        <h2 class="text-center mb-4" style="color: var(--primary-color);">Our Doctors</h2>
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="doctor-card">
                    <div class="doctor-image">
                        <img src="https://img.freepik.com/free-photo/woman-doctor-wearing-lab-coat-with-stethoscope-isolated_1303-29791.jpg" 
                             alt="Dr. Sarah Lee"
                             class="img-fluid rounded-circle mb-3">
                    </div>
                    <h4>Dr. Sarah Lee</h4>
                    <p class="specialty">General Practitioner</p>
                    <p class="description">Specializes in family medicine with over 10 years of experience in primary care.</p>
                    <a href="../Clinic_Appointment_System/patient/login.php" class="btn-book">Book Appointment</a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="doctor-card">
                    <div class="doctor-image">
                        <img src="https://img.freepik.com/free-photo/doctor-with-his-arms-crossed-white-background_1368-5790.jpg" 
                             alt="Dr. David Chen"
                             class="img-fluid rounded-circle mb-3">
                    </div>
                    <h4>Dr. David Chen</h4>
                    <p class="specialty">Internal Medicine</p>
                    <p class="description">Expert in chronic disease management and preventive medicine.</p>
                    <a href="../Clinic_Appointment_System/patient/login.php" class="btn-book">Book Appointment</a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="doctor-card">
                    <div class="doctor-image">
                        <img src="https://img.freepik.com/free-photo/female-doctor-hospital-with-stethoscope_23-2148827776.jpg" 
                             alt="Dr. Emily Wong"
                             class="img-fluid rounded-circle mb-3">
                    </div>
                    <h4>Dr. Emily Wong</h4>
                    <p class="specialty">Pediatrician</p>
                    <p class="description">Dedicated to providing comprehensive care for children and adolescents.</p>
                    <a href="../Clinic_Appointment_System/patient/login.php" class="btn-book">Book Appointment</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <!-- <?php include 'includes/footer.php'; ?> -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 