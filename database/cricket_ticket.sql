-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 05, 2026 at 07:20 PM
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
-- Database: `cricket_ticket`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('superadmin','admin') DEFAULT 'admin',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Sample data for table `admins`
-- NOTE: Default password: admin123 (change after first login)
--

INSERT INTO `admins` (`id`, `username`, `password`, `email`, `role`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2y$10$6W5csmfl98Jik0z2kdePEO92AoIhWkWGOXX3IgWyeJkCohHKkiF1W', 'admin@cricket.com', 'superadmin', NULL, '2026-03-03 09:35:33');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_id` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `total_tickets` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `booking_status` enum('pending','confirmed','cancelled','failed') DEFAULT 'pending',
  `payment_status` enum('pending','success','failed') DEFAULT 'pending',
  `cancellation_deadline` datetime DEFAULT NULL,
  `booking_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Sample data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_id`, `user_id`, `match_id`, `total_tickets`, `total_amount`, `booking_status`, `payment_status`, `cancellation_deadline`, `booking_time`, `updated_at`) VALUES
(1, 'CT20260319093548944', 1, 13, 2, 4000.00, 'cancelled', 'success', '2026-03-13 11:27:00', '2026-03-19 08:35:48', '2026-03-19 09:02:42'),
(2, 'CT20260319103120801', 1, 13, 2, 4000.00, 'confirmed', 'success', '2026-03-13 11:27:00', '2026-03-19 09:31:20', '2026-03-19 09:47:29'),
(4, 'CT20260319152026702', 1, 13, 2, 7000.00, 'confirmed', 'success', '2026-03-13 11:27:00', '2026-03-19 14:20:26', '2026-03-19 14:20:26'),
(5, 'CT20260319153300464', 2, 13, 2, 7000.00, 'cancelled', 'success', '2026-03-13 11:27:00', '2026-03-19 14:33:00', '2026-04-03 18:34:00'),
(6, 'CT20260319155257710', 1, 15, 2, 5000.00, 'cancelled', 'success', '2026-03-30 19:30:00', '2026-03-19 14:52:57', '2026-04-04 06:52:57'),
(7, 'CT20260319174932385', 2, 15, 2, 6000.00, 'confirmed', 'success', '2026-03-30 19:30:00', '2026-03-19 16:49:32', '2026-03-19 16:49:32'),
(8, 'CT20260320050338375', 3, 15, 2, 5000.00, 'confirmed', 'success', '2026-03-30 19:30:00', '2026-03-20 04:03:38', '2026-03-20 04:03:38'),
(9, 'CT20260320055124126', 3, 13, 3, 6000.00, 'confirmed', 'success', '2026-03-13 11:27:00', '2026-03-20 04:51:24', '2026-03-20 04:51:24'),
(10, 'CT20260320153639601', 3, 18, 2, 700.00, 'confirmed', 'success', '2026-03-30 19:30:00', '2026-03-20 14:36:39', '2026-03-20 14:36:39'),
(11, 'CT20260321113114513', 1, 13, 3, 15000.00, 'confirmed', 'success', '2026-03-25 11:27:00', '2026-03-21 10:31:14', '2026-03-21 10:31:14'),
(12, 'CT20260322074654787', 1, 16, 3, 4500.00, 'cancelled', 'success', '2026-03-29 22:20:00', '2026-03-22 06:46:54', '2026-04-04 06:46:58'),
(13, 'CT20260322090724643', 1, 16, 4, 14000.00, 'confirmed', 'success', '2026-03-29 22:20:00', '2026-03-22 08:07:24', '2026-03-22 08:07:24'),
(14, 'CT20260322091526601', 1, 19, 5, 7500.00, 'confirmed', 'success', '2026-03-27 19:30:00', '2026-03-22 08:15:26', '2026-03-22 08:15:26'),
(15, 'CT20260322093119909', 1, 19, 3, 4500.00, 'confirmed', 'success', '2026-03-27 19:30:00', '2026-03-22 08:31:19', '2026-03-22 08:31:19'),
(16, 'CT20260322105310611', 2, 18, 5, 22500.00, 'confirmed', 'success', '2026-03-30 19:30:00', '2026-03-22 09:53:10', '2026-03-22 09:53:10'),
(17, 'CT20260323092313814', 4, 18, 5, 27500.00, 'confirmed', 'success', '2026-03-30 19:30:00', '2026-03-23 08:23:13', '2026-03-23 08:23:13'),
(18, 'CT20260323153156642', 4, 21, 4, 14000.00, 'confirmed', 'success', '2026-03-29 19:39:00', '2026-03-23 14:31:56', '2026-03-23 14:31:56'),
(19, 'CT20260323153310978', 4, 20, 6, 6000.00, 'confirmed', 'success', '2026-03-28 19:30:00', '2026-03-23 14:33:10', '2026-03-23 14:33:10'),
(20, 'CT20260324064806680', 5, 18, 5, 32500.00, 'confirmed', 'success', '2026-03-30 19:30:00', '2026-03-24 05:48:06', '2026-03-24 05:48:06'),
(21, 'CT20260325084857840', 6, 13, 5, 17500.00, 'confirmed', 'success', '2026-03-25 11:27:00', '2026-03-25 07:48:57', '2026-03-25 07:48:57'),
(22, 'CT20260403150445729', 1, 19, 3, 4500.00, 'confirmed', 'success', '2026-04-05 19:30:00', '2026-04-03 13:04:45', '2026-04-03 13:04:45'),
(23, 'CT20260403153315148', 1, 16, 4, 14000.00, 'confirmed', 'success', '2026-04-06 22:20:00', '2026-04-03 13:33:15', '2026-04-03 13:33:15'),
(24, 'CT20260403153411131', 4, 19, 4, 16000.00, 'confirmed', 'success', '2026-04-05 19:30:00', '2026-04-03 13:34:11', '2026-04-03 13:34:11'),
(25, 'CT20260404082120200', 5, 19, 4, 16000.00, 'confirmed', 'success', '2026-04-05 19:30:00', '2026-04-04 06:21:20', '2026-04-04 06:21:20'),
(26, 'CT20260404125201456', 1, 14, 3, 7500.00, 'confirmed', 'success', '2026-04-08 10:34:00', '2026-04-04 10:52:01', '2026-04-04 10:52:01'),
(27, 'CT20260404184228120', 4, 14, 3, 7500.00, 'confirmed', 'success', '2026-04-08 10:34:00', '2026-04-04 16:42:28', '2026-04-04 16:42:28'),
(28, 'CT20260405114124570', 4, 16, 2, 3000.00, 'confirmed', 'success', '2026-04-06 22:20:00', '2026-04-05 09:41:24', '2026-04-05 09:41:24'),
(29, 'CT20260405114258443', 2, 16, 3, 4500.00, 'confirmed', 'success', '2026-04-06 22:20:00', '2026-04-05 09:42:58', '2026-04-05 09:42:58'),
(30, 'CT20260405190948864', 6, 19, 5, 7500.00, 'confirmed', 'success', '2026-04-05 19:30:00', '2026-04-05 17:09:48', '2026-04-05 17:09:48');

