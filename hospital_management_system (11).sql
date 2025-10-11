-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 10, 2025 at 02:54 PM
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
(1, 'admin', '$2y$10$EKnGlwO9OClDZwN985u2WOTtSb0vMNpHPLDjBLEU4S9mvt8v0lZhq', 'admin@clinic.com', 'System Administrator', '2025-03-14 06:11:49', '2025-10-10 08:30:20');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('pending','confirmed','cancelled_by_patient','cancelled_by_admin','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `patient_name` varchar(100) DEFAULT NULL,
  `patient_phone` varchar(20) DEFAULT NULL,
  `patient_email` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `queue_number` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `status`, `created_at`, `patient_name`, `patient_phone`, `patient_email`, `message`, `queue_number`) VALUES
(42, NULL, 2, '2025-09-17', '11:30:00', 'cancelled_by_admin', '2025-09-17 01:19:39', 'Test', '0123456789', 'test@mail.com', NULL, NULL),
(43, NULL, 2, '2025-09-19', '14:00:00', 'completed', '2025-09-17 01:48:12', 'test', '0123456789', 'test@mail.com', NULL, NULL),
(44, NULL, 2, '2025-09-19', '14:30:00', 'completed', '2025-09-17 01:50:28', 'testdatetime', '0123456789', 'testdt@mail.com', NULL, NULL),
(45, NULL, 3, '2025-09-21', '15:00:00', 'confirmed', '2025-09-18 00:37:47', 'testnodt', '0123456789', 'testnodt@mail.com', NULL, NULL),
(46, NULL, 5, '2025-09-28', '15:00:00', 'confirmed', '2025-09-18 02:00:43', 'testmessage', '0123456789', 'testmessg@mail.com', 'just testing', NULL),
(47, NULL, 6, '2025-09-18', '11:30:00', 'confirmed', '2025-09-18 02:11:31', 'test', '0123456789', 'test@mail.com', NULL, NULL),
(48, NULL, 3, '2025-09-18', '14:30:00', 'confirmed', '2025-09-18 02:35:35', 'Ang Hui Ying', '0123456789', 'testdt3@mail.com', NULL, NULL),
(49, NULL, 1, '2025-09-20', '10:00:00', 'cancelled_by_patient', '2025-09-18 03:16:42', 'testdttt', '0123456789', 'testdttt@mail.com', 'test', NULL),
(51, 1, 3, '2025-09-24', '15:00:00', 'cancelled_by_patient', '2025-09-22 01:47:51', NULL, NULL, NULL, NULL, NULL),
(52, NULL, 3, '2025-09-22', '14:30:00', 'confirmed', '2025-09-22 02:25:22', 'test', '0123456789', 'test@mail.com', NULL, NULL),
(53, 1, 5, '2025-09-22', '16:00:00', 'confirmed', '2025-09-22 02:32:00', 'test', '0123456789', 'test@mail.com', NULL, NULL),
(57, NULL, 6, '2025-09-28', '11:00:00', 'confirmed', '2025-09-22 06:34:47', 'Nortrick', '0134567892', 'huiying9318@gmail.com', 'test', NULL),
(58, NULL, 5, '2025-09-25', '11:30:00', 'confirmed', '2025-09-22 07:12:28', 'Nortrick', '0134567892', 'huiying9318@gmail.com', NULL, NULL),
(59, 16, 3, '2025-09-23', '17:00:00', 'confirmed', '2025-09-23 07:47:11', 'Nortrick', '0134567892', 'huiying9318@gmail.com', NULL, NULL),
(60, NULL, 5, '2025-09-26', '15:00:00', 'confirmed', '2025-09-23 08:35:38', 'Nortrick', '0134567892', 'huiying9318@gmail.com', NULL, 1),
(61, NULL, 2, '2025-09-24', '11:30:00', 'completed', '2025-09-24 00:30:46', 'Riflori', '0198765432', 'yumeru5120@gmail.com', 'test', 1),
(62, 16, 2, '2025-09-24', '15:00:00', 'completed', '2025-09-24 00:53:59', 'Nortrick', '0134567892', 'huiying9318@gmail.com', 'ii', 2),
(63, 16, 2, '2025-09-24', '17:00:00', 'completed', '2025-09-24 00:57:56', 'Nortrick', '0134567892', 'huiying9318@gmail.com', 'test123', 3),
(64, 16, 1, '2025-09-24', '10:30:00', 'completed', '2025-09-24 01:36:23', 'Nortrick', '0134567892', 'huiying9318@gmail.com', 'test', 1),
(65, NULL, 1, '2025-09-24', '14:00:00', 'confirmed', '2025-09-24 01:56:55', 'Jocelyn', '0124787535', 'jocelynooi808@gmail.com', 'test', 2),
(66, NULL, 2, '2025-09-25', '09:30:00', 'cancelled_by_patient', '2025-09-25 00:35:42', 'Nortrick', '0134567892', 'huiying9318@gmail.com', NULL, 1),
(67, NULL, 6, '2025-09-26', '15:00:00', 'confirmed', '2025-09-26 00:14:16', 'Riflori', '0198765432', 'yumeru5120@gmail.com', NULL, 1),
(68, NULL, 3, '2025-09-29', '15:00:00', 'cancelled_by_patient', '2025-09-28 05:10:41', 'Testlast', '0198765432', 'fong9318@gmail.com', 'test', 1),
(69, 21, 2, '2025-09-28', '16:30:00', 'confirmed', '2025-09-28 06:04:10', 'Testlast', '0198765432', 'fong9318@gmail.com', NULL, 1),
(70, NULL, 1, '2025-10-01', '11:00:00', 'confirmed', '2025-09-28 10:51:09', 'Testlast', '0198765432', 'fong9318@gmail.com', 'testad', 1),
(71, 16, 1, '2025-09-30', '16:00:00', 'completed', '2025-09-30 01:04:21', 'Nortrick', '0134567892', 'huiying9318@gmail.com', 'test', 1),
(72, 16, NULL, '2025-09-30', '15:30:00', 'confirmed', '2025-09-30 05:00:12', 'Nortrick', '0134567892', 'huiying9318@gmail.com', 'abc', 1),
(73, NULL, 2, '2025-09-30', '16:30:00', 'confirmed', '2025-09-30 06:08:04', 'Nortrick', '0134567892', 'huiying9318@gmail.com', NULL, 1),
(74, NULL, 5, '2025-10-01', '11:30:00', 'confirmed', '2025-10-01 00:30:33', 'Nortrick', '0134567892', 'huiying9318@gmail.com', 'test dc option', 1),
(75, 25, 2, '2025-10-04', '14:00:00', 'completed', '2025-10-01 08:10:37', 'testing', '601120452650', 'testing@mail.com', NULL, 1),
(76, 17, 2, '2025-10-03', '16:30:00', 'confirmed', '2025-10-03 05:28:29', 'Riflori', '0198765432', 'yumeru5120@gmail.com', 'test bill', 1),
(77, 16, 1, '2025-10-06', '14:00:00', 'confirmed', '2025-10-06 01:36:55', 'Nortrick', '0134567892', 'huiying9318@gmail.com', NULL, 1),
(78, NULL, 2, '2025-10-06', '15:30:00', 'confirmed', '2025-10-06 06:42:32', 'Nortrick', '0134567892', 'huiying9318@gmail.com', NULL, 1),
(79, 16, 5, '2025-10-07', '15:30:00', 'confirmed', '2025-10-06 07:55:41', 'Nortrick', '0134567892', 'huiying9318@gmail.com', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `id` int(11) NOT NULL,
  `patient_name` varchar(100) NOT NULL,
  `patient_email` varchar(100) NOT NULL,
  `patient_phone` varchar(20) NOT NULL,
  `service` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`id`, `patient_name`, `patient_email`, `patient_phone`, `service`, `amount`, `payment_method`, `created_at`) VALUES
