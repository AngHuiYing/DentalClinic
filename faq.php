<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Clinic System</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }

        .navbar {
            background: #ffffff;
            padding: 15px 20px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            z-index: 1000;
        }

        .navbar ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .navbar ul li {
            margin: 0 15px;
        }

        .navbar ul li a {
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            color: #333;
            padding: 10px 15px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .navbar ul li a:hover {
            background: #e0e0e0;
            color: #007bff;
            transform: scale(1.1);
        }

        .faq-section {
            max-width: 800px;
            margin: 100px auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1);
        }

        .faq-section h2 {
            text-align: center;
            color: #333;
        }

        .faq-item {
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }

        .faq-question {
            font-weight: bold;
            cursor: pointer;
            font-size: 18px;
            color: #007bff;
            transition: 0.3s;
        }

        .faq-answer {
            display: none;
            margin-top: 10px;
            font-size: 16px;
            color: #555;
            line-height: 1.5;
        }

        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 50px;
        }
    </style>
</head>
<body>

    <!-- 导航栏 -->
    <nav class="navbar">
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="../Clinic_Appointment_System/patient/book_appointment.php">Book Appointment</a></li>
            <li><a href="../Clinic_Appointment_System/patient/book_appointment.php">Appointments</a></li>
            <li><a href="faq.php">FAQ</a></li>

            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'doctor') { ?>
                <li><a href="medical_records.php">Patient Records</a></li>
            <?php } ?>

            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin') { ?>
                <li><a href="user_management.php">User Management</a></li>
            <?php } ?>

            <?php if (isset($_SESSION['user_id'])) { ?>
                <li><a href="../Clinic_Appointment_System/patient/logout.php">Logout</a></li>
            <?php } else { ?>
                <li><a href="../Clinic_Appointment_System/patient/login.php">Login</a></li>
            <?php } ?>
        </ul>
    </nav>

    <!-- FAQ 区域 -->
    <section class="faq-section">
        <h2>Frequently Asked Questions</h2>

        <div class="faq-item">
            <div class="faq-question">1. What are the clinic's operating hours?</div>
            <div class="faq-answer">Our clinic is open from Monday to Friday, 9:00 AM to 6:00 PM, and on Saturdays from 9:00 AM to 1:00 PM. We are closed on Sundays and public holidays.</div>
        </div>

        <div class="faq-item">
            <div class="faq-question">2. Do I need to make an appointment, or can I walk in?</div>
            <div class="faq-answer">We encourage patients to make an appointment in advance to reduce waiting time. However, we do accept walk-in patients based on availability.</div>
        </div>

        <div class="faq-item">
            <div class="faq-question">3. How can I book an appointment?</div>
            <div class="faq-answer">You can book an appointment online through our website by clicking on the "Book Appointment" button, or you can call our reception at +123 456 7890.</div>
        </div>

        <div class="faq-item">
            <div class="faq-question">4. What should I bring for my appointment?</div>
            <div class="faq-answer">Please bring your identification card (IC/passport), medical records (if any), and your insurance details if applicable.</div>
        </div>

        <div class="faq-item">
            <div class="faq-question">5. Does the clinic accept insurance?</div>
            <div class="faq-answer">Yes, we accept most insurance plans. Please check with our reception for specific details regarding coverage.</div>
        </div>

        <div class="faq-item">
            <div class="faq-question">6. Can I cancel or reschedule my appointment?</div>
            <div class="faq-answer">Yes, you can cancel or reschedule your appointment by logging into your account on our website or calling our reception at least 24 hours in advance.</div>
        </div>
    </section>

    <!-- 页脚 -->
    <footer class="footer">
        <p>© 2025 Clinic System. All rights reserved.</p>
        <p>Contact us: contact@clinic.com | +123 456 7890</p>
    </footer>

    <script>
        // FAQ 展开/收起功能
        document.addEventListener("DOMContentLoaded", function () {
            let questions = document.querySelectorAll(".faq-question");

            questions.forEach(question => {
                question.addEventListener("click", function () {
                    let answer = this.nextElementSibling;
                    if (answer.style.display === "block") {
                        answer.style.display = "none";
                    } else {
                        answer.style.display = "block";
                    }
                });
            });
        });
    </script>

</body>
</html>