-- --------------------------------------------------------

--
-- Table structure for table `booking_items`
--

CREATE TABLE `booking_items` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `seats_no` varchar(255) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `venue_category_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_items`
--

INSERT INTO `booking_items` (`id`, `booking_id`, `category_id`, `quantity`, `seats_no`, `unit_price`, `total_price`, `venue_category_id`) VALUES
(1, 1, 3, 2, 'A3,A4', 2000.00, 4000.00, 43),
(2, 2, 3, 2, 'A1,A2', 2000.00, 4000.00, 43),
(4, 4, 1, 2, 'A2,A1', 3500.00, 7000.00, 51),
(5, 5, 1, 2, 'B1,B2', 3500.00, 7000.00, 51),
(6, 6, 1, 2, 'F8,F7', 2500.00, 5000.00, 46),
(7, 7, 2, 2, 'A1,A2', 3000.00, 6000.00, 44),
(8, 8, 1, 2, 'A1,A2', 2500.00, 5000.00, 46),
(9, 9, 3, 3, 'A3,A4,A5', 2000.00, 6000.00, 43),
(10, 10, 3, 2, 'A1,A2', 350.00, 700.00, 54),
(11, 11, 10, 3, 'A1,A2,A3', 5000.00, 15000.00, 45),
(12, 12, 3, 3, 'A3,A4,A5', 1500.00, 4500.00, 58),
(13, 13, 1, 4, 'C2,C1,C3,C4', 3500.00, 14000.00, 59),
(14, 14, 3, 5, 'E2,E1,E3,E4,E5', 1500.00, 7500.00, 56),
(15, 15, 3, 3, 'A1,A2,A3', 1500.00, 4500.00, 56),
(16, 16, 1, 5, 'A1,A2,A3,A4,A5', 4500.00, 22500.00, NULL),
(17, 17, 10, 5, 'D1,D2,D3,D4,D5', 5500.00, 27500.00, NULL),
(18, 18, 1, 4, 'E3,E4,E5,E6', 3500.00, 14000.00, NULL),
(19, 19, 3, 6, 'C3,C4,C5,C6,C7,C8', 1000.00, 6000.00, NULL),
(20, 20, 2, 5, 'C2,C3,C4,C5,C6', 6500.00, 32500.00, NULL),
(21, 21, 1, 5, 'D4,D5,D6,D7,D8', 3500.00, 17500.00, NULL),
(22, 22, 3, 3, 'B1,B2,B3', 1500.00, 4500.00, NULL),
(23, 23, 1, 4, 'A1,A2,A3,A4', 3500.00, 14000.00, NULL),
(24, 24, 1, 4, 'E1,E2,E3,E4', 4000.00, 16000.00, NULL),
(25, 25, 1, 4, 'F1,F2,F3,F4', 4000.00, 16000.00, NULL),
(26, 26, 1, 3, 'A1,A2,A3', 2500.00, 7500.00, NULL),
(27, 27, 1, 3, 'D1,D2,D3', 2500.00, 7500.00, NULL),
(28, 28, 3, 2, 'A1,A2', 1500.00, 3000.00, NULL),
(29, 29, 3, 3, 'A3,A4,A5', 1500.00, 4500.00, NULL),
(30, 30, 3, 5, 'A4,A5,A6,A7,A8', 1500.00, 7500.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Sample data for table `feedback`
--

INSERT INTO `feedback` (`id`, `user_id`, `match_id`, `booking_id`, `rating`, `message`, `created_at`) VALUES
(1, 1, 13, 2, 5, 'Amazing match experience!', '2026-03-19 09:34:20'),
(2, 2, 13, 5, 2, 'Could have been better', '2026-03-19 16:48:30'),
(3, 4, 18, 17, 4, 'Great atmosphere at the stadium', '2026-03-23 08:29:14'),
(4, 4, 18, 17, 4, 'What a thrilling match!', '2026-03-23 09:32:40');

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `id` int(11) NOT NULL,
  `team1_id` int(11) NOT NULL,
  `team2_id` int(11) NOT NULL,
  `venue_id` int(11) NOT NULL,
  `match_date` date NOT NULL,
  `match_time` time NOT NULL,
  `match_type` enum('T20','ODI','Test','IPL','World Cup') DEFAULT 'T20',
  `description` text DEFAULT NULL,
  `status` enum('upcoming','live','completed','cancelled') DEFAULT 'upcoming',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `matches`
--

INSERT INTO `matches` (`id`, `team1_id`, `team2_id`, `venue_id`, `match_date`, `match_time`, `match_type`, `description`, `status`, `created_at`, `updated_at`) VALUES
(13, 14, 12, 7, '2026-03-26', '11:27:00', 'T20', 'India vs Pakistan high-voltage T20 match', 'completed', '2026-03-10 02:57:30', '2026-04-02 14:16:02'),
(14, 20, 21, 8, '2026-04-09', '10:34:00', 'IPL', 'hyuhuygty', 'upcoming', '2026-03-10 05:03:13', '2026-04-03 13:47:32'),
(15, 23, 12, 8, '2026-04-08', '19:30:00', 'T20', 'superb', 'upcoming', '2026-03-17 16:48:36', '2026-04-02 13:46:18'),
(16, 22, 21, 6, '2026-04-07', '22:20:00', 'IPL', 'superb', 'upcoming', '2026-03-17 16:50:19', '2026-04-02 13:53:16'),
(18, 24, 20, 2, '2026-04-14', '19:30:00', 'IPL', 'virat vs Dhoni ', 'upcoming', '2026-03-20 14:35:26', '2026-04-03 13:49:04'),
(19, 26, 25, 1, '2026-04-06', '19:30:00', 'IPL', 'Indian premier league', 'upcoming', '2026-03-20 16:26:45', '2026-04-02 13:52:37'),
(20, 27, 26, 4, '2026-04-11', '19:30:00', 'IPL', 'Big fan base rivalry', 'upcoming', '2026-03-23 13:13:52', '2026-04-03 13:49:17'),
(21, 28, 20, 11, '2026-04-01', '19:39:00', 'IPL', 'Big fan base rivalry', 'completed', '2026-03-23 13:20:50', '2026-04-02 14:16:02'),
(22, 29, 25, 12, '2026-04-11', '15:30:00', 'IPL', 'Big fan base rivalry', 'upcoming', '2026-03-23 13:25:39', '2026-04-03 13:48:43'),
(23, 30, 29, 13, '2026-04-19', '19:30:00', 'IPL', 'superb', 'upcoming', '2026-04-04 15:55:34', '2026-04-04 15:55:34');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('booking','payment','cancellation','match') DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Sample data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 'cancellation', 'Booking Cancelled', 'Your booking has been successfully cancelled. Your refund will be processed in 5-7 working days.', 1, '2026-04-04 06:46:58'),
(2, 1, 'cancellation', 'Booking Cancelled', 'Your refund will be processed in a few days and the seats you booked have been successfully cancelled.', 1, '2026-04-04 06:52:57');

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