(4, 'Testdate', 'testdt3@mail.com', '0123456789', 'test', 66.00, 'Insurance', '2025-09-18 08:12:16'),
(5, 'Nortrick', 'huiying9318@gmail.com', '0134567892', 'testt', 84.00, 'Cash', '2025-09-22 07:53:07'),
(6, 'Riflori', 'yumeru5120@gmail.com', '0198765432', '2223', 80.00, 'Credit Card', '2025-09-24 01:10:56'),
(7, 'Nortrick', 'huiying9318@gmail.com', '0134567892', 'Teeth Cleaning,Filling Teeth', 100.00, 'Insurance', '2025-09-25 01:20:48'),
(12, 'Riflori', 'yumeru5120@gmail.com', '0198765432', 'Teeth Cleaning,Filling Teeth', 100.00, 'Cash', '2025-09-25 02:07:42'),
(14, 'Testlast', 'fong9318@gmail.com', '0198765432', 'Orthodontics Treatment', 3500.00, 'Cash', '2025-09-28 10:55:31'),
(15, 'Nortrick', 'huiying9318@gmail.com', '0134567892', 'Teeth Cleaning,Orthodontics Treatment', 3560.00, 'Cash', '2025-10-01 02:19:44'),
(19, 'Nortrick', 'huiying9318@gmail.com', '0134567892', 'Teeth Cleaning,Filling Teeth,Orthodontics Treatment', 3600.00, 'Cash', '2025-10-06 23:55:02');

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
(6, 1, 1, 'ddd', '2025-03-25 02:52:32', 'sent'),
(16, 5, 1, 'Are you allergic to anything?', '2025-03-27 06:19:31', 'sent'),
(17, 1, 5, 'No', '2025-03-27 06:20:10', 'sent'),
(18, 1, 9, 'hi', '2025-03-28 05:10:01', 'sent');

-- --------------------------------------------------------

--
-- Table structure for table `clinic_settings`
--

