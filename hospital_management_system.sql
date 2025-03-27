-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 26, 2025 at 04:01 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hospital_management_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `email`, `name`, `created_at`, `last_login`) VALUES
(1, 'admin', '$2y$10$EKnGlwO9OClDZwN985u2WOTtSb0vMNpHPLDjBLEU4S9mvt8v0lZhq', 'admin@clinic.com', 'System Administrator', '2025-03-14 06:11:49', '2025-03-25 03:14:24');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('pending','approved','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `status`, `created_at`) VALUES
(1, 1, 2, '2025-03-21', '10:52:00', 'approved', '2025-03-21 03:36:48'),
(2, 1, 2, '2025-03-23', '12:14:00', 'pending', '2025-03-23 04:14:22'),
(3, 1, 5, '2025-03-26', '12:17:00', 'approved', '2025-03-23 04:16:44');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('sent','delivered','seen') DEFAULT 'sent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `sender_id`, `receiver_id`, `message`, `timestamp`, `status`) VALUES
(1, 1, 7, 'hi', '2025-03-25 02:13:11', 'sent'),
(2, 1, 1, 'This is a test', '2025-03-25 02:20:51', 'sent'),
(3, 1, 1, 'hhh', '2025-03-25 02:21:02', 'sent'),
(4, 1, 1, 'hi', '2025-03-25 02:29:38', 'sent'),
(5, 1, 1, 'test', '2025-03-25 02:44:09', 'sent'),
(6, 1, 1, 'ddd', '2025-03-25 02:52:32', 'sent');

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `image` varchar(255) NOT NULL,
  `specialty` varchar(100) NOT NULL,
  `bio` text NOT NULL,
  `experience` varchar(50) NOT NULL,
  `location` varchar(100) NOT NULL,
  `department` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `name`, `image`, `specialty`, `bio`, `experience`, `location`, `department`, `user_id`) VALUES
