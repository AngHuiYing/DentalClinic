<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Management System</title>
    <link rel="stylesheet" href="style.css">
</head>
<style>
    /* 通用样式 */
body {
    font-family: 'Arial', sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f4f4f4;
}

/* 导航栏 */
.navbar {
    background: #005a8d;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
    position: fixed;
    width: 100%;
    top: 0;
    z-index: 1000;
}

.navbar .logo {
    font-size: 20px;
    font-weight: bold;
}

.navbar nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
}

.navbar nav ul li {
    margin: 0 10px;
}

.navbar nav ul li a {
    text-decoration: none;
    color: white;
    font-weight: bold;
    transition: color 0.3s;
}

.navbar nav ul li a:hover {
    color: #ffd700;
}

/* Banner */
.banner {
    height: 200px;
    background: url('images/hospital_banner.jpg') no-repeat center center/cover;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: white;
    position: relative;
}

.banner .overlay {
    background: rgba(0, 90, 141, 0.7);
    padding: 20px;
    border-radius: 10px;
}

/* 交错式医院介绍 */
.hospital-info {
    padding: 50px 20px;
}

.info-box {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 40px;
}

.info-box.reverse {
    flex-direction: row-reverse;
}

.info-box img {
    width: 50%;  /* 调小图片大小 */
    max-width: 500px; /* 限制最大宽度 */
    border-radius: 10px;
    box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1);
    margin: 0 20px;
}

.info-text {
    width: 55%;
    padding: 20px;
}

.info-text h2 {
    color: #005a8d;
    margin-bottom: 10px;
}

.info-text p {
    font-size: 16px;
    color: #333;
    line-height: 1.6;
    margin-bottom: 10px;
}

/* 部门区域整体样式 */
.departments {
    text-align: center;
    padding: 50px 20px;
    background-color: #f9f9f9;
}

/* 标题样式 */
.departments h2 {
    font-size: 32px;
    margin-bottom: 20px;
    color: #2c3e50;
}

/* 卡片容器 */
.dept-container {
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
}

/* 每个科室卡片 */
.dept-card {
    width: 220px;
    height: 260px;
    background: #ffffff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    opacity: 0; /* 初始透明 */
    transform: translateY(30px); /* 初始位置稍微下移 */
    animation: fadeInUp 0.8s ease forwards;
}

/* 卡片动画延迟，让它们依次出现 */
.dept-card:nth-child(1) { animation-delay: 0.2s; }
.dept-card:nth-child(2) { animation-delay: 0.4s; }
.dept-card:nth-child(3) { animation-delay: 0.6s; }
.dept-card:nth-child(4) { animation-delay: 0.8s; }

/* 悬停时放大效果 */
.dept-card:hover {
    transform: scale(1.08);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
}

/* 图片样式 */
.dept-card img {
    width: 100%;
    height: 180px;
    object-fit: cover;
}

/* 科室名称 */
.dept-card h3 {
    font-size: 20px;
    margin-top: 10px;
    color: #34495e;
    font-weight: bold;
}

/* 淡入上移动画 */
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

/* 公告栏 */
.announcement {
    text-align: center;
    background: ;
    padding: 20px;
}

/* 医院环境 */
.gallery {
    text-align: center;
    padding: 50px 20px;
}

.gallery-container {
    display: flex;
    justify-content: center;
}

.gallery img {
    width: 200px;
    margin: 10px;
}

/* 地图 */
.map {
    text-align: center;
    padding: 50px 20px;
}

.map iframe {
    width: 80%;
    height: 400px;
    border-radius: 10px;
}

/* 页脚 */
.footer {
    background: #333;
    color: white;
    text-align: center;
    padding: 20px;
}

.contact-form {
    background: #f9f9f9;
    padding: 40px;
    text-align: center;
}

.contact-form h2 {
    color: #005a8d;
    margin-bottom: 20px;
}

.contact-form form {
    max-width: 500px;
    margin: auto;
}

.contact-form input,
.contact-form textarea {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 5px;
}

.contact-form button {
    background: #005a8d;
    color: white;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    border-radius: 5px;
}

.contact-form button:hover {
    background: #003f5c;
}
</style>
<body>

<!-- 导航栏 -->
<header class="navbar">
    <div class="logo">🌿 Green Life Hospital</div>
    <nav>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="book_appointment.php">Book Appointment</a></li>
            <li><a href="#departments">Departments</a></li>
            <li><a href="#about">About Us</a></li>
            <li><a href="#contact">Contact</a></li>
            <li><a href="../Hospital_Management_System/patient/login.php">Login</a></li>
        </ul>
    </nav>
</header>

<br>
<br>
<br>