CREATE TABLE `clinic_settings` (
  `id` int(11) NOT NULL,
  `clinic_name` varchar(255) NOT NULL,
  `clinic_address` text NOT NULL,
  `contact_email` varchar(255) NOT NULL,
  `contact_phone` varchar(50) NOT NULL,
  `working_hours` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_settings`
--

INSERT INTO `clinic_settings` (`id`, `clinic_name`, `clinic_address`, `contact_email`, `contact_phone`, `working_hours`, `description`, `updated_at`) VALUES
(1, 'Green Life Dental Clinic', '96, Jalan DDC, Taman Green Life, 13400 Pulau Pinang, Butterworth', 'clinic@example.com', '0123456789', 'Mon-Sat: 9AM-6PM', 'Default clinic description.', '2025-09-27 16:19:57');

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
(1, 'John Doe', 'uploads/doctors/1759371773_WhatsApp Image 2025-10-02 at 10.18.32 AM (1).jpeg', 'General Dentistry', 'Dr. John has 15 years of experience in cardiology.', '15', 'Room 301', 'general', 7),
(2, 'Sarah Lee', 'uploads/doctors/1759371758_WhatsApp Image 2025-10-02 at 10.18.34 AM.jpeg', 'General Dentistry', 'Dr. Sarah is an expert in heart surgery with a 98% success rate.', '12', 'Room 205', 'general', 4),
(3, 'William Brown', 'uploads/doctors/1759371783_WhatsApp Image 2025-10-02 at 10.18.33 AM (1).jpeg', 'Braces & Orthodontics', 'Dr. William specializes in joint and bone disorders.', '10', 'Room 102', 'orthodontics', 6),
(5, 'ZhongLi', 'uploads/doctors/1759371724_WhatsApp Image 2025-10-02 at 10.18.34 AM (3).jpeg', 'Dental Implants', 'Dr.ZhongLi is good on Neurology.', '14', 'Room 11', 'implant', 5),
(6, 'hy', 'uploads/doctors/1759371709_WhatsApp Image 2025-10-02 at 10.17.28 AM.jpeg', 'Cosmetic Dentistry', 'test only test', '2', 'Room 111', 'cosmetic', 9),
(10, 'Azwa', 'uploads/doctors/1759371680_WhatsApp Image 2025-10-02 at 10.18.34 AM (2).jpeg', 'Implant', 'test', '10', 'Room 982', 'implant', 26),
(11, 'Shirney', 'uploads/doctors/1759456200_WhatsApp Image 2025-10-02 at 10.18.34 AM (1).jpeg', 'Dental Implants', 'Professional', '11', 'Room 77', 'implant', 24);

-- --------------------------------------------------------

--
-- Table structure for table `doctor_reviews`
--

CREATE TABLE `doctor_reviews` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `patient_name` varchar(100) NOT NULL,
  `patient_email` varchar(255) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_title` varchar(200) DEFAULT NULL,
  `review_text` text DEFAULT NULL,
  `is_anonymous` tinyint(1) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 1,
  `rejection_reason` text DEFAULT NULL,
  `admin_reply` text DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_reviews`
--

INSERT INTO `doctor_reviews` (`id`, `doctor_id`, `patient_id`, `patient_name`, `patient_email`, `appointment_id`, `rating`, `review_title`, `review_text`, `is_anonymous`, `is_approved`, `rejection_reason`, `admin_reply`, `admin_id`, `replied_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'test', 'test@example.com', NULL, 5, 'Excellent Care and Service', 'Dr. John Doe provided exceptional care during my visit. Very professional and knowledgeable. Highly recommend!', 0, 1, NULL, 'We’re so happy to know you felt well taken care of. Thank you for trusting us with your dental health.', 1, '2025-10-02 02:01:48', '2025-10-01 06:15:51', '2025-10-02 02:01:48'),
(2, 1, 12, 'LeoIzu', 'leo@example.com', NULL, 4, 'Very Good Experience', 'Great doctor with good communication skills. The treatment was effective and I felt comfortable throughout.', 0, 1, NULL, NULL, NULL, NULL, '2025-10-01 06:15:51', '2025-10-01 06:15:51'),
(3, 2, 16, 'Anonymous Patient', 'nortrick@example.com', NULL, 5, 'Outstanding Professional', 'Dr. Sarah Lee is amazing! She took time to explain everything and made me feel at ease. Definitely coming back.', 1, 1, NULL, NULL, NULL, NULL, '2025-10-01 06:15:51', '2025-10-01 06:15:51'),
(4, 2, 1, 'test', 'test@example.com', NULL, 4, 'Good Service', 'Professional service and clean facility. Dr. Lee was thorough in her examination.', 0, 1, NULL, NULL, NULL, NULL, '2025-10-01 06:15:51', '2025-10-01 06:15:51'),
(5, 3, 12, 'LeoIzu', 'leo@example.com', NULL, 3, 'Average Experience', 'The treatment was okay, but I felt the consultation could have been more detailed.', 0, 1, NULL, 'Thank you for your feedback. We are working to improve our consultation process and will take your comments into consideration.', 1, '2025-10-02 01:42:36', '2025-10-01 06:15:51', '2025-10-02 01:42:36'),
(6, 3, 16, 'Nortrick', 'huiying9318@gmail.com', 59, 4, 'Professional and Caring, with Room for Improvement', 'The dentist was very professional and gentle throughout the treatment, which made me feel comfortable. The clinic is clean and the staff are friendly, giving an overall positive experience. However, the waiting time could be improved, and communication could be a bit more efficient. Overall, I am satisfied and would recommend this clinic.', 0, 1, NULL, NULL, NULL, NULL, '2025-10-01 07:09:54', '2025-10-01 07:09:54'),
(10, 2, 16, 'Nortrick', 'huiying9318@gmail.com', 62, 4, 'Very Good', 'Good experience!', 0, 1, NULL, NULL, NULL, NULL, '2025-10-02 05:52:19', '2025-10-02 05:52:19');

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `patient_email` varchar(255) DEFAULT NULL,
  `chief_complaint` text DEFAULT NULL,
  `doctor_id` int(11) NOT NULL,
  `diagnosis` text NOT NULL,
  `treatment_plan` text DEFAULT NULL,
  `prescription` text NOT NULL,
  `progress_notes` text DEFAULT NULL,
  `visit_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `report_generated` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_records`
--

INSERT INTO `medical_records` (`id`, `patient_id`, `patient_email`, `chief_complaint`, `doctor_id`, `diagnosis`, `treatment_plan`, `prescription`, `progress_notes`, `visit_date`, `created_at`, `report_generated`) VALUES
(4, NULL, 'huiying9318@gmail.com', 'test', 5, 'just test', 'meet frederrick', 'testing', 'testtt', '2025-09-22', '2025-09-22 00:00:00', 1),
(7, NULL, 'huiying9318@gmail.com', 'ttestagainn', 6, 'test', 'jg', 'hj', 'ddt', '2025-09-21', '2025-09-22 16:32:55', 1),
(14, NULL, 'huiying9318@gmail.com', 'testserviceused', 1, 'djja', 'wbh', 'wh', 'wjj', '2025-09-24', '2025-09-24 15:01:17', 1),
(43, NULL, 'yumeru5120@gmail.com', '12', 2, '12', '12', '21', '21', '2025-09-24', '2025-09-24 16:36:55', 1),
(45, NULL, 'huiying9318@gmail.com', 'test', 2, 'tets', 'h', 'nn', 'n', '2025-09-25', '2025-09-25 09:20:10', 1),
(48, NULL, 'fong9318@gmail.com', 'testnewserv', 2, 'test', 'test', 'justtestt', 'tets', '2025-09-28', '2025-09-28 16:52:16', 1),
(49, NULL, 'fong9318@gmail.com', 'testnewservaddd', 2, 'add', 'new', 'serv', 'testing', '2025-09-28', '2025-09-28 18:54:42', 1),
(52, NULL, 'huiying9318@gmail.com', 'testing ah', 5, 'new content', 'testing it', 'new setting', 'new function', '2025-10-01', '2025-10-01 10:10:35', 1),
(54, NULL, 'huiying9318@gmail.com', 'jbh', 2, 'asd', 'dasda', 'asda', 'asda', '2025-10-06', '2025-10-06 14:44:25', 1);

-- --------------------------------------------------------

--
-- Table structure for table `medical_record_services`
--