(1, 'John Doe', '../uploads/doctors/1742703672_heizou.9.jpg', 'Heart Specialist', 'Dr. John has 15 years of experience in cardiology.', '15', 'Room 301', 'cardiology', 7),
(2, 'Sarah Lee', '../uploads/doctors/1742702065_starrail.1.jpg', 'Cardiac Surgeon', 'Dr. Sarah is an expert in heart surgery with a 98% success rate.', '12', 'Room 205', 'cardiology', 4),
(3, 'William Brown', '../uploads/doctors/1742703602_ratio.1.jpg', 'Bone Specialist', 'Dr. William specializes in joint and bone disorders.', '10', 'Room 102', 'orthopedics', 6),
(5, 'ZhongLi', '../uploads/doctors/1742538217_zhongli.3.jpeg', 'Neurology', 'Dr.Ameng is good on Neurology.', '14', 'Room 11', 'Neurology', 5),
(6, 'hy', '../uploads/doctors/1742782322_kazuha.1.jpg', 'Bone Specialist', 'test only test', '2', 'Room 111', 'orthopedics', 9);

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `diagnosis` text NOT NULL,
  `prescription` text NOT NULL,
  `visit_date` date NOT NULL DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_records`
--

INSERT INTO `medical_records` (`id`, `patient_id`, `doctor_id`, `diagnosis`, `prescription`, `visit_date`) VALUES
(1, 1, 4, 'body health', 'drink more water', '2025-03-21');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `tel` varchar(20) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `name`, `email`, `tel`, `message`, `created_at`) VALUES
(1, 'Test', 'test@mail.com', '123456789', 'hhhhhhi', '2025-03-24 02:20:55'),
(2, 'Test111', 'test@mail.com', '0123456789', 'nsnfjkaw', '2025-03-25 01:06:53'),
(4, 'Leon', 'leon@mail.com', '0123456789', 'I need to ask for where the Room 111?', '2025-03-25 03:01:41'),
(5, 'Lily', 'lily@mail.com', '0123456789', 'Where are the Room?', '2025-03-25 03:05:23'),
(6, 'hy', 'huiyingsyzz@gmail.com', '0123456789', 'test', '2025-03-25 03:14:13');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 'Your appointment has been approved by the doctor.', 0, '2025-03-21 06:00:59'),
(2, 1, 'Your appointment has been approved by the doctor.', 0, '2025-03-21 06:01:13'),
(3, 1, 'Your appointment has been approved by the doctor.', 0, '2025-03-21 06:02:03'),
(4, 1, 'Your appointment has been approved by the doctor.', 0, '2025-03-23 04:39:11');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `created_at`) VALUES
(1, 'test@mail.com', '266bebd7ecbd0c9e0af9ffacf78f04e384ffbff1cfbfff1fccd19e1f2b07bcb8', '2025-03-24 03:49:11', '2025-03-24 01:49:11'),
(2, 'test@mail.com', '149b6b68319a66810070261fc9ccd51603c2d0434540132b00b06bf9ae43cfc3', '2025-03-24 03:50:35', '2025-03-24 01:50:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','doctor','patient') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'test', 'test@mail.com', '$2y$10$v5v8TKxzZoRx/ElSu7oq/.5OQX/2ALTzrgThmT3s2/7wPT0gC3Q3G', 'patient', '2025-03-21 03:35:10'),
(3, 'Admin', 'admin@example.com', '$2y$10$LMw09l8VctLiTvHSKGJiAOUe4AQH/h11d2CkSPKv.R4.2xk5wj4fC', 'admin', '2025-03-21 03:35:10'),
(4, 'Sarah Lee', 'sarah@mail.com', '$2y$10$gMZV/eH1gRSFHxLeKusH1eD/jv9H5kpdet54CxmIV90yayS/dUkAC', 'doctor', '2025-03-21 05:32:49'),
(5, 'ZhongLi', 'zhongli@mail.com', '$2y$10$rihF7UnM2wOfomYV4V2eseBYHQOUx9YYtgdNo7Z4QwpkeMzgJAlAm', 'doctor', '2025-03-21 06:22:39'),
(6, 'William', 'william@mail.com', '$2y$10$z2mZnS3MYHMeWvqlAKfaxuFs64mI4I/OaLEkdwZfWXVJdhyykhrAe', 'doctor', '2025-03-23 04:19:10'),
(7, 'John', 'john@mail.com', '$2y$10$YJ1RbdKUP8cWdFVxfNotX.8ipKk1ZmiGnw3a/sUzsbK7CbaG8S.8u', 'doctor', '2025-03-23 04:20:43'),
(9, 'hy', 'huiyingsyzz@gmail.com', '$2y$10$daYSjqmIpUvcNBUDDujSGuQlnQ1iLvYRS2Xyry48rrYTtQtPf1lw.', 'doctor', '2025-03-24 02:08:26');

-- --------------------------------------------------------

--
-- Table structure for table `user_logs`
--

CREATE TABLE `user_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_logs`
--

INSERT INTO `user_logs` (`id`, `admin_id`, `action`, `timestamp`) VALUES
(1, 1, 'Admin logged in: System Administrator', '2025-03-21 03:29:48'),
(2, 1, 'Admin logged in: System Administrator', '2025-03-21 03:39:24'),
(3, 1, 'Added new user: Dr. Sarah Lee (sarah@mail.com)', '2025-03-21 05:32:49'),
(4, 1, 'Admin logged in: System Administrator', '2025-03-21 06:06:17'),
(5, 1, 'Added new user: Ameng (ameng@mail.com)', '2025-03-21 06:22:39'),
(6, 1, 'Admin logged in: System Administrator', '2025-03-23 03:43:33'),
(7, 1, 'Admin logged in: System Administrator', '2025-03-23 04:18:30'),
(8, 1, 'Added new user: William (william@mail.com)', '2025-03-23 04:19:10'),
(9, 1, 'Added new user: John (john@mail.com)', '2025-03-23 04:20:43'),
(10, 1, 'Admin logged in: System Administrator', '2025-03-23 08:24:58'),
(11, 1, 'Admin logged in: System Administrator', '2025-03-24 01:33:19'),
(12, 1, 'Admin logged in: System Administrator', '2025-03-24 02:07:57'),
(13, 1, 'Added new user: hy (huiyingsyzz@gmail.com)', '2025-03-24 02:08:26'),
(14, 1, 'Admin logged in: System Administrator', '2025-03-24 02:10:48'),
(15, 1, 'Admin logged in: System Administrator', '2025-03-24 02:21:11'),
(16, 1, 'Admin logged in: System Administrator', '2025-03-24 02:40:11'),
(17, 1, 'Admin logged in: System Administrator', '2025-03-25 00:57:17'),
(18, 1, 'Admin logged in: System Administrator', '2025-03-25 01:07:16'),
(19, 1, 'Admin logged in: System Administrator', '2025-03-25 03:13:14'),
(20, 1, 'Admin logged in: System Administrator', '2025-03-25 03:14:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`);

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `fk_doctor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_records_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD CONSTRAINT `user_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
