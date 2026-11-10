SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `ambulances` (
  `ambulance_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `vehicle_number` varchar(20) NOT NULL,
  `driver_name` varchar(100) NOT NULL,
  `driver_phone` varchar(20) NOT NULL,
  `ambulance_type` enum('basic','bls','als','mortuary') DEFAULT 'basic',
  `status` enum('available','dispatched','on_way','at_pickup','en_route','at_hospital','maintenance','assigned') DEFAULT 'available',
  `current_lat` decimal(10,7) DEFAULT 0.0000000,
  `current_lng` decimal(10,7) DEFAULT 0.0000000,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  KEY `idx_ambulance_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `ambulances` (`ambulance_id`, `vehicle_number`, `driver_name`, `driver_phone`, `ambulance_type`, `status`, `current_lat`, `current_lng`, `is_active`, `created_at`) VALUES
(1, 'MH-01-AB-1001', 'Rajesh Kumar', '9876543210', 'als', 'available', 19.0760000, 72.8777000, 1, '2026-05-02 12:18:19'),
(2, 'MH-01-AB-1002', 'Suresh Patil', '9876543211', 'bls', 'available', 19.0800000, 72.8800000, 1, '2026-05-02 12:18:19'),
(3, 'MH-01-AB-1003', 'Amit Sharma', '9876543212', 'basic', 'available', 19.0700000, 72.8750000, 1, '2026-05-02 12:18:19'),
(4, 'MH-01-AB-1004', 'Vikram Singh', '9876543213', 'als', 'maintenance', 19.0760000, 72.8777000, 1, '2026-05-02 12:18:19'),
(5, 'MH-01-AB-1005', 'Prakash Yadav', '9876543214', 'bls', 'available', 19.0850000, 72.8700000, 1, '2026-05-02 12:18:19');

CREATE TABLE `ambulance_bookings` (
  `booking_id` int(11) NOT NULL,
  `booking_number` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `ambulance_id` int(11) DEFAULT NULL,
  `booked_by` int(11) DEFAULT NULL,
  `pickup_lat` decimal(10,7) DEFAULT NULL,
  `pickup_lng` decimal(10,7) DEFAULT NULL,
  `pickup_address` text NOT NULL,
  `dropoff_lat` decimal(10,7) DEFAULT NULL,
  `dropoff_lng` decimal(10,7) DEFAULT NULL,
  `dropoff_address` text DEFAULT 'MediQueue Hospital',
  `status` enum('requested','assigned','dispatched','on_way','at_pickup','en_route','at_hospital','completed','cancelled') DEFAULT 'requested',
  `emergency_type` enum('emergency','transfer','discharge','routine') DEFAULT 'routine',
  `notes` text DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  `arrived_at_pickup` datetime DEFAULT NULL,
  `arrived_at_hospital` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ambulance_tracking` (
  `tracking_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `ambulance_id` int(11) NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `speed` decimal(5,1) DEFAULT 0.0,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `is_emergency` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `appointments` (`appointment_id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `status`, `is_emergency`, `created_at`) VALUES
(11, 7, 2, '2026-04-06', NULL, 'completed', 0, '2026-04-06 06:57:02'),
(12, 8, 2, '2026-04-06', NULL, 'completed', 0, '2026-04-06 06:57:38'),
(13, 7, 2, '2026-04-06', NULL, 'completed', 0, '2026-04-06 06:59:08'),
(14, 7, 1, '2026-04-06', NULL, 'confirmed', 0, '2026-04-06 07:27:53'),
(15, 7, 1, '2026-05-15', NULL, 'cancelled', 0, '2026-05-02 11:16:22'),
(16, 8, 1, '2026-05-06', NULL, 'confirmed', 0, '2026-05-06 09:46:03');

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `beds` (
  `bed_id` int(11) NOT NULL,
  `ward_id` int(11) NOT NULL,
  `bed_number` varchar(50) NOT NULL,
  `floor` int(11) DEFAULT 1,
  `is_occupied` tinyint(1) DEFAULT 0,
  `patient_id` int(11) DEFAULT NULL,
  `admitted_at` datetime DEFAULT NULL,
  `expected_discharge` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `beds` (`bed_id`, `ward_id`, `bed_number`, `floor`, `is_occupied`, `patient_id`, `admitted_at`, `expected_discharge`, `created_at`) VALUES
(1, 1, 'G-1', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(2, 2, 'G-1', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(3, 3, 'S-1', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(4, 4, 'P-1', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(5, 5, 'I-1', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(6, 6, 'E-1', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(7, 7, 'P-1', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(8, 8, 'M-1', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(9, 1, 'G-2', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(10, 2, 'G-2', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(11, 3, 'S-2', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(12, 4, 'P-2', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(13, 5, 'I-2', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(14, 6, 'E-2', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(15, 7, 'P-2', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(16, 8, 'M-2', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(17, 1, 'G-3', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(18, 2, 'G-3', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(19, 3, 'S-3', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(20, 4, 'P-3', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(21, 5, 'I-3', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(22, 6, 'E-3', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(23, 7, 'P-3', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(24, 8, 'M-3', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(25, 1, 'G-4', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(26, 2, 'G-4', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(27, 3, 'S-4', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(28, 4, 'P-4', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(29, 5, 'I-4', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(30, 6, 'E-4', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(31, 7, 'P-4', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(32, 8, 'M-4', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(33, 1, 'G-5', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(34, 2, 'G-5', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(35, 3, 'S-5', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(36, 4, 'P-5', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(37, 5, 'I-5', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(38, 6, 'E-5', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(39, 7, 'P-5', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(40, 8, 'M-5', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(41, 1, 'G-6', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(42, 2, 'G-6', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(43, 3, 'S-6', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(44, 4, 'P-6', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(45, 5, 'I-6', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(46, 6, 'E-6', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(47, 7, 'P-6', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(48, 8, 'M-6', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(49, 1, 'G-7', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(50, 2, 'G-7', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(51, 3, 'S-7', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(52, 4, 'P-7', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(53, 5, 'I-7', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(54, 7, 'P-7', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(55, 8, 'M-7', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(56, 1, 'G-8', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(57, 2, 'G-8', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(58, 3, 'S-8', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(59, 4, 'P-8', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(60, 5, 'I-8', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(61, 7, 'P-8', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(62, 8, 'M-8', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(63, 1, 'G-9', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(64, 2, 'G-9', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(65, 3, 'S-9', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(66, 5, 'I-9', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(67, 8, 'M-9', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(68, 1, 'G-10', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(69, 2, 'G-10', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(70, 3, 'S-10', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(71, 5, 'I-10', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(72, 8, 'M-10', 1, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(73, 1, 'G-11', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(74, 2, 'G-11', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(75, 1, 'G-12', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(76, 2, 'G-12', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(77, 1, 'G-13', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(78, 2, 'G-13', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(79, 1, 'G-14', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(80, 2, 'G-14', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(81, 1, 'G-15', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(82, 2, 'G-15', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(83, 1, 'G-16', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(84, 2, 'G-16', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(85, 1, 'G-17', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(86, 2, 'G-17', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(87, 1, 'G-18', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(88, 2, 'G-18', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(89, 1, 'G-19', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(90, 2, 'G-19', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(91, 1, 'G-20', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(92, 2, 'G-20', 2, 0, NULL, NULL, NULL, '2026-05-02 11:46:35'),
(128, 6, '7', 1, 0, NULL, NULL, NULL, '2026-05-04 03:14:35');

CREATE TABLE `billing_items` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `item_type` enum('consultation','lab_test','medicine','bed','procedure','other') DEFAULT 'other',
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `billing_items` (`item_id`, `item_name`, `item_type`, `price`, `is_active`, `created_at`) VALUES
(1, 'General Consultation', 'consultation', 300.00, 1, '2026-05-02 11:46:35'),
(2, 'Specialist Consultation', 'consultation', 500.00, 1, '2026-05-02 11:46:35'),
(3, 'Senior Doctor Consultation', 'consultation', 800.00, 1, '2026-05-02 11:46:35'),
(4, 'General Ward (per day)', 'bed', 500.00, 1, '2026-05-02 11:46:35'),
(5, 'Semi Private (per day)', 'bed', 1500.00, 1, '2026-05-02 11:46:35'),
(6, 'Private Room (per day)', 'bed', 3000.00, 1, '2026-05-02 11:46:35'),
(7, 'ICU (per day)', 'bed', 5000.00, 1, '2026-05-02 11:46:35'),
(8, 'X-Ray', 'procedure', 500.00, 1, '2026-05-02 11:46:35'),
(9, 'ECG', 'procedure', 400.00, 1, '2026-05-02 11:46:35'),
(10, 'Ultrasound', 'procedure', 1200.00, 1, '2026-05-02 11:46:35'),
(11, 'Dressing/Bandage', 'procedure', 200.00, 1, '2026-05-02 11:46:35'),
(12, 'IV Fluid', 'procedure', 300.00, 1, '2026-05-02 11:46:35');

CREATE TABLE `consultations` (
  `consultation_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `status` enum('scheduled','active','completed','cancelled','missed') DEFAULT 'scheduled',
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `prescription_given` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `consultations` (`consultation_id`, `appointment_id`, `patient_id`, `doctor_id`, `room_name`, `scheduled_at`, `duration_minutes`, `status`, `started_at`, `ended_at`, `notes`, `prescription_given`, `created_at`) VALUES
(1, 16, 8, 1, 'medi-1-69fb0df674bb7', '2026-05-06 15:16:00', 30, 'active', '2026-05-06 15:16:33', NULL, NULL, 0, '2026-05-06 09:46:30'),
(2, 16, 8, 1, 'medi-1-69fb0ed5571aa', '2026-05-06 15:20:00', 30, 'active', '2026-05-06 15:20:15', NULL, NULL, 0, '2026-05-06 09:50:13');

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `departments` (`department_id`, `department_name`, `created_at`) VALUES
(1, 'Cardiology', '2026-03-22 10:48:17'),
(2, 'Orthopedics', '2026-03-22 10:48:17'),
(3, 'Neurology', '2026-03-22 10:48:17'),
(4, 'Pediatrics', '2026-03-22 10:48:17'),
(5, 'Gynecology', '2026-03-22 10:48:17'),
(6, 'General Medicine', '2026-03-22 10:48:17'),
(7, 'ENT', '2026-03-22 10:48:17'),
(8, 'Ophthalmology', '2026-03-22 10:48:17');

CREATE TABLE `doctors` (
  `doctor_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `department_id` int(11) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `consultation_time` int(11) DEFAULT 10,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `doctors` (`doctor_id`, `user_id`, `full_name`, `department_id`, `specialization`, `consultation_time`, `is_available`, `created_at`) VALUES
(1, 2, 'Dr. Rajesh Sharma', 1, 'Heart Specialist', 10, 1, '2026-03-22 10:48:17'),
(2, 3, 'Dr. Priya Singh', 2, 'Bone Specialist', 10, 1, '2026-03-22 10:48:17'),
(3, 4, 'Dr. Amit Verma', 3, 'Brain Specialist', 10, 1, '2026-03-22 10:48:17'),
(4, 5, 'Dr. Neha Gupta', 4, 'Child Specialist', 10, 1, '2026-03-22 10:48:17'),
(5, 6, 'Dr. Sanjay Patel', 5, 'Women Health', 10, 1, '2026-03-22 10:48:17');

CREATE TABLE `doctor_leaves` (
  `leave_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `leave_type` enum('sick','vacation','personal','emergency','other') DEFAULT 'other',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `doctor_schedule` (
  `schedule_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL COMMENT '1=Monday to 7=Sunday',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_duration` int(11) DEFAULT 10,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `doctor_schedule` (`schedule_id`, `doctor_id`, `day_of_week`, `start_time`, `end_time`, `slot_duration`, `is_active`) VALUES
(1, 1, 1, '09:00:00', '17:00:00', 10, 1),
(2, 1, 2, '09:00:00', '17:00:00', 10, 1),
(3, 1, 3, '09:00:00', '17:00:00', 10, 1),
(4, 1, 4, '09:00:00', '17:00:00', 10, 1),
(5, 1, 5, '09:00:00', '17:00:00', 10, 1),
(6, 2, 1, '09:00:00', '17:00:00', 10, 1),
(7, 2, 2, '09:00:00', '17:00:00', 10, 1),
(8, 2, 3, '09:00:00', '17:00:00', 10, 1),
(9, 2, 4, '09:00:00', '17:00:00', 10, 1),
(10, 2, 5, '09:00:00', '17:00:00', 10, 1),
(11, 3, 1, '09:00:00', '17:00:00', 10, 1),
(12, 3, 2, '09:00:00', '17:00:00', 10, 1),
(13, 3, 3, '09:00:00', '17:00:00', 10, 1),
(14, 3, 4, '09:00:00', '17:00:00', 10, 1),
(15, 3, 5, '09:00:00', '17:00:00', 10, 1),
(16, 4, 1, '09:00:00', '17:00:00', 10, 1),
(17, 4, 2, '09:00:00', '17:00:00', 10, 1),
(18, 4, 3, '09:00:00', '17:00:00', 10, 1),
(19, 4, 4, '09:00:00', '17:00:00', 10, 1),
(20, 4, 5, '09:00:00', '17:00:00', 10, 1),
(21, 5, 1, '09:00:00', '17:00:00', 10, 1),
(22, 5, 2, '09:00:00', '17:00:00', 10, 1),
(23, 5, 3, '09:00:00', '17:00:00', 10, 1),
(24, 5, 4, '09:00:00', '17:00:00', 10, 1),
(25, 5, 5, '09:00:00', '17:00:00', 10, 1);

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `feedback` (`feedback_id`, `appointment_id`, `rating`, `comment`, `created_at`) VALUES
(2, 11, 5, 'good', '2026-04-06 06:59:18');

CREATE TABLE `follow_ups` (
  `follow_up_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `follow_up_date` date NOT NULL,
  `follow_up_time` time DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','missed') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `insurance_providers` (
  `provider_id` int(11) NOT NULL,
  `provider_name` varchar(150) NOT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `insurance_providers` (`provider_id`, `provider_name`, `contact_phone`, `contact_email`, `is_active`, `created_at`) VALUES
(1, 'Star Health Insurance', '1800-425-2255', 'support@starhealth.in', 1, '2026-05-02 11:46:35'),
(2, 'ICICI Lombard', '1800-266-6646', 'health@icicilombard.com', 1, '2026-05-02 11:46:35'),
(3, 'HDFC Ergo', '022-6234-6234', 'support@hdfcergo.com', 1, '2026-05-02 11:46:35'),
(4, 'Max Bupa', '1860-500-8888', 'support@maxbupa.com', 1, '2026-05-02 11:46:35'),
(5, 'Bajaj Allianz', '1800-22-5858', 'health@bajajallianz.co.in', 1, '2026-05-02 11:46:35');

CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `final_amount` decimal(12,2) DEFAULT 0.00,
  `insurance_amount` decimal(12,2) DEFAULT 0.00,
  `patient_payable` decimal(12,2) DEFAULT 0.00,
  `status` enum('draft','pending','paid','partially_paid','refunded') DEFAULT 'draft',
  `payment_method` enum('cash','card','upi','insurance','mixed') DEFAULT NULL,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `invoice_items` (
  `item_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `item_type` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `lab_bookings` (
  `booking_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `scheduled_date` date NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `lab_results` (
  `result_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `result_data` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `reported_by` int(11) DEFAULT NULL,
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `lab_tests` (
  `test_id` int(11) NOT NULL,
  `test_name` varchar(150) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `lab_tests` (`test_id`, `test_name`, `department`, `price`, `description`, `is_active`, `created_at`) VALUES
(1, 'Complete Blood Count (CBC)', 'Hematology', 500.00, 'Measures RBC, WBC, platelets, hemoglobin', 1, '2026-05-02 11:46:35'),
(2, 'Blood Sugar (Fasting)', 'Pathology', 200.00, 'Fasting blood glucose test', 1, '2026-05-02 11:46:35'),
(3, 'Blood Sugar (PP)', 'Pathology', 250.00, 'Post-prandial blood glucose test', 1, '2026-05-02 11:46:35'),
(4, 'Lipid Profile', 'Pathology', 800.00, 'Cholesterol and triglyceride levels', 1, '2026-05-02 11:46:35'),
(5, 'Liver Function Test (LFT)', 'Pathology', 600.00, 'Liver enzymes and bilirubin', 1, '2026-05-02 11:46:35'),
(6, 'Kidney Function Test (KFT)', 'Pathology', 700.00, 'Creatinine, urea, electrolytes', 1, '2026-05-02 11:46:35'),
(7, 'Thyroid Profile (T3, T4, TSH)', 'Endocrinology', 900.00, 'Thyroid hormone levels', 1, '2026-05-02 11:46:35'),
(8, 'Urine Analysis', 'Pathology', 300.00, 'Complete urine examination', 1, '2026-05-02 11:46:35'),
(9, 'X-Ray Chest', 'Radiology', 500.00, 'Chest X-ray PA view', 1, '2026-05-02 11:46:35'),
(10, 'ECG', 'Cardiology', 400.00, 'Electrocardiogram', 1, '2026-05-02 11:46:35'),
(11, 'Ultrasound Abdomen', 'Radiology', 1200.00, 'Abdominal ultrasound', 1, '2026-05-02 11:46:35'),
(12, 'HbA1c', 'Pathology', 600.00, 'Glycated hemoglobin test', 1, '2026-05-02 11:46:35'),
(13, 'Dengue NS1 Antigen', 'Microbiology', 800.00, 'Dengue fever detection', 1, '2026-05-02 11:46:35'),
(14, 'Typhoid (Widal Test)', 'Microbiology', 400.00, 'Typhoid fever detection', 1, '2026-05-02 11:46:35'),
(15, 'Vitamin D', 'Pathology', 1000.00, 'Vitamin D levels', 1, '2026-05-02 11:46:35');

CREATE TABLE `language_translations` (
  `lang_id` varchar(10) NOT NULL,
  `lang_name` varchar(50) NOT NULL,
  `lang_code` varchar(5) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `language_translations` (`lang_id`, `lang_name`, `lang_code`, `is_active`) VALUES
('ar', 'العربية', 'ar', 1),
('bn', 'বাংলা', 'bn', 1),
('en', 'English', 'en', 1),
('es', 'Español', 'es', 1),
('hi', 'हिन्दी', 'hi', 1),
('mr', 'मराठी', 'mr', 1),
('ta', 'தமிழ்', 'ta', 1),
('te', 'తెలుగు', 'te', 1);

CREATE TABLE `login_attempts` (
  `attempt_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `login_attempts` (`attempt_id`, `email`, `ip_address`, `attempt_time`) VALUES
(12, 'admin@mediqueue.com', '::1', '2026-04-01 04:27:07'),
(13, 'admin@mediqueue.com', '::1', '2026-04-01 04:27:18'),
(14, 'admin@mediqueue.com', '::1', '2026-04-01 04:27:25'),
(15, 'admin@mediqueue.com', '::1', '2026-04-06 06:45:25'),
(16, 'admin@mediqueue.com', '::1', '2026-04-06 06:45:32'),
(17, 'admin@mediqueue.com', '::1', '2026-04-06 06:45:37'),
(19, 'admin@mediqueue.com', '::1', '2026-04-30 14:54:06'),
(20, 'admin@mediqueue.com', '::1', '2026-04-30 14:54:11'),
(21, 'admin@mediqueue.com', '::1', '2026-04-30 14:54:19'),
(22, 'admin@mediqueue.com', '::1', '2026-04-30 14:54:25'),
(23, 'admin@mediqueue.com', '::1', '2026-04-30 14:55:44'),
(24, 'admin@mediqueue.com', '::1', '2026-05-04 03:13:04');

CREATE TABLE `medical_history` (
  `history_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `visit_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `prescription_pdf` varchar(255) DEFAULT NULL,
  `generated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `medical_history` (`history_id`, `patient_id`, `doctor_id`, `diagnosis`, `prescription`, `notes`, `visit_date`, `created_at`, `prescription_pdf`, `generated_at`) VALUES
(4, 7, 2, 'blood sugar', 'glimstar pm 2', 'avoid sugar', '2026-04-06', '2026-04-06 07:00:55', NULL, NULL);

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(6, 10, 'Token Called', 'Your token #S-001 has been called. Please proceed to doctor.', 'warning', 0, '2026-04-06 06:57:59'),
(7, 10, 'Token Called', 'Your token #S-002 has been called. Please proceed to doctor.', 'warning', 0, '2026-04-06 06:58:45'),
(8, 10, 'Token Called', 'Your token #S-003 has been called. Please proceed to doctor.', 'warning', 0, '2026-04-06 06:59:43');

CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `age` int(11) NOT NULL,
  `relation` varchar(50) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `is_senior` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `patients` (`patient_id`, `user_id`, `full_name`, `gender`, `age`, `relation`, `phone`, `is_senior`, `created_at`) VALUES
(3, 10, 'preetam kumar', 'Male', 23, 'Self', '8299769731', 0, '2026-03-26 12:10:43'),
(7, 10, 'Mr. Abc', 'Male', 61, 'Father', '8855994422', 1, '2026-04-06 06:48:55'),
(8, 10, 'Mrs. xyzzy', 'Female', 60, 'Mother', '6677334455', 1, '2026-04-06 06:49:23');

CREATE TABLE `patient_insurance` (
  `insurance_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `policy_number` varchar(100) NOT NULL,
  `policy_holder_name` varchar(150) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `coverage_amount` decimal(12,2) DEFAULT NULL,
  `status` enum('active','expired','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `patient_vitals` (
  `vital_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `oxygen_saturation` decimal(4,1) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `respiratory_rate` int(11) DEFAULT NULL,
  `blood_sugar` decimal(5,1) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pharmacy_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pharmacy_categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Tablets', NULL, '2026-05-02 11:46:35'),
(2, 'Capsules', NULL, '2026-05-02 11:46:35'),
(3, 'Syrups', NULL, '2026-05-02 11:46:35'),
(4, 'Injections', NULL, '2026-05-02 11:46:35'),
(5, 'Ointments & Creams', NULL, '2026-05-02 11:46:35'),
(6, 'Drops', NULL, '2026-05-02 11:46:35'),
(7, 'Inhalers', NULL, '2026-05-02 11:46:35'),
(8, 'Surgical Items', NULL, '2026-05-02 11:46:35');

CREATE TABLE `pharmacy_inventory` (
  `medicine_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `medicine_name` varchar(200) NOT NULL,
  `generic_name` varchar(200) DEFAULT NULL,
  `manufacturer` varchar(150) DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 10,
  `price` decimal(10,2) DEFAULT 0.00,
  `unit` varchar(20) DEFAULT 'tablet',
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pharmacy_orders` (
  `order_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `medicines` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`medicines`)),
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','dispensed','cancelled') DEFAULT 'pending',
  `dispensed_by` int(11) DEFAULT NULL,
  `dispensed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `prescription_templates` (
  `template_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `template_name` varchar(150) NOT NULL,
  `condition_type` varchar(100) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `prescription_templates` (`template_id`, `doctor_id`, `template_name`, `condition_type`, `diagnosis`, `prescription`, `notes`, `is_active`, `created_at`) VALUES
(1, 1, 'Common Cold & Flu', 'Respiratory', 'Upper Respiratory Tract Infection', 'Tab. Cetirizine 10mg - 1 at night for 5 days\nTab. Paracetamol 500mg - SOS for fever\nSyp. Cough Syrup - 10ml TDS for 5 days', 'Rest, hydration, steam inhalation advised', 1, '2026-05-02 11:46:35'),
(2, 1, 'Hypertension', 'Cardiology', 'Essential Hypertension', 'Tab. Amlodipine 5mg - 1 daily morning\nTab. Telmisartan 40mg - 1 daily night', 'Low salt diet, regular BP monitoring, follow up after 2 weeks', 1, '2026-05-02 11:46:35'),
(3, 1, 'Diabetes Type 2', 'Endocrinology', 'Type 2 Diabetes Mellitus', 'Tab. Metformin 500mg - 1 BD after meals\nTab. Glimepiride 1mg - 1 before breakfast', 'Diabetic diet, exercise, monitor blood sugar, HbA1c after 3 months', 1, '2026-05-02 11:46:35'),
(4, 1, 'Acid Reflux / GERD', 'Gastroenterology', 'Gastroesophageal Reflux Disease', 'Cap. Pantoprazole 40mg - 1 empty stomach morning\nSyp. Antacid Gel - 10ml TDS after meals', 'Avoid spicy/oily food, small frequent meals, no late night eating', 1, '2026-05-02 11:46:35');

CREATE TABLE `queue_transfers` (
  `transfer_id` int(11) NOT NULL,
  `token_id` int(11) NOT NULL,
  `from_doctor_id` int(11) NOT NULL,
  `to_doctor_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `transferred_by` int(11) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `generated_by` int(11) NOT NULL,
  `report_type` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `staff_attendance` (
  `attendance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `clock_in` time DEFAULT NULL,
  `clock_out` time DEFAULT NULL,
  `status` enum('present','absent','late','half_day','on_leave') DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tokens` (
  `token_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `token_number` varchar(20) NOT NULL,
  `token_prefix` enum('N','S','E') DEFAULT 'N',
  `queue_date` date NOT NULL,
  `status` enum('waiting','called','completed','cancelled') DEFAULT 'waiting',
  `called_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `position` int(11) DEFAULT NULL,
  `estimated_wait_time` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `service_start_time` datetime DEFAULT NULL,
  `service_end_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tokens` (`token_id`, `appointment_id`, `doctor_id`, `token_number`, `token_prefix`, `queue_date`, `status`, `called_at`, `completed_at`, `position`, `estimated_wait_time`, `created_at`, `service_start_time`, `service_end_time`) VALUES
(11, 11, 2, 'S-001', 'S', '2026-04-06', 'completed', '2026-04-06 12:27:59', '2026-04-06 12:28:43', 1, 0, '2026-04-06 06:57:02', NULL, NULL),
(12, 12, 2, 'S-002', 'S', '2026-04-06', 'completed', '2026-04-06 12:28:45', '2026-04-06 12:28:52', 2, 10, '2026-04-06 06:57:38', NULL, NULL),
(13, 13, 2, 'S-003', 'S', '2026-04-06', 'completed', '2026-04-06 12:29:43', '2026-04-06 12:29:50', 3, 20, '2026-04-06 06:59:08', NULL, NULL),
(14, 14, 1, 'S-004', 'S', '2026-04-06', 'waiting', NULL, NULL, 4, 30, '2026-04-06 07:27:53', NULL, NULL),
(15, 15, 1, 'S-001', 'S', '2026-05-15', 'cancelled', NULL, NULL, 1, 0, '2026-05-02 11:16:22', NULL, NULL),
(16, 16, 1, 'S-001', 'S', '2026-05-06', 'waiting', NULL, NULL, 1, 0, '2026-05-06 09:46:03', NULL, NULL);

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('patient','admin','doctor','receptionist','driver') DEFAULT 'patient',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `role`, `reset_token`, `reset_expires`, `is_active`, `created_at`) VALUES
(2, 'Dr. Rajesh Sharma', 'dr.rajesh@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', NULL, NULL, 1, '2026-03-22 10:48:17'),
(3, 'Dr. Priya Singh', 'dr.priya@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', NULL, NULL, 1, '2026-03-22 10:48:17'),
(4, 'Dr. Amit Verma', 'dr.amit@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', NULL, NULL, 1, '2026-03-22 10:48:17'),
(5, 'Dr. Neha Gupta', 'dr.neha@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', NULL, NULL, 1, '2026-03-22 10:48:17'),
(6, 'Dr. Sanjay Patel', 'dr.sanjay@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', NULL, NULL, 1, '2026-03-22 10:48:17'),
(7, 'Reception User', 'reception@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'receptionist', NULL, NULL, 1, '2026-03-22 10:48:17'),
(10, 'preetam kumar', 'preetam0032@gmail.com', '$2y$10$oRgoXh9CCESMZUEdGPJU7ud0stps.rhWoQrqVeBgEejazZ3lfu5Am', 'patient', 'b7d3430000bb9832a5cdc2e236481377c8b1b824fdeaa90acac6769ebb201b85', '2026-03-31 17:42:21', 1, '2026-03-26 12:10:43'),
(12, 'Admin User', 'admin@hospital.com', '$2y$10$aNV0x7hJ1xYTx2aQcifyj.RTA4y0YN.FZM.LlUB4adRFkV/YEmsXa', 'admin', NULL, NULL, 1, '2026-03-31 15:32:34');

CREATE TABLE `virtual_queue` (
  `vq_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `token_prefix` enum('N','S','E') DEFAULT 'N',
  `token_number` varchar(20) DEFAULT NULL,
  `position` int(11) DEFAULT NULL,
  `estimated_wait_minutes` int(11) DEFAULT NULL,
  `status` enum('waiting_remotely','traveling','arrived','completed','cancelled') DEFAULT 'waiting_remotely',
  `notified_at` int(11) DEFAULT 0 COMMENT 'minutes before arrival',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `wards` (
  `ward_id` int(11) NOT NULL,
  `ward_name` varchar(100) NOT NULL,
  `ward_type` enum('general','semi_private','private','icu','emergency','pediatric','maternity') DEFAULT 'general',
  `total_beds` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `wards` (`ward_id`, `ward_name`, `ward_type`, `total_beds`, `description`, `created_at`) VALUES
(1, 'General Ward A', 'general', 20, 'General ward with basic facilities', '2026-05-02 11:46:35'),
(2, 'General Ward B', 'general', 20, 'General ward with basic facilities', '2026-05-02 11:46:35'),
(3, 'Semi Private Ward', 'semi_private', 10, 'Shared rooms with 2 beds', '2026-05-02 11:46:35'),
(4, 'Private Ward', 'private', 8, 'Single occupancy rooms', '2026-05-02 11:46:35'),
(5, 'ICU', 'icu', 10, 'Intensive Care Unit', '2026-05-02 11:46:35'),
(6, 'Emergency Ward', 'emergency', 7, 'Emergency observation beds', '2026-05-02 11:46:35'),
(7, 'Pediatric Ward', 'pediatric', 8, 'Children ward', '2026-05-02 11:46:35'),
(8, 'Maternity Ward', 'maternity', 10, 'Maternity and delivery ward', '2026-05-02 11:46:35');


ALTER TABLE `ambulances`
  ADD PRIMARY KEY (`ambulance_id`),
  ADD UNIQUE KEY `vehicle_number` (`vehicle_number`),
  ADD KEY `idx_status` (`status`);

ALTER TABLE `ambulance_bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD UNIQUE KEY `booking_number` (`booking_number`),
  ADD KEY `booked_by` (`booked_by`),
  ADD KEY `idx_patient_status` (`patient_id`,`status`),
  ADD KEY `idx_ambulance_status` (`ambulance_id`,`status`);

ALTER TABLE `ambulance_tracking`
  ADD PRIMARY KEY (`tracking_id`),
  ADD KEY `ambulance_id` (`ambulance_id`),
  ADD KEY `idx_booking` (`booking_id`);

ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `idx_patient_date` (`patient_id`,`appointment_date`),
  ADD KEY `idx_doctor_date` (`doctor_id`,`appointment_date`),
  ADD KEY `idx_status` (`status`);

ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_action` (`user_id`,`action`),
  ADD KEY `idx_created_at` (`created_at`);

ALTER TABLE `beds`
  ADD PRIMARY KEY (`bed_id`),
  ADD UNIQUE KEY `unique_bed_number` (`ward_id`,`bed_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `idx_ward_occupied` (`ward_id`,`is_occupied`);

ALTER TABLE `billing_items`
  ADD PRIMARY KEY (`item_id`);

ALTER TABLE `consultations`
  ADD PRIMARY KEY (`consultation_id`),
  ADD UNIQUE KEY `room_name` (`room_name`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `idx_doctor_status` (`doctor_id`,`status`),
  ADD KEY `idx_patient_status` (`patient_id`,`status`),
  ADD KEY `idx_scheduled_at` (`scheduled_at`);

ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD UNIQUE KEY `department_name` (`department_name`);

ALTER TABLE `doctors`
  ADD PRIMARY KEY (`doctor_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `idx_available` (`is_available`);

ALTER TABLE `doctor_leaves`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_doctor_dates` (`doctor_id`,`start_date`,`end_date`);

ALTER TABLE `doctor_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `idx_doctor_day` (`doctor_id`,`day_of_week`);

ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `idx_appointment` (`appointment_id`);

ALTER TABLE `follow_ups`
  ADD PRIMARY KEY (`follow_up_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `idx_patient_date` (`patient_id`,`follow_up_date`),
  ADD KEY `idx_doctor_date` (`doctor_id`,`follow_up_date`);

ALTER TABLE `insurance_providers`
  ADD PRIMARY KEY (`provider_id`),
  ADD UNIQUE KEY `provider_name` (`provider_name`);

ALTER TABLE `invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_invoice_number` (`invoice_number`);

ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_invoice` (`invoice_id`);

ALTER TABLE `lab_bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `test_id` (`test_id`),
  ADD KEY `idx_patient_status` (`patient_id`,`status`),
  ADD KEY `idx_scheduled_date` (`scheduled_date`);

ALTER TABLE `lab_results`
  ADD PRIMARY KEY (`result_id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `test_id` (`test_id`),
  ADD KEY `reported_by` (`reported_by`),
  ADD KEY `idx_patient` (`patient_id`);

ALTER TABLE `lab_tests`
  ADD PRIMARY KEY (`test_id`);

ALTER TABLE `language_translations`
  ADD PRIMARY KEY (`lang_id`);

ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_email_time` (`email`,`attempt_time`);

ALTER TABLE `medical_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_visit_date` (`visit_date`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`);

ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_phone` (`phone`);

ALTER TABLE `patient_insurance`
  ADD PRIMARY KEY (`insurance_id`),
  ADD UNIQUE KEY `policy_number` (`policy_number`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_policy` (`policy_number`);

ALTER TABLE `patient_vitals`
  ADD PRIMARY KEY (`vital_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_recorded_at` (`recorded_at`);

ALTER TABLE `pharmacy_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

ALTER TABLE `pharmacy_inventory`
  ADD PRIMARY KEY (`medicine_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_stock` (`stock_quantity`),
  ADD KEY `idx_expiry` (`expiry_date`);

ALTER TABLE `pharmacy_orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `dispensed_by` (`dispensed_by`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_status` (`status`);

ALTER TABLE `prescription_templates`
  ADD PRIMARY KEY (`template_id`),
  ADD KEY `idx_doctor` (`doctor_id`);

ALTER TABLE `queue_transfers`
  ADD PRIMARY KEY (`transfer_id`),
  ADD KEY `from_doctor_id` (`from_doctor_id`),
  ADD KEY `to_doctor_id` (`to_doctor_id`),
  ADD KEY `transferred_by` (`transferred_by`),
  ADD KEY `idx_token` (`token_id`);

ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `generated_by` (`generated_by`);

ALTER TABLE `staff_attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`date`),
  ADD KEY `idx_date` (`date`);

ALTER TABLE `tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `idx_queue_date_status` (`queue_date`,`status`),
  ADD KEY `idx_token_number` (`token_number`),
  ADD KEY `idx_doctor_queue` (`doctor_id`,`queue_date`,`status`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email_active` (`email`,`is_active`),
  ADD KEY `idx_reset_token` (`reset_token`);

ALTER TABLE `virtual_queue`
  ADD PRIMARY KEY (`vq_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `idx_patient_status` (`patient_id`,`status`);

ALTER TABLE `wards`
  ADD PRIMARY KEY (`ward_id`),
  ADD UNIQUE KEY `ward_name` (`ward_name`);


ALTER TABLE `ambulances`
  MODIFY `ambulance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `ambulance_bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ambulance_tracking`
  MODIFY `tracking_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `beds`
  MODIFY `bed_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

ALTER TABLE `billing_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

ALTER TABLE `consultations`
  MODIFY `consultation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `doctors`
  MODIFY `doctor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `doctor_leaves`
  MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `doctor_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `follow_ups`
  MODIFY `follow_up_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `insurance_providers`
  MODIFY `provider_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `invoices`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `invoice_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `lab_bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `lab_results`
  MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `lab_tests`
  MODIFY `test_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

ALTER TABLE `login_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

ALTER TABLE `medical_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `patient_insurance`
  MODIFY `insurance_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `patient_vitals`
  MODIFY `vital_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `pharmacy_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `pharmacy_inventory`
  MODIFY `medicine_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `pharmacy_orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `prescription_templates`
  MODIFY `template_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `queue_transfers`
  MODIFY `transfer_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `staff_attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `tokens`
  MODIFY `token_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

ALTER TABLE `virtual_queue`
  MODIFY `vq_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `wards`
  MODIFY `ward_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;


ALTER TABLE `ambulance_bookings`
  ADD CONSTRAINT `ambulance_bookings_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `ambulance_bookings_ibfk_2` FOREIGN KEY (`ambulance_id`) REFERENCES `ambulances` (`ambulance_id`),
  ADD CONSTRAINT `ambulance_bookings_ibfk_3` FOREIGN KEY (`booked_by`) REFERENCES `users` (`user_id`);

ALTER TABLE `ambulance_tracking`
  ADD CONSTRAINT `ambulance_tracking_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `ambulance_bookings` (`booking_id`),
  ADD CONSTRAINT `ambulance_tracking_ibfk_2` FOREIGN KEY (`ambulance_id`) REFERENCES `ambulances` (`ambulance_id`);

ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`);

ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

ALTER TABLE `beds`
  ADD CONSTRAINT `beds_ibfk_1` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`ward_id`),
  ADD CONSTRAINT `beds_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

ALTER TABLE `consultations`
  ADD CONSTRAINT `consultations_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`),
  ADD CONSTRAINT `consultations_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `consultations_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`);

ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `doctors_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);

ALTER TABLE `doctor_leaves`
  ADD CONSTRAINT `doctor_leaves_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`),
  ADD CONSTRAINT `doctor_leaves_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`);

ALTER TABLE `doctor_schedule`
  ADD CONSTRAINT `doctor_schedule_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE CASCADE;

ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`);

ALTER TABLE `follow_ups`
  ADD CONSTRAINT `follow_ups_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`),
  ADD CONSTRAINT `follow_ups_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `follow_ups_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`);

ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`);

ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`);

ALTER TABLE `lab_bookings`
  ADD CONSTRAINT `lab_bookings_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `lab_bookings_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `lab_tests` (`test_id`);

ALTER TABLE `lab_results`
  ADD CONSTRAINT `lab_results_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `lab_bookings` (`booking_id`),
  ADD CONSTRAINT `lab_results_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `lab_results_ibfk_3` FOREIGN KEY (`test_id`) REFERENCES `lab_tests` (`test_id`),
  ADD CONSTRAINT `lab_results_ibfk_4` FOREIGN KEY (`reported_by`) REFERENCES `doctors` (`doctor_id`);

ALTER TABLE `medical_history`
  ADD CONSTRAINT `medical_history_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `medical_history_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`);

ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

ALTER TABLE `patient_insurance`
  ADD CONSTRAINT `patient_insurance_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `patient_insurance_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `insurance_providers` (`provider_id`);

ALTER TABLE `patient_vitals`
  ADD CONSTRAINT `patient_vitals_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `patient_vitals_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`),
  ADD CONSTRAINT `patient_vitals_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`);

ALTER TABLE `pharmacy_inventory`
  ADD CONSTRAINT `pharmacy_inventory_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `pharmacy_categories` (`category_id`);

ALTER TABLE `pharmacy_orders`
  ADD CONSTRAINT `pharmacy_orders_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `pharmacy_orders_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`),
  ADD CONSTRAINT `pharmacy_orders_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`),
  ADD CONSTRAINT `pharmacy_orders_ibfk_4` FOREIGN KEY (`dispensed_by`) REFERENCES `users` (`user_id`);

ALTER TABLE `prescription_templates`
  ADD CONSTRAINT `prescription_templates_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`);

ALTER TABLE `queue_transfers`
  ADD CONSTRAINT `queue_transfers_ibfk_1` FOREIGN KEY (`token_id`) REFERENCES `tokens` (`token_id`),
  ADD CONSTRAINT `queue_transfers_ibfk_2` FOREIGN KEY (`from_doctor_id`) REFERENCES `doctors` (`doctor_id`),
  ADD CONSTRAINT `queue_transfers_ibfk_3` FOREIGN KEY (`to_doctor_id`) REFERENCES `doctors` (`doctor_id`),
  ADD CONSTRAINT `queue_transfers_ibfk_4` FOREIGN KEY (`transferred_by`) REFERENCES `users` (`user_id`);

ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`);

ALTER TABLE `staff_attendance`
  ADD CONSTRAINT `staff_attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

ALTER TABLE `tokens`
  ADD CONSTRAINT `tokens_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`),
  ADD CONSTRAINT `tokens_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`);

ALTER TABLE `virtual_queue`
  ADD CONSTRAINT `virtual_queue_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `virtual_queue_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