CREATE TABLE `medical_record_services` (
  `id` int(11) NOT NULL,
  `medical_record_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_record_services`
--

INSERT INTO `medical_record_services` (`id`, `medical_record_id`, `service_id`) VALUES
(9, 43, 2),
(10, 43, 1),
(13, 45, 1),
(14, 45, 2),
(17, 48, 2),
(18, 48, 1),
(19, 49, 4),
(24, 52, 1),
(25, 52, 4),
(29, 54, 1),
(30, 54, 4),
(32, 54, 2);

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
(6, 'hy', 'huiyingsyzz@gmail.com', '0123456789', 'test', '2025-03-25 03:14:13'),
(7, 'test9', 'test9@mail.com', '0123456789', 'ttsttest', '2025-09-18 08:16:38'),
(8, 'Jocelyn', 'jocelynooi808@gmail.com', '0124787535', 'testing', '2025-09-24 01:25:09'),
(9, 'testlast', 'huiying9318@gmail.com', '0123456789', 'testtt', '2025-09-27 08:56:20'),
(10, 'Nortrick', 'huiying9318@gmail.com', '0134567892', 'testttt289', '2025-09-28 07:02:38');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `patient_email` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `patient_email`, `message`, `is_read`, `created_at`) VALUES
(1, 1, NULL, 'Your appointment has been approved by the doctor.', 0, '2025-03-21 06:00:59'),
(2, 1, NULL, 'Your appointment has been approved by the doctor.', 0, '2025-03-21 06:01:13'),
(3, 1, NULL, 'Your appointment has been approved by the doctor.', 0, '2025-03-21 06:02:03'),
(4, 1, NULL, 'Your appointment has been approved by the doctor.', 0, '2025-03-23 04:39:11'),
(8, 1, NULL, 'Your appointment has been approved by the doctor.', 0, '2025-09-17 06:20:19'),
(9, 1, NULL, 'Your appointment has been approved by the doctor.', 0, '2025-09-17 06:26:42'),
(10, 1, NULL, 'Your appointment has been approved by the doctor.', 0, '2025-09-17 06:35:12');

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
(2, 'test@mail.com', '149b6b68319a66810070261fc9ccd51603c2d0434540132b00b06bf9ae43cfc3', '2025-03-24 03:50:35', '2025-03-24 01:50:35'),
(15, 'jocelynooi808@gmail.com', '3f99a5a6bf99dbc77ab1147ec9b0964cf107e81ecfe62b0ea4470f02b170d182', '2025-09-24 10:56:55', '2025-09-24 01:56:55'),
(16, 'huiying9318@gmail.com', '59c9814475736457865892fc45060e20098c905e0afd630323c011b68bcc074a', '2025-09-26 15:25:34', '2025-09-26 06:25:34'),
(17, 'huiying9318@gmail.com', 'd82755dd143260782f3f818443b0bc7616affdc7c12dbd8822b4fc828ab46934', '2025-09-27 19:03:04', '2025-09-27 10:03:04'),
(19, 'shirneyang@gmail.com', '3e4b7c2cf6edbad1d6ae14cb7a2e5eb92c62f8e9952c79b3b19f9558d1c3ef6a', '2025-09-28 20:12:53', '2025-09-28 11:12:53'),
(20, 'shirneyang@gmail.coms', 'ad353d43281d11a7e8d45f3eda0365b1fc14ac0fa8dec3404f0e9a3db002373f', '2025-09-29 00:31:47', '2025-09-28 15:31:47'),
(21, 'shirneyang@gmail.com', '8bed2b37fd4b13aa4568326a52c6a187670ef2d8cd1857579db47ea58b48b382', '2025-09-29 00:37:50', '2025-09-28 15:37:50'),
(22, 'huiyingsyzz@gmail.com', 'ce163fb6c162a29c7b92710672e624fb544dcad22f0a13fde062199cc9b315d8', '2025-09-30 11:04:52', '2025-09-30 02:04:52'),
(23, 'nurhwny0515@gmail.com', '74cd912dd8c9c2cc0d97411be875a1f95ea01798588f323cbe40094c8eccb83a', '2025-10-01 17:18:53', '2025-10-01 08:18:53'),
(24, 'test567@mail.com', 'c2d5377aabab4984d559dc7aa0f945078f877ce25d053ebbec73beab1f36f564', '2025-10-07 11:39:39', '2025-10-07 02:39:39');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `price`) VALUES
(1, 'Teeth Cleaning', 60.00),
(2, 'Filling Teeth', 40.00),
(4, 'Orthodontics Treatment', 3500.00);

-- --------------------------------------------------------

--
-- Table structure for table `unavailable_slots`
--

