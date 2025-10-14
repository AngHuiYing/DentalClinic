<?php
// Set timezone for Malaysia
date_default_timezone_set("Asia/Kuala_Lumpur");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green Life Dental Clinic</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script>
document.addEventListener("DOMContentLoaded", function() {
    const toggle = document.querySelector('.menu-toggle');
    const navMenu = document.querySelector('.navbar nav ul');

    toggle.addEventListener('click', function() {
        navMenu.classList.toggle('show');
    });

    // 點擊連結後收合 (手機用)
    document.querySelectorAll('.navbar nav ul li a').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                navMenu.classList.remove('show');
            }
        });
    });
});
</script>
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
    padding: 10px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
    position: fixed;
    width: 100%;
    top: 0;
    z-index: 1000;
    box-sizing: border-box;
}

.navbar .logo {
    font-size: 18px;
    font-weight: bold;
    white-space: nowrap;
}

.navbar nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
}

.navbar nav ul li {
    margin: 0 8px;
}

.navbar nav ul li a {
    text-decoration: none;
    color: white;
    font-weight: bold;
    transition: color 0.3s;
    font-size: 14px;
}

.navbar nav ul li a:hover {
    color: #ffd700;
}

/* Banner */
.banner {
    height: 300px;
    background: url('images/dental_banner2.jpg') no-repeat center center/cover;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: white;
    position: relative;
    margin-top: 60px;
}

.banner .overlay {
    background: rgba(0, 90, 141, 0.7);
    padding: 30px;
    border-radius: 10px;
    max-width: 90%;
}

.banner h1 {
    font-size: 2.5rem;
    margin-bottom: 15px;
    font-weight: 700;
}

.banner p {
    font-size: 1.1rem;
    line-height: 1.5;
    margin: 0;
}

/* 交错式医院介绍 */
.hospital-info {
    padding: 50px 20px;
}

.info-box {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 50px;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}

.info-box.reverse {
    flex-direction: row-reverse;
}

.info-box img {
    width: 45%;
    max-width: 500px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    margin: 0 25px;
    object-fit: cover;
    height: 300px;
}

.info-text {
    width: 50%;
    padding: 20px;
}

.info-text h2 {
    color: #005a8d;
    margin-bottom: 15px;
    font-size: 2rem;
    font-weight: 600;
}

.info-text p {
    font-size: 16px;
    color: #444;
    line-height: 1.7;
    margin-bottom: 15px;
}

/* 部门区域整体样式 */
.departments {
    text-align: center;
    padding: 60px 20px;
    background-color: #f9f9f9;
}

/* 标题样式 */
.departments h2 {
    font-size: 2.5rem;
    margin-bottom: 30px;
    color: #2c3e50;
    font-weight: 600;
}

/* 卡片容器 */
.dept-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    max-width: 1200px;
    margin: 0 auto;
    justify-items: center;
}

/* 每个科室卡片 */
.dept-card {
    width: 100%;
    max-width: 280px;
    background: #ffffff;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: all 0.3s ease;
    opacity: 0;
    transform: translateY(30px);
    animation: fadeInUp 0.8s ease forwards;
}

/* 卡片动画延迟，让它们依次出现 */
.dept-card:nth-child(1) { animation-delay: 0.2s; }
.dept-card:nth-child(2) { animation-delay: 0.4s; }
.dept-card:nth-child(3) { animation-delay: 0.6s; }
.dept-card:nth-child(4) { animation-delay: 0.8s; }

/* 悬停时放大效果 */
.dept-card:hover {
    transform: translateY(-10px) scale(1.03);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

/* 图片样式 */
.dept-card img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

/* 科室名称 */
.dept-card h3 {
    font-size: 18px;
    margin: 15px 0;
    color: #34495e;
    font-weight: 600;
    padding: 0 15px;
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
    background: #fff;
    padding: 15px 20px;
    margin-top: 60px;
}

.news-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    max-width: 1200px;
    margin: 0 auto;
}

/* 医院环境 */
.gallery {
    text-align: center;
    padding: 60px 20px;
    background: white;
}

.gallery h2 {
    font-size: 2.5rem;
    margin-bottom: 30px;
    color: #2c3e50;
    font-weight: 600;
}

.gallery-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