CREATE TABLE `otp_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- No sample data for OTP verifications (contains sensitive user data)

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_method` enum('card','upi','netbanking','wallet') DEFAULT 'card',
  `amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','success','failed') DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `payment_response` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `transaction_id`, `payment_method`, `amount`, `payment_status`, `payment_date`, `payment_response`) VALUES
(1, 1, NULL, 'upi', 4000.00, 'success', NULL, NULL),
(2, 2, NULL, 'card', 4000.00, 'success', NULL, NULL),
(4, 4, NULL, 'netbanking', 7000.00, 'success', NULL, NULL),
(5, 5, NULL, 'netbanking', 7000.00, 'success', NULL, NULL),
(6, 6, NULL, 'upi', 5000.00, 'success', NULL, NULL),
(7, 7, NULL, 'card', 6000.00, 'success', NULL, NULL),
(8, 8, NULL, 'card', 5000.00, 'success', NULL, NULL),
(9, 9, NULL, 'card', 6000.00, 'success', NULL, NULL),
(10, 10, NULL, 'card', 700.00, 'success', NULL, NULL),
(11, 11, NULL, 'card', 15000.00, 'success', NULL, NULL),
(12, 12, NULL, 'card', 4500.00, 'success', NULL, NULL),
(13, 13, NULL, 'wallet', 14000.00, 'success', NULL, NULL),
(14, 14, NULL, 'upi', 7500.00, 'success', NULL, NULL),
(15, 15, NULL, 'upi', 4500.00, 'success', NULL, NULL),
(16, 16, NULL, 'upi', 22500.00, 'success', NULL, NULL),
(17, 17, NULL, 'upi', 27500.00, 'success', NULL, NULL),
(18, 18, NULL, 'upi', 14000.00, 'success', NULL, NULL),
(19, 19, NULL, 'netbanking', 6000.00, 'success', NULL, NULL),
(20, 20, NULL, 'upi', 32500.00, 'success', NULL, NULL),
(21, 21, NULL, 'upi', 17500.00, 'success', NULL, NULL),
(22, 22, NULL, 'upi', 4500.00, 'success', NULL, NULL),
(23, 23, NULL, 'card', 14000.00, 'success', NULL, NULL),
(24, 24, NULL, 'netbanking', 16000.00, 'success', NULL, NULL),
(25, 25, NULL, 'wallet', 16000.00, 'success', NULL, NULL),
(26, 26, NULL, 'upi', 7500.00, 'success', NULL, NULL),
(27, 27, NULL, 'upi', 7500.00, 'success', NULL, NULL),
(28, 28, NULL, 'upi', 3000.00, 'success', NULL, NULL),
(29, 29, NULL, 'upi', 4500.00, 'success', NULL, NULL),
(30, 30, NULL, 'netbanking', 7500.00, 'success', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `seat_categories`
--

CREATE TABLE `seat_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seat_categories`
--

INSERT INTO `seat_categories` (`id`, `name`, `description`) VALUES
(1, 'VIP', 'VIP seats with best view and facilities'),
(2, 'Premium', 'Premium seats with good view'),
(3, 'General', 'General seating'),
(10, 'Platinum', 'Best seats in the stadium\r\nVery close to the ground'),
(11, 'Economy', 'suitable for general spectators.'),
(12, 'Pavilion', 'Close to pitch, good view'),
(13, 'VVIP', 'close to ground');

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `short_name` varchar(10) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`id`, `name`, `short_name`, `logo`, `created_at`) VALUES
(12, 'india', 'ind', 'image\\teams\\ind.png', '2026-03-07 09:27:24'),
(14, 'pakistan', 'pak', 'image\\teams\\pak.jpg', '2026-03-07 09:29:47'),
(20, 'CSK', 'CSK', 'image\\teams\\csk.png', '2026-03-07 11:47:03'),
(21, 'GT', 'GT', 'image\\teams\\gt.png', '2026-03-10 05:02:29'),
(22, 'Delhi capital', 'DC', 'image\\teams\\dc.png', '2026-03-17 09:32:19'),
(23, 'Aus', 'Aus', NULL, '2026-03-17 16:47:49'),
(24, 'RCB', 'RCB', 'image\\teams\\rcb.jpg', '2026-03-20 14:34:26'),
(25, 'SRH', 'SRH', 'image\\teams\\srh.jpg', '2026-03-20 16:25:08'),
(26, 'KKR', 'KKR', 'image\\teams\\kkr.png', '2026-03-20 16:25:19'),
(27, 'MI', 'MI', 'image\\teams\\mi.png', '2026-03-23 13:11:41'),
(28, 'RR', 'RR', 'image\\teams\\rr.jpg', '2026-03-23 13:16:30'),
(29, 'LSG', 'LSG', 'image\\teams\\LSG1.png', '2026-03-23 13:22:02'),
(30, 'PBKS', 'PBKS', 'image/teams/pbks.jpg', '2026-04-04 15:52:58');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT 'default.jpg',
  `status` enum('active','inactive') DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT 0,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Sample data for table `users`
-- NOTE: These are demo users with fake data. Default password for all: Test@1234
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `address`, `profile_image`, `status`, `email_verified`, `reset_token`, `reset_expiry`, `created_at`, `updated_at`, `profile_picture`) VALUES
(1, 'Demo User', 'demo@example.com', '$2y$10$A.MO.pjE2q3POd5K/hx3iOTOoAszFLkH0Yd8S3QLMnRqJqv1/OHdK', '9876543210', NULL, 'default.jpg', 'active', 1, NULL, NULL, '2026-03-11 08:09:10', '2026-03-30 17:42:15', NULL),
(2, 'Test User', 'test@example.com', '$2y$10$H6QDCN4C5OQHVTY0UuHBf.kyQwl4TmCsfh0NOChHskrXXukRR1Qiu', '9876543211', NULL, 'default.jpg', 'active', 1, NULL, NULL, '2026-03-11 08:22:27', '2026-03-19 14:31:55', NULL),
(3, 'Sample User', 'sample@example.com', '$2y$10$pIqcgch5N/aSzcwWenvImOlV9HDLKubSQVOX/qC60cnek7ofyjAQK', '9876543212', NULL, 'default.jpg', 'active', 1, NULL, NULL, '2026-03-11 08:57:26', '2026-03-19 14:10:24', NULL),
(4, 'John Doe', 'john@example.com', '$2y$10$CkoB8cRG4W6yGyaCC725yORcMtUzSeQ6Xa3oXQwlxfThhq48/ZsGK', '9876543213', NULL, 'default.jpg', 'active', 1, NULL, NULL, '2026-03-17 15:58:59', '2026-03-17 15:59:38', NULL),
(5, 'Jane Doe', 'jane@example.com', '$2y$10$u/L41yI0ka7W8QYgcM0.9usRE459ztvDWlyDEIymcOleeYYSaiKkG', '9876543214', NULL, 'default.jpg', 'active', 1, NULL, NULL, '2026-03-24 05:45:58', '2026-03-24 05:46:48', NULL),
(6, 'Alex Smith', 'alex@example.com', '$2y$10$4MgSykdHfeuUzTwXVSOiNuN0im/5TlgbLDYiLYVNsQbW752.dsyh6', '9876543215', NULL, 'default.jpg', 'active', 1, NULL, NULL, '2026-03-25 07:38:58', '2026-03-25 07:46:43', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `venues`
--

CREATE TABLE `venues` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'India',
  `capacity` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`id`, `name`, `city`, `state`, `country`, `capacity`, `address`, `created_at`) VALUES
(1, 'eden garder', 'kolkata', 'West Bengal', 'India', 66000, 'Maidan, B.B.D. Bagh, Kolkata, West Bengal 700021', '2026-03-05 14:45:01'),
(2, ' M. Chinnaswamy Stadium', 'Bengaluru', 'Karnataka', 'India', 32000, 'Shivaji Nagar, Bengaluru, Karnataka 560001', '2026-03-05 14:45:28'),
(3, 'M.A. Chidambaram Stadium', 'chennai', 'Tamil Nadu.', 'India', 38000, 'Triplicane, Chennai, Tamil Nadu 600005', '2026-03-05 17:44:41'),
(4, 'Wankhede Stadium', 'Mumbai', 'Maharashtra', 'India', 33100, 'Churchgate, Mumbai, Maharashtra 400020', '2026-03-05 17:46:10'),
(6, 'Arun Jaitley Stadium', 'Delhi', 'NCT', 'India', 41000, 'Jawaharlal Nehru Marg,Raj Ghat, New Delhi, Delhi, 110002', '2026-03-07 09:09:13'),
(7, 'Dubai International Stadium', 'Dubai', 'Dubai', 'UAE', 25000, 'Prime Business Centre,Jumeirah Village,Dubai Sports City,Dubai,United Arab Emirates', '2026-03-07 09:10:48'),
(8, 'Narendra Modi Stadium', 'Ahemdabad', 'Gujarat', 'India', 132000, 'Motera, Sabarmati, Ahmedabad, Gujarat 380005', '2026-03-07 09:16:52'),
(10, 'laal bhai', 'surat', 'gujarat', 'india', 50000, 'Goverdhan Nath haveli', '2026-03-17 09:31:40'),
(11, 'Sawai Mansingh Stadium', 'Jaipur', 'Rajasthan', 'India', 24000, 'Jaipur Nagar Nigam, Lalkothi, Jaipur, Rajasthan 302015', '2026-03-23 13:20:15'),
(12, 'Rajiv Gandhi International Stadium', 'Hyderabad', 'Telangana', 'India', 55000, 'RGI Stadium Rd, Uppal, Hyderabad, Telangana 500039', '2026-03-23 13:24:52'),
(13, 'Maharaja Yadavindra Singh PCA Stadium', 'Punjab', 'Chandigarh', 'India', 38000, 'DLF Mullanpur, Mullanpur Garibdass, New Chandigarh, Punjab 140901', '2026-04-04 15:54:47');

-- --------------------------------------------------------

--
-- Table structure for table `venue_category`
--

CREATE TABLE `venue_category` (
  `id` int(11) NOT NULL,
  `venue_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `total_seats` int(11) NOT NULL DEFAULT 0,
  `no_of_seats` int(11) NOT NULL DEFAULT 0,
  `color_code` varchar(7) DEFAULT '#007bff',
  `price` decimal(10,2) NOT NULL,
  `amenities` varchar(255) DEFAULT 'Standard Seats, Basic Facilities'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venue_category`
--

INSERT INTO `venue_category` (`id`, `venue_id`, `category_id`, `total_seats`, `no_of_seats`, `color_code`, `price`, `amenities`) VALUES
(43, 7, 3, 500, 495, '#23b710', 2000.00, 'basic'),
(44, 8, 2, 700, 698, '#5b10b7', 3000.00, 'all'),
(45, 7, 10, 800, 797, '#cd14e6', 5000.00, 'all'),
(46, 8, 1, 400, 392, '#eeff00', 2500.00, 'food'),
(51, 7, 1, 800, 793, '#eeff00', 3500.00, 'food,best view'),
(52, 2, 1, 100, 95, '#eeff00', 4500.00, 'Standard Seats, Basic Facilities'),
(53, 2, 2, 205, 200, '#007bff', 6500.00, 'Standard Seats, Basic Facilities'),
(54, 2, 3, 110, 108, '#14d411', 1000.00, 'Standard Seats, Basic Facilities'),
(55, 2, 10, 200, 195, '#cd14e6', 5500.00, 'Standard Seats, Basic Facilities'),
(56, 1, 3, 295, 279, '#1bd902', 1500.00, 'basic'),
(57, 1, 1, 200, 192, '#eeff00', 4000.00, 'Standard Seats, Basic Facilities,food,coldrink'),
(58, 6, 3, 200, 195, '#41e605', 1500.00, 'basic'),
(59, 6, 1, 200, 192, '#deed07', 3500.00, 'Standard Seats, Basic Facilities,food'),
(60, 4, 3, 350, 344, '#30f00a', 1000.00, 'Basic Facilities'),
(61, 4, 1, 250, 250, '#bac705', 2500.00, 'Highest price, best view'),
(62, 4, 11, 550, 550, '#737876', 850.00, 'Basic seating, standard view of the field'),
(63, 12, 3, 450, 450, '#27db0f', 1000.00, 'Standard Seats, Basic Facilities'),
(64, 12, 1, 250, 250, '#dce00b', 3500.00, 'Standard Seats, Basic Facilities,food,coldrink'),
(65, 11, 11, 650, 650, '#787d7b', 950.00, 'Standard Seats, Basic seating'),
(66, 11, 3, 550, 550, '#27e70d', 1500.00, 'Standard Seats, Basic Facilities'),
(67, 11, 1, 350, 346, '#a3b710', 3500.00, 'food availbity'),
(68, 1, 10, 360, 360, '#ac10b7', 5500.00, 'All Facilities,food,coldrink');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `match_id` (`match_id`);

--
-- Indexes for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_venue_category_id` (`venue_category_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `match_id` (`match_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `team1_id` (`team1_id`),
  ADD KEY `team2_id` (`team2_id`),
  ADD KEY `venue_id` (`venue_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `seat_categories`
--
ALTER TABLE `seat_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `venue_category`
--
ALTER TABLE `venue_category`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_venue_category` (`venue_id`,`category_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_venue_id` (`venue_id`),
  ADD KEY `idx_category_id` (`category_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `booking_items`
--
ALTER TABLE `booking_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `seat_categories`
--
ALTER TABLE `seat_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `venues`
--
ALTER TABLE `venues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `venue_category`
--
ALTER TABLE `venue_category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`);

--
-- Constraints for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD CONSTRAINT `booking_items_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_items_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `seat_categories` (`id`),
  ADD CONSTRAINT `fk_booking_items_venue_category` FOREIGN KEY (`venue_category_id`) REFERENCES `venue_category` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_3` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `matches_ibfk_1` FOREIGN KEY (`team1_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `matches_ibfk_2` FOREIGN KEY (`team2_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `matches_ibfk_3` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