CREATE TABLE `unavailable_slots` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `from_time` time NOT NULL,
  `to_time` time NOT NULL,
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unavailable_slots`
--

INSERT INTO `unavailable_slots` (`id`, `doctor_id`, `date`, `from_time`, `to_time`, `reason`) VALUES
(6, 5, '2025-04-16', '12:00:00', '16:00:00', NULL),
(9, 5, '2025-04-22', '14:00:00', '15:00:00', NULL),
(10, 3, '2025-04-17', '12:00:00', '15:00:00', NULL),
(11, 3, '2025-04-30', '08:00:00', '20:00:00', NULL),
(12, 6, '2025-04-10', '13:00:00', '17:00:00', NULL),
(13, 2, '2025-09-18', '12:47:00', '13:46:00', NULL),
(14, 2, '2025-09-19', '14:00:00', '16:00:00', NULL),
(15, 2, '2025-09-30', '11:00:00', '15:00:00', NULL),
(17, 5, '2025-09-26', '09:30:00', '10:30:00', NULL),
(18, 2, '2025-10-01', '10:00:00', '15:30:00', NULL),
(19, 1, '2025-09-30', '09:30:00', '11:30:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','doctor','patient') NOT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `gender`, `date_of_birth`, `created_at`) VALUES
(1, 'test', 'test@mail.com', '0123456789', '$2y$10$RLOvG.w3xd8zfLol07G/8esBgxI04pyBwS8DdPctBVO1cFD1aeygS', 'patient', 'male', '2025-10-06', '2025-03-21 03:35:10'),
(3, 'Admin', 'admin@clinic.com', NULL, '$2y$10$LMw09l8VctLiTvHSKGJiAOUe4AQH/h11d2CkSPKv.R4.2xk5wj4fC', 'admin', NULL, NULL, '2025-03-21 03:35:10'),
(4, 'Sarah Lee', 'sarah@mail.com', NULL, '$2y$10$gMZV/eH1gRSFHxLeKusH1eD/jv9H5kpdet54CxmIV90yayS/dUkAC', 'doctor', NULL, NULL, '2025-03-21 05:32:49'),
(5, 'ZhongLi', 'zhongli@mail.com', NULL, '$2y$10$rihF7UnM2wOfomYV4V2eseBYHQOUx9YYtgdNo7Z4QwpkeMzgJAlAm', 'doctor', NULL, NULL, '2025-03-21 06:22:39'),
(6, 'William', 'william@mail.com', NULL, '$2y$10$z2mZnS3MYHMeWvqlAKfaxuFs64mI4I/OaLEkdwZfWXVJdhyykhrAe', 'doctor', NULL, NULL, '2025-03-23 04:19:10'),
(7, 'John', 'john@mail.com', NULL, '$2y$10$YJ1RbdKUP8cWdFVxfNotX.8ipKk1ZmiGnw3a/sUzsbK7CbaG8S.8u', 'doctor', NULL, NULL, '2025-03-23 04:20:43'),
(9, 'hy', 'huiyingsyzz@gmail.com', NULL, '$2y$10$IcEvzvnSfNF.ZDTIfsx7m.RQDddsA7sVC9ypEGr4I8TVF09WItvDG', 'doctor', NULL, NULL, '2025-03-24 02:08:26'),
(12, 'LeoIzu', 'leoizu@mail.com', '0123456789', '$2y$10$KZYNQNN456jYTvpfFWKdqOxBYyt3oEtA.iJTYEqQpKzM2QKdTKx8i', 'patient', NULL, NULL, '2025-09-22 03:35:56'),
(16, 'Nortrick', 'huiying9318@gmail.com', '0134567892', '$2y$10$2.twOzc2VawUHN5aXRmhYehqnEB.lwU7T9NXntDT9Dyeb4rM.rtLy', 'patient', 'female', '2005-12-05', '2025-09-22 06:34:47'),
(17, 'Riflori', 'yumeru5120@gmail.com', '0198765432', '$2y$10$vw1kEdtg5SMCJuSM7K/JRezaOUqXpY1HD3o2lINFaNcwryqHGSbaC', 'patient', 'male', '2005-05-12', '2025-09-22 07:37:30'),
(18, 'Jocelyn', 'jocelynooi808@gmail.com', '0124787535', '$2y$10$cUpGQWyfWQ7szjZ5MRXoyeDUkMfALDeEnTbyMEMU1ObGBwTZV240m', 'patient', NULL, NULL, '2025-09-24 01:56:55'),
(19, 'Ang Hui Ying', 'alice@mail.com', '01120452650', '$2y$10$AYpCzIngHyJur9zn5K6hceoEEXfnSqWjtB7R/PYtN9Ls2iUG5SDvW', 'patient', NULL, NULL, '2025-09-26 01:20:56'),
(20, 'Test User', 'testuser@example.com', NULL, '$2y$10$zYtpBEzlno8SdQfa/VTbJ.Z/JpmVjPiDYhTA23VtmwFyXRMZL3KaS', 'patient', NULL, NULL, '2025-09-26 01:49:16'),
(21, 'Testlast', 'fong9318@gmail.com', '0198765432', '$2y$10$espaSF0UkeDAkBA4o.TLyeHVcgRem4n33lqfbZzX8eHQ8q3dAzQZW', 'patient', NULL, NULL, '2025-09-28 05:10:41'),
(24, 'Shirney', 'shirneyang@gmail.com', '0123456789', '$2y$10$JbKN9zmnxTrhEFEPjEPa7enqmAQ30IdMwBsSBUOHgyES1hiB1f1um', 'doctor', NULL, NULL, '2025-09-28 15:37:50'),
(25, 'testing', 'testing@mail.com', '601120452650', '$2y$10$yS/jC6VFJTqY8HdzcJphYugzNc4iUdByGsZIj.krLMR9MCJrvaWaq', 'patient', 'male', '2021-01-01', '2025-10-01 08:08:12'),
(26, 'Azwa', 'nurhwny0515@gmail.com', '0123456789', '$2y$10$XFnzyvY.Y.kraE6mNY44LugYPOE/dO.fi70eR73MFfRZzZHCCaS.e', 'doctor', NULL, NULL, '2025-10-01 08:18:53'),
(27, 'testing dc', 'test567@mail.com', '0123456789', '$2y$10$H0rLS/ONC6AkgYMVgVdhw.9CFJv3QX5omOPQIjjIW.7mt.1juvNk6', 'doctor', NULL, NULL, '2025-10-07 02:39:39');

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
(20, 1, 'Admin logged in: System Administrator', '2025-03-25 03:14:24'),
(21, 1, 'Admin logged in: System Administrator', '2025-03-28 05:35:50'),
(22, 1, 'Admin logged in: System Administrator', '2025-04-03 00:55:48'),
(23, 1, 'Admin logged in: System Administrator', '2025-04-03 01:07:09'),
(24, 1, 'Admin logged in: System Administrator', '2025-04-07 05:36:17'),
(25, 1, 'Updated appointment ID: 12 to status: approved', '2025-04-07 05:37:05'),
(26, 1, 'Updated appointment ID: 7 to status: approved', '2025-04-07 05:37:13'),
(27, 1, 'Updated appointment ID: 9 to status: approved', '2025-04-07 05:37:22'),
(28, 1, 'Updated appointment ID: 10 to status: cancelled', '2025-04-07 05:37:37'),
(29, 1, 'Admin logged in: System Administrator', '2025-04-09 03:32:59'),
(30, 1, 'Admin logged in: System Administrator', '2025-04-09 03:46:21'),
(31, 1, 'Admin logged in: System Administrator', '2025-04-09 06:15:17'),
(32, 1, 'Admin logged in: System Administrator', '2025-04-09 06:30:33'),
(33, 1, 'Admin logged in: System Administrator', '2025-09-17 05:52:40'),
(34, 1, 'Admin logged in: System Administrator', '2025-09-17 06:07:24'),
(35, 1, 'Updated appointment ID: 42 to status: pending', '2025-09-17 06:07:33'),
(36, 1, 'Admin logged in: System Administrator', '2025-09-17 06:39:50'),
(37, 1, 'Updated appointment ID: 43 to status: approved', '2025-09-17 06:40:16'),
(38, 1, 'Admin logged in: System Administrator', '2025-09-17 07:03:15'),
(39, 1, 'Deleted user with ID: 11', '2025-09-17 07:03:43'),
(40, 1, 'Deleted user with ID: 10', '2025-09-17 07:03:47'),
(41, 1, 'Admin logged in: System Administrator', '2025-09-17 08:05:02'),
(42, 1, 'Admin logged in: System Administrator', '2025-09-18 00:28:18'),
(43, 1, 'Admin logged in: System Administrator', '2025-09-18 00:38:00'),
(44, 1, 'Assigned doctor ID: 3 to appointment ID: 45', '2025-09-18 00:38:55'),
(45, 1, 'Admin logged in: System Administrator', '2025-09-18 00:40:35'),
(46, 1, 'Admin logged in: System Administrator', '2025-09-18 01:58:03'),
(47, 1, 'Admin logged in: System Administrator', '2025-09-18 02:00:56'),
(48, 1, 'Assigned doctor ID: 5 to appointment ID: 46', '2025-09-18 02:01:10'),
(49, 1, 'Admin logged in: System Administrator', '2025-09-18 02:15:49'),
(50, 1, 'Admin logged in: System Administrator', '2025-09-18 02:33:20'),
(51, 1, 'Admin logged in: System Administrator', '2025-09-18 03:26:57'),
(52, 1, 'Admin logged in: System Administrator', '2025-09-18 03:37:29'),
(53, 1, 'Admin logged in: System Administrator', '2025-09-18 03:44:24'),
(54, 1, 'Updated appointment ID: 45 to status: approved', '2025-09-18 03:44:57'),
(55, 1, 'Admin logged in: System Administrator', '2025-09-18 05:57:03'),
(56, 1, 'Admin logged in: System Administrator', '2025-09-18 06:32:33'),
(57, 1, 'Admin logged in: System Administrator', '2025-09-18 07:10:08'),
(58, 1, 'Admin logged in: System Administrator', '2025-09-18 07:12:34'),
(59, 1, 'Admin logged in: System Administrator', '2025-09-18 08:08:01'),
(60, 1, 'Admin logged in: System Administrator', '2025-09-18 08:17:03'),
(61, 1, 'Updated appointment ID: 44 to status: approved', '2025-09-18 08:20:47'),
(62, 1, 'Updated appointment ID: 42 to status: cancelled', '2025-09-18 08:21:07'),
(63, 1, 'Updated appointment ID: 47 to status: ', '2025-09-18 08:28:12'),
(64, 1, 'Updated appointment ID: 47 to status: approved', '2025-09-18 08:28:36'),
(65, 1, 'Updated appointment ID: 49 to status: ', '2025-09-18 08:31:24'),
(66, 1, 'Updated appointment ID: 49 to status: approved', '2025-09-18 08:31:55'),
(67, 1, 'Assigned doctor ID: 3 to appointment ID: 48', '2025-09-18 08:39:24'),
(68, 1, 'Updated appointment ID: 48 to status: ', '2025-09-18 08:41:39'),
(69, 1, 'Updated appointment ID: 48 to status: approved', '2025-09-19 00:31:51'),
(70, 1, 'Admin logged in: System Administrator', '2025-09-19 02:35:36'),
(71, 1, 'Admin logged in: System Administrator', '2025-09-22 01:23:30'),
(72, 1, 'Updated appointment ID: 49 to status: cancelled_by_patient', '2025-09-22 01:23:52'),
(73, 1, 'Updated appointment ID: 50 to status: confirmed', '2025-09-22 01:40:58'),
(74, 1, 'Admin logged in: System Administrator', '2025-09-22 01:48:42'),
(75, 1, 'Admin logged in: System Administrator', '2025-09-22 02:38:49'),
(76, 1, 'Assigned doctor ID: 3 to appointment ID: 52', '2025-09-22 02:39:09'),
(77, 1, 'Admin logged in: System Administrator', '2025-09-22 02:56:43'),
(78, 1, 'Admin logged in: System Administrator', '2025-09-22 03:06:28'),
(79, 1, 'Admin logged in: System Administrator', '2025-09-22 06:50:20'),
(80, 1, 'Updated appointment ID: 52 to status: confirmed', '2025-09-22 07:08:23'),
(81, 1, 'Admin logged in: System Administrator', '2025-09-22 07:14:56'),
(82, 1, 'Added new user: Riflori (yumeru5120@gmail.com)', '2025-09-22 07:37:35'),
(83, 1, 'Admin logged in: System Administrator', '2025-09-22 07:40:08'),
(84, 1, 'Updated appointment ID: 58 to status: confirmed', '2025-09-22 07:40:45'),
(85, 1, 'Admin logged in: System Administrator', '2025-09-22 07:47:29'),
(86, 1, 'Assigned doctor ID: 6 to appointment ID: 57', '2025-09-22 07:52:00'),
(87, 1, 'Updated appointment ID: 57 to status: confirmed', '2025-09-22 07:52:06'),
(88, 1, 'Admin logged in: System Administrator', '2025-09-22 07:55:15'),
(89, 1, 'Admin logged in: System Administrator', '2025-09-23 01:58:09'),
(90, 1, 'Admin logged in: System Administrator', '2025-09-23 02:13:33'),
(91, 1, 'Admin logged in: System Administrator', '2025-09-23 07:48:05'),
(92, 1, 'Admin logged in: System Administrator', '2025-09-23 08:19:24'),
(93, 1, 'Admin logged in: System Administrator', '2025-09-23 08:41:20'),
(94, 1, 'Admin logged in: System Administrator', '2025-09-24 00:59:08'),
(95, 1, 'Admin logged in: System Administrator', '2025-09-24 01:00:37'),
(96, 1, 'Admin logged in: System Administrator', '2025-09-24 01:47:51'),
(97, 1, 'Admin logged in: System Administrator', '2025-09-24 02:07:39'),
(98, 1, 'Admin logged in: System Administrator', '2025-09-24 02:19:01'),
(99, 1, 'Admin logged in: System Administrator', '2025-09-24 03:15:17'),
(100, 1, 'Admin logged in: System Administrator', '2025-09-24 03:30:17'),
(101, 1, 'Admin logged in: System Administrator', '2025-09-24 05:19:19'),
(102, 1, 'Admin logged in: System Administrator', '2025-09-24 05:47:29'),
(103, 1, 'Admin logged in: System Administrator', '2025-09-24 06:28:30'),
(104, 1, 'Admin logged in: System Administrator', '2025-09-24 06:36:36'),
(105, 1, 'Admin logged in: System Administrator', '2025-09-24 06:54:37'),
(106, 1, 'Admin logged in: System Administrator', '2025-09-24 07:02:20'),
(107, 1, 'Admin logged in: System Administrator', '2025-09-24 07:08:37'),
(108, 1, 'Admin logged in: System Administrator', '2025-09-24 07:24:15'),
(109, 1, 'Admin logged in: System Administrator', '2025-09-24 07:40:37'),
(110, 1, 'Admin logged in: System Administrator', '2025-09-24 07:42:00'),
(111, 1, 'Admin logged in: System Administrator', '2025-09-24 07:52:37'),
(112, 1, 'Admin logged in: System Administrator', '2025-09-24 07:57:26'),
(113, 1, 'Admin logged in: System Administrator', '2025-09-24 08:01:04'),
(114, 1, 'Admin logged in: System Administrator', '2025-09-24 08:11:52'),
(115, 1, 'Admin logged in: System Administrator', '2025-09-24 08:38:08'),
(116, 1, 'Admin logged in: System Administrator', '2025-09-25 00:24:25'),
(117, 1, 'Admin logged in: System Administrator', '2025-09-25 00:43:20'),
(118, 1, 'Admin logged in: System Administrator', '2025-09-25 01:19:14'),
(119, 1, 'Admin logged in: System Administrator', '2025-09-25 01:20:37'),
(120, 1, 'Admin logged in: System Administrator', '2025-09-25 01:35:54'),
(121, 1, 'Admin logged in: System Administrator', '2025-09-25 02:58:04'),
(122, 1, 'Admin logged in: System Administrator', '2025-09-25 03:15:49'),
(123, 1, 'Admin logged in: System Administrator', '2025-09-25 06:03:05'),
(124, 1, 'Admin logged in: System Administrator', '2025-09-25 14:48:01'),
(125, 1, 'Admin logged in: System Administrator', '2025-09-26 00:22:36'),
(126, 1, 'Admin logged in: System Administrator', '2025-09-26 00:24:55'),
(127, 1, 'Admin logged in: System Administrator', '2025-09-26 06:23:46'),
(128, 1, 'Admin logged in: System Administrator', '2025-09-27 11:54:24'),
(129, 1, 'Admin logged in: System Administrator', '2025-09-27 16:14:20'),
(130, 1, 'Admin logged in: System Administrator', '2025-09-27 16:17:31'),
(131, 1, 'Admin logged in: System Administrator', '2025-09-28 08:53:25'),
(132, 1, 'Admin logged in: System Administrator', '2025-09-28 08:57:53'),
(133, 1, 'Updated appointment ID: 68 to status: confirmed', '2025-09-28 09:22:42'),
(134, 1, 'Updated appointment ID: 68 to status: cancelled_by_patient', '2025-09-28 09:48:19'),
(135, 1, 'Updated appointment ID: 68 to status: cancelled_by_admin', '2025-09-28 09:52:29'),
(136, 1, 'Updated appointment ID: 68 to status: cancelled_by_patient', '2025-09-28 09:52:57'),
(137, 1, 'Admin logged in: System Administrator', '2025-09-28 10:41:28'),
(138, 1, 'Admin logged in: System Administrator', '2025-09-28 10:55:17'),
(139, 1, 'Admin logged in: System Administrator', '2025-09-28 10:58:37'),
(140, 1, 'Admin logged in: System Administrator', '2025-09-28 11:10:17'),
(141, 1, 'Added new user: testdoctor (shirneyang@gmail.com)', '2025-09-28 11:12:56'),
(142, 1, 'Admin logged in: System Administrator', '2025-09-28 14:01:16'),
(143, 1, 'Updated user (ID: 22) - Name: Shirney, Role: doctor', '2025-09-28 15:27:02'),
(144, 1, 'Deleted user with ID: 22', '2025-09-28 15:31:05'),
(145, 1, 'Added new user: Shirney (shirneyang@gmail.coms)', '2025-09-28 15:31:51'),
(146, 1, 'Admin logged in: System Administrator', '2025-09-28 15:36:28'),
(147, 1, 'Deleted user with ID: 23', '2025-09-28 15:36:40'),
(148, 1, 'Added new user: Shirney (shirneyang@gmail.com)', '2025-09-28 15:37:54'),
(149, 1, 'Admin logged in: System Administrator', '2025-09-28 23:57:06'),
(150, 1, 'Admin logged in: System Administrator', '2025-09-29 01:22:15'),
(151, 1, 'Admin logged in: System Administrator', '2025-09-29 02:39:27'),
(152, 1, 'Admin logged in: System Administrator', '2025-09-29 03:34:25'),
(153, 1, 'Admin logged in: System Administrator', '2025-09-30 02:19:45'),
(154, 1, 'Admin logged in: System Administrator', '2025-09-30 03:08:50'),
(155, 1, 'Admin logged in: System Administrator', '2025-09-30 03:42:52'),
(156, 1, 'Admin logged in: System Administrator', '2025-09-30 05:22:55'),
(157, 1, 'Admin logged in: System Administrator', '2025-09-30 05:24:36'),
(158, 1, 'Admin logged in: System Administrator', '2025-09-30 06:11:23'),
(159, 1, 'Admin logged in: System Administrator', '2025-09-30 06:35:06'),
(160, 1, 'Admin logged in: System Administrator', '2025-09-30 06:52:42'),
(161, 1, 'Admin logged in: System Administrator', '2025-10-01 00:31:35'),
(162, 1, 'Admin logged in: System Administrator', '2025-10-01 00:58:21'),
(163, 1, 'Admin logged in: System Administrator', '2025-10-01 02:18:43'),
(164, 1, 'Admin logged in: System Administrator', '2025-10-01 02:43:18'),
(165, 1, 'Admin logged in: System Administrator', '2025-10-01 06:19:36'),
(166, 1, 'Admin logged in: System Administrator', '2025-10-01 06:38:36'),
(167, 1, 'Admin logged in: System Administrator', '2025-10-01 08:16:23'),
(168, 1, 'Added new user: Azwa (nurhwny0515@gmail.com)', '2025-10-01 08:18:59'),
(169, 1, 'Updated user (ID: 26) - Name: Azwa Wany, Role: doctor', '2025-10-01 08:20:23'),
(170, 1, 'Updated user (ID: 26) - Name: Azwa, Role: doctor', '2025-10-01 08:20:48'),
(171, 1, 'Admin logged in: System Administrator', '2025-10-02 00:51:17'),
(172, 1, 'Admin logged in: System Administrator', '2025-10-02 01:18:33'),
(173, 1, 'Admin logged in: System Administrator', '2025-10-02 01:46:34'),
(174, 1, 'Admin logged in: System Administrator', '2025-10-02 02:21:05'),
(175, 1, 'Admin logged in: System Administrator', '2025-10-02 03:48:22'),
(176, 1, 'Admin logged in: System Administrator', '2025-10-02 05:31:18'),
(177, 1, 'Admin logged in: System Administrator', '2025-10-02 06:12:21'),
(178, 1, 'Admin logged in: System Administrator', '2025-10-02 06:18:44'),
(179, 1, 'Admin logged in: System Administrator', '2025-10-02 06:19:12'),
(180, 1, 'Admin logged in: System Administrator', '2025-10-02 06:38:53'),
(181, 1, 'Admin logged in: System Administrator', '2025-10-02 07:09:33'),
(182, 1, 'Admin logged in: System Administrator', '2025-10-02 08:02:45'),
(183, 1, 'Admin logged in: System Administrator', '2025-10-03 00:54:51'),
(184, 1, 'Admin logged in: System Administrator', '2025-10-03 02:27:23'),
(185, 1, 'Admin logged in: System Administrator', '2025-10-03 02:32:06'),
(186, 1, 'Admin logged in: System Administrator', '2025-10-03 02:34:34'),
(187, 1, 'Admin logged in: System Administrator', '2025-10-03 02:38:50'),
(188, 1, 'Admin logged in: System Administrator', '2025-10-03 05:01:38'),
(189, 1, 'Admin logged in: System Administrator', '2025-10-03 05:49:37'),
(190, 1, 'Admin logged in: System Administrator', '2025-10-03 06:20:00'),
(191, 1, 'Admin logged in: System Administrator', '2025-10-03 06:49:32'),
(192, 1, 'Admin logged in: System Administrator', '2025-10-03 07:48:23'),
(193, 1, 'Admin logged in: System Administrator', '2025-10-03 07:49:47'),
(194, 1, 'Admin logged in: System Administrator', '2025-10-03 07:55:36'),
(195, 1, 'Admin logged in: System Administrator', '2025-10-03 08:01:29'),
(196, 1, 'Admin logged in: System Administrator', '2025-10-03 08:02:49'),
(197, 1, 'Admin logged in: System Administrator', '2025-10-03 08:09:15'),
(198, 1, 'Admin logged in: System Administrator', '2025-10-03 08:15:38'),
(199, 1, 'Admin logged in: System Administrator', '2025-10-03 08:24:06'),
(200, 1, 'Admin logged in: System Administrator', '2025-10-03 08:26:27'),
(201, 1, 'Admin logged in: System Administrator', '2025-10-06 01:37:39'),
(202, 1, 'Admin logged in: System Administrator', '2025-10-06 06:00:22'),
(203, 1, 'Admin logged in: System Administrator', '2025-10-06 06:19:46'),
(204, 1, 'Admin logged in: System Administrator', '2025-10-06 06:39:53'),
(205, 1, 'Admin logged in: System Administrator', '2025-10-06 06:44:57'),
(206, 1, 'Admin logged in: System Administrator', '2025-10-06 06:59:33'),
(207, 1, 'Admin logged in: System Administrator', '2025-10-06 07:39:11'),
(208, 1, 'Admin logged in: System Administrator', '2025-10-06 07:59:21'),
(209, 1, 'Admin logged in: System Administrator', '2025-10-06 23:54:41'),
(210, 1, 'Admin logged in: System Administrator', '2025-10-07 00:07:26'),
(211, 1, 'Admin logged in: System Administrator', '2025-10-07 01:06:46'),
(212, 1, 'Admin logged in: System Administrator', '2025-10-07 01:29:01'),
(213, 1, 'Admin logged in: System Administrator', '2025-10-07 01:49:14'),
(214, 1, 'Admin logged in: System Administrator', '2025-10-07 02:24:30'),
(215, 1, 'Added new user: testing dc (test567@mail.com)', '2025-10-07 02:39:44'),
(216, 1, 'Admin logged in: System Administrator', '2025-10-07 05:04:46'),
(217, 1, 'Admin logged in: System Administrator', '2025-10-07 05:17:09'),
(218, 1, 'Admin logged in: System Administrator', '2025-10-07 06:31:30'),
(219, 1, 'Admin logged in: System Administrator', '2025-10-07 07:02:37'),
(220, 1, 'Admin logged in: System Administrator', '2025-10-08 00:40:40'),
(221, 1, 'Admin logged in: System Administrator', '2025-10-08 03:11:53'),
(222, 1, 'Admin logged in: System Administrator', '2025-10-08 03:20:35'),
(223, 1, 'Admin logged in: System Administrator', '2025-10-08 03:23:03'),
(224, 1, 'Admin logged in: System Administrator', '2025-10-08 05:19:44'),
(225, 1, 'Admin logged in: System Administrator', '2025-10-10 01:33:00'),
(226, 1, 'Admin logged in: System Administrator', '2025-10-10 02:24:12'),
(227, 1, 'Admin logged in: System Administrator', '2025-10-10 05:16:14'),
(228, 1, 'Admin logged in: System Administrator', '2025-10-10 08:30:20');

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
  ADD UNIQUE KEY `unique_doctor_time` (`doctor_id`,`appointment_date`,`appointment_time`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `clinic_settings`
--
ALTER TABLE `clinic_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `doctor_reviews`
--
ALTER TABLE `doctor_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_patient_doctor` (`patient_id`,`doctor_id`,`appointment_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `fk_medical_records_doctor` (`doctor_id`);

--
-- Indexes for table `medical_record_services`
--
ALTER TABLE `medical_record_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medical_record_id` (`medical_record_id`),
  ADD KEY `service_id` (`service_id`);

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
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `unavailable_slots`
--
ALTER TABLE `unavailable_slots`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `doctor_reviews`
--
ALTER TABLE `doctor_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `medical_record_services`
--
ALTER TABLE `medical_record_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `unavailable_slots`
--
ALTER TABLE `unavailable_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=229;

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
-- Constraints for table `doctor_reviews`
--
ALTER TABLE `doctor_reviews`
  ADD CONSTRAINT `doctor_reviews_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `doctor_reviews_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `doctor_reviews_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `fk_medical_records_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_record_services`
--
ALTER TABLE `medical_record_services`
  ADD CONSTRAINT `medical_record_services_ibfk_1` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_record_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

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