.gallery img {
    width: 100%;
    height: 250px;
    object-fit: cover;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.gallery img:hover {
    transform: scale(1.05);
}

/* 地图 */
.map {
    text-align: center;
    padding: 60px 20px;
    background: #f9f9f9;
}

.map h2 {
    font-size: 2.5rem;
    margin-bottom: 30px;
    color: #2c3e50;
    font-weight: 600;
}

.map iframe {
    width: 100%;
    max-width: 1000px;
    height: 400px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

/* 页脚 */
.footer {
    background: #2c3e50;
    color: white;
    text-align: center;
    padding: 40px 20px;
}

.footer p {
    margin: 5px 0;
    font-size: 14px;
}

.contact-form {
    background: #fff;
    padding: 60px 20px;
    text-align: center;
}

.contact-form h2 {
    color: #005a8d;
    margin-bottom: 30px;
    font-size: 2.5rem;
    font-weight: 600;
}

.contact-form form {
    max-width: 600px;
    margin: auto;
}

.contact-form input,
.contact-form textarea {
    width: 100%;
    padding: 15px;
    margin: 10px 0;
    border: 2px solid #e1e5e9;
    border-radius: 10px;
    font-size: 16px;
    transition: border-color 0.3s ease;
    box-sizing: border-box;
}

.contact-form input:focus,
.contact-form textarea:focus {
    outline: none;
    border-color: #005a8d;
}

.contact-form textarea {
    min-height: 120px;
    resize: vertical;
}

.contact-form button {
    background: #005a8d;
    color: white;
    border: none;
    padding: 15px 40px;
    cursor: pointer;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    transition: background 0.3s ease;
    margin-top: 10px;
}

.contact-form button:hover {
    background: #003f5c;
}
.menu-toggle {
    display: none;
    cursor: pointer;
    font-size: 22px;
    color: #ffd700;
}

/* 響應式設計 - 手機適配 */
@media (max-width: 768px) {
    body {
        font-size: 14px;
    }
    
    /* Banner 手机优化 */
    .banner {
        height: 250px;
        margin-top: 55px;
    }
    
    .banner .overlay {
        padding: 20px 15px;
        max-width: 95%;
    }
    
    .banner h1 {
        font-size: 1.8rem;
        margin-bottom: 10px;
    }
    
    .banner p {
        font-size: 0.9rem;
    }
    
    /* 公告栏手机优化 */
    .announcement {
        padding: 10px 15px;
    }
    
    .news-card {
        padding: 12px;
        font-size: 13px;
    }

    /* 信息介绍手机优化 */
    .hospital-info {
        padding: 30px 15px;
    }
    
    .info-box {
        flex-direction: column;
        text-align: center;
        margin-bottom: 40px;
    }

    .info-box.reverse {
        flex-direction: column;
    }

    .info-box img {
        width: 100%;
        max-width: 100%;
        margin: 0 0 20px 0;
        height: 200px;
    }

    .info-text {
        width: 100%;
        padding: 0 10px;
    }

    .info-text h2 {
        font-size: 1.5rem;
        margin-bottom: 15px;
    }

    .info-text p {
        font-size: 14px;
        line-height: 1.6;
    }

    /* 部门卡片手机优化 */
    .departments {
        padding: 40px 15px;
    }
    
    .departments h2 {
        font-size: 1.8rem;
        margin-bottom: 25px;
    }

    .dept-container {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        padding: 0 10px;
    }

    .dept-card {
        max-width: 100%;
    }
    
    .dept-card img {
        height: 150px;
    }
    
    .dept-card h3 {
        font-size: 16px;
        margin: 10px 0;
    }

    /* 环境图片手机优化 */
    .gallery {
        padding: 40px 15px;
    }
    
    .gallery h2 {
        font-size: 1.8rem;
        margin-bottom: 25px;
    }

    .gallery-container {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .gallery img {
        height: 200px;
    }

    /* 地图手机优化 */
    .map {
        padding: 40px 15px;
    }
    
    .map h2 {
        font-size: 1.8rem;
        margin-bottom: 25px;
    }

    .map iframe {
        height: 250px;
    }
    
    /* 联系表单手机优化 */
    .contact-form {
        padding: 40px 15px;
    }
    
    .contact-form h2 {
        font-size: 1.8rem;
        margin-bottom: 25px;
    }
    
    .contact-form form {
        max-width: 100%;
    }
    
    .contact-form input,
    .contact-form textarea {
        padding: 12px;
        font-size: 14px;
    }
    
    .contact-form button {
        padding: 12px 30px;
        font-size: 14px;
    }
    
    /* 页脚手机优化 */
    .footer {
        padding: 30px 15px;
    }
    
    .footer p {
        font-size: 13px;
    }
}

/* 響應式設計 - Navbar */
@media (max-width: 768px) {
    .navbar {
        padding: 8px 15px;
        position: fixed;
    }
    
    .navbar .logo {
        font-size: 16px;
    }

    .menu-toggle {
        display: block;
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 1100;
    }

    .navbar nav ul {
        display: none;
        flex-direction: column;
        align-items: center;
        position: fixed;
        width: 100%;
        background: #005a8d;
        top: 50px;
        left: 0;
        padding: 20px 0;
        box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.2);
        z-index: 1000;
    }

    .navbar nav ul.show {
        display: flex !important;
    }

    .navbar nav ul li {
        margin: 8px 0;
        width: 100%;
        text-align: center;
    }

    .navbar nav ul li a {
        display: block;
        width: 100%;
        padding: 12px 20px;
        color: white;
        font-size: 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .navbar nav ul li:last-child a {
        border-bottom: none;
    }
}

/* 小屏幕优化 */
@media (max-width: 480px) {
    .banner h1 {
        font-size: 1.5rem;
    }
    
    .banner p {
        font-size: 0.85rem;
    }
    
    .departments h2,
    .gallery h2,
    .map h2,
    .contact-form h2 {
        font-size: 1.5rem;
    }
    
    .info-text h2 {
        font-size: 1.3rem;
    }
    
    .dept-container {
        grid-template-columns: 1fr;
        padding: 0 5px;
    }
    
    .contact-form {
        padding: 30px 10px;
    }
}
</style>
<body>

<!-- 导航栏 -->
<!-- <header class="navbar">
    <a href="index.php" class="logo" style="text-decoration: none; color: inherit;"><i class="bi bi-heart-pulse-fill"></i> Green Life Dental Clinic</a>
    <span class="menu-toggle">&#9776;</span> burger 按鈕 -->
    <!-- <nav>
        <ul>
            <li><a href="index.php"><i class="bi bi-house"> Home</i></a></li>
            <li><a href="all_doctors.php"><i class="bi bi-people"> Our Doctors</i></a></li>
            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'patient'): ?> -->
                <!-- 患者已登录时的菜单 -->
                <!-- <li><a href="patient/book_appointment.php"><i class="bi bi-calendar"> Book Appointment</i></a></li>
                <li><a href="#departments"><i class="bi bi-briefcase"> Services</i></a></li>
                <li><a href="#about"><i class="bi bi-info-circle"> About Us</i></a></li>
                <li><a href="#contact"><i class="bi bi-envelope"> Contact</i></a></li>
                <li><a href="patient/dashboard.php"><i class="bi bi-speedometer2"> Dashboard</i></a></li>
            <?php else: ?> -->
                <!-- 未登录用户的菜单 -->
                <!-- <li><a href="book_appointment.php"><i class="bi bi-calendar"> Book Appointment</i></a></li>
                <li><a href="#departments"><i class="bi bi-briefcase"> Services</i></a></li>
                <li><a href="#about"><i class="bi bi-info-circle"> About Us</i></a></li>
                <li><a href="#contact"><i class="bi bi-envelope"> Contact</i></a></li>
                <li><a href="patient/login.php"><i class="bi bi-box-arrow-in-right"> Login</i></a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header> -->
<?php include 'includes/navbar.php'; ?>

<!-- 公告栏（卡片样式） -->
<section class="announcement">
    <div class="news-card">
        <marquee behavior="scroll" direction="left" style="font-size:14px;">
            Latest Update: Free dental check-up every Friday afternoon! Book your slot now.
        </marquee>
    </div>
</section>

<!-- 顶部 Banner -->
<section class="banner">
    <div class="overlay">
        <h1>Welcome to Green Life Dental Clinic</h1>
        <p>Your smile is our priority. Providing professional dental care with modern technology and experienced dentists.</p>
    </div>
</section>

<!-- 診所介紹 -->
<section class="hospital-info" id="about">
    <div class="info-box">
        <img src="images/dental_inner.webp" alt="Dental Clinic Interior">
        <div class="info-text">
            <h2>About Our Clinic</h2>
            <p>Green Life Dental Clinic has been dedicated to improving oral health for the community for over 15 years.  
        We provide a comprehensive range of dental services, including preventive care, restorative treatments, orthodontics, cosmetic dentistry, and oral surgery.  
        Our mission is to make dental care accessible, comfortable, and affordable for everyone, ensuring that each patient receives the highest quality of care.</p>
        <p>We believe that every smile tells a story. That’s why our team of experienced dentists, hygienists, and staff are committed to creating a welcoming environment where patients feel relaxed and cared for.  
        Whether it's your child's first dental visit, routine check-ups for the whole family, or advanced treatments such as dental implants and braces, we provide personalized solutions tailored to individual needs.</p>
        </div>
    </div>

    <div class="info-box reverse">
        <div class="info-text">
            <h2>Modern Dental Facilities</h2>
            <p>Our clinic is equipped with state-of-the-art facilities designed to make every visit smooth, safe, and efficient.  
        From advanced digital X-rays and 3D scanning to painless anesthesia delivery systems and modern sterilization technologies, we ensure that every procedure is carried out with precision and the highest safety standards.</p>
        <p>We continuously update our technology and techniques in line with the latest advancements in dentistry.  
        Patients can benefit from minimally invasive treatments, computer-aided smile design, and same-day dental restorations using CAD/CAM systems.  
        With comfortable dental chairs, relaxing treatment rooms, and strict infection-control protocols, we aim to provide not only excellent dental results but also a stress-free experience for our patients.</p>
        </div>
        <img src="images/dental_clinic_facility.jpg" alt="Dental Facility">
    </div>
</section>

<!-- 牙科服務 -->
<section class="departments" id="departments">
    <h2>Our Dental Services</h2>
    <div class="dept-container">
        <div class="dept-card" onclick="location.href='../Dental_Clinic/all_doctors.php'">
            <img src="images/general_dentistry.jpg" alt="General Dentistry">
            <h3>General Dentistry</h3>
        </div>
        <div class="dept-card" onclick="location.href='../Dental_Clinic/all_doctors.php'">
            <img src="images/orthodontics.jpg" alt="Orthodontics">
            <h3>Braces & Orthodontics</h3>
        </div>
        <div class="dept-card" onclick="location.href='../Dental_Clinic/all_doctors.php'">
            <img src="images/cosmetic_dentistry.jpg" alt="Cosmetic Dentistry">
            <h3>Cosmetic Dentistry</h3>
        </div>
        <div class="dept-card" onclick="location.href='../Dental_Clinic/all_doctors.php'">
            <img src="images/dental_implants.jpg" alt="Dental Implants">
            <h3>Dental Implants</h3>
        </div>
    </div>
</section>

<!-- 環境圖片 -->
<section class="gallery">
    <h2>Our Clinic Environment</h2>
    <div class="gallery-container">
        <img src="images/dental_clinic1.jpg" alt="Dental Chair">
        <img src="images/dental_clinic2.jpg" alt="Modern Equipment">
        <img src="images/dental_clinic3.jpg" alt="Patient Care">
    </div>
</section>

<!-- 地圖 -->
<section class="map">
    <h2>Find Us</h2>
    <iframe src="https://www.google.com/maps/embed?pb=YOUR_MAP_LINK_HERE" allowfullscreen></iframe>
</section>

<!-- 聯絡表單 -->
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

<!-- 頁腳 -->
<footer class="footer" id="contact">
    <p>© 2025 Green Smile Dental Clinic. All rights reserved.</p>
    <p>Contact: info@greensmile.com | +123 456 7890</p>
    <p style="margin-top: 10px; font-size: 12px;">
        Opening Hours: Mon-Fri 9:00 AM - 6:00 PM | Sat 9:00 AM - 2:00 PM
    </p>
    
    <!-- 隐藏的员工入口 - 只有员工知道 -->
    <p style="margin-top: 25px; font-size: 9px; opacity: 0.2;">
        <a href="doctor/login.php" style="color: #bdc3c7; text-decoration: none; transition: opacity 0.3s; margin: 0 10px;" 
           onmouseover="this.style.opacity='1'" 
           onmouseout="this.style.opacity='0.2'">
           Doctor
        </a>
        <span style="color: #95a5a6;">|</span>
        <a href="admin/login.php" style="color: #bdc3c7; text-decoration: none; transition: opacity 0.3s; margin: 0 10px;" 
           onmouseover="this.style.opacity='1'" 
           onmouseout="this.style.opacity='0.2'">
           Admin
        </a>
    </p>
</footer>

</body>
</html>