<!-- 公告栏（卡片样式） -->
<section class="announcement">
    <div class="news-card">
        <marquee behavior="scroll" direction="left" style="font-size:14px;">Latest News: COVID-19 vaccination available now! Book your slot today.</marquee>
    </div>
</section>

<!-- 顶部 Banner -->
<section class="banner">
    <div class="overlay">
        <h1>Welcome to Green Life Hospital</h1>
        <p>Your health is our priority. Providing world-class healthcare services with advanced technology and experienced professionals.</p>
    </div>
</section>

<!-- 交错式医院介绍 -->
<section class="hospital-info" id="about">
    <div class="info-box">
        <img src="images/hospital_inside.jpg" alt="Hospital Interior">
        <div class="info-text">
            <h2>About Our Hospital</h2>
            <p>Green Life Hospital has been providing top-quality healthcare for over 20 years. We combine modern medical technology with compassionate patient care.
            Our hospital is dedicated to delivering comprehensive medical services with a focus on patient well-being. From emergency care to specialized treatments, we ensure excellence in healthcare.
            With a team of experienced doctors and advanced medical facilities, we provide the highest standard of treatment in a safe and comfortable environment.</p>
            <p>Our departments cover a wide range of specialties, including cardiology, orthopedics, neurology, pediatrics, and oncology. Each department is staffed with highly trained professionals committed to delivering the best care.
            We believe in patient-centered care, offering personalized treatment plans tailored to each individual's needs. Our multidisciplinary team works together to provide holistic medical solutions.
            Green Life Hospital is also a pioneer in medical research and education, collaborating with top institutions to drive innovation in healthcare. We continuously adopt the latest medical advancements to ensure our patients receive the best possible treatment.</p>
        </div>
    </div>

    <div class="info-box reverse">
        <div class="info-text">
            <h2>Advanced Medical Facilities</h2>
            <p>We are equipped with the latest technology and a team of highly trained professionals, ensuring the best treatment for our patients.
            Our hospital features state-of-the-art operation theaters, modern diagnostic centers, and specialized treatment units to handle a wide range of medical conditions.
            We continuously invest in cutting-edge medical equipment and digital healthcare solutions to improve diagnosis accuracy and patient recovery outcomes.</p>
        </div>
        <img src="images/medical_facility.jpg" alt="Medical Facility">
    </div>
</section>

<!-- 科室介绍（卡片样式） -->
<section class="departments" id="departments">
    <h2>Our Departments</h2>
    <div class="dept-container">
        <div class="dept-card" onclick="location.href='../Hospital_Management_System/doctor/doctors.php?dept=cardiology'">
            <img src="images/cardiology.jpg" alt="Cardiology">
            <h3>Cardiology</h3>
        </div>
        <div class="dept-card" onclick="location.href='../Hospital_Management_System/doctor/doctors.php?dept=orthopedics'">
            <img src="images/orthopedics.jpg" alt="Orthopedics">
            <h3>Orthopedics</h3>
        </div>
        <div class="dept-card" onclick="location.href='../Hospital_Management_System/doctor/doctors.php?dept=pediatrics'">
            <img src="images/pediatrics.jpg" alt="Pediatrics">
            <h3>Pediatrics</h3>
        </div>
        <div class="dept-card" onclick="location.href='../Hospital_Management_System/doctor/doctors.php?dept=neurology'">
            <img src="images/neurology.jpg" alt="Neurology">
            <h3>Neurology</h3>
        </div>
    </div>
</section>

<!-- 医院环境（图片展示） -->
<section class="gallery">
    <h2>Our Facilities</h2>
    <div class="gallery-container">
        <img src="images/hospital1.jpg" alt="Hospital Interior">
        <img src="images/hospital2.jpg" alt="Modern Equipment">
        <img src="images/hospital3.jpg" alt="Patient Care">
    </div>
</section>

<!-- 医院地图 -->
<section class="map">
    <h2>Find Us</h2>
    <iframe src="https://www.google.com/maps/embed?pb=YOUR_MAP_LINK_HERE" allowfullscreen></iframe>
</section>

<!-- Send Message Section -->
<section class="contact-form" id="contact">
    <h2>Send Message to Us</h2>
    <form action="send_message.php" method="POST">
        <input type="text" name="name" placeholder="Your Name" required>
        <input type="email" name="email" placeholder="Your Email" required>
        <input type="tel" name="tel" placeholder="Your Phone Number" required>
        <textarea name="message" placeholder="Your Message" required></textarea>
        <button type="submit">Send Message</button>
    </form>
</section>

<!-- 页脚 -->
<footer class="footer" id="contact">
    <p>© 2025 Green Life Hospital. All rights reserved.</p>
    <p>Contact: info@hospital.com | +123 456 7890</p>
</footer>

</body>
</html>
