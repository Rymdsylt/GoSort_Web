-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 19, 2025 at 12:03 PM
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
-- Database: `gosort_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `assigned_sorters`
--

CREATE TABLE `assigned_sorters` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_identity` varchar(100) NOT NULL,
  `assigned_floor` varchar(50) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assigned_sorters`
--

INSERT INTO `assigned_sorters` (`id`, `user_id`, `device_identity`, `assigned_floor`, `assigned_at`) VALUES
(1, 2, 'sorter', 'Floor1', '2025-10-19 07:13:25');

-- --------------------------------------------------------

--
-- Table structure for table `bin_fullness`
--

CREATE TABLE `bin_fullness` (
  `id` int(11) NOT NULL,
  `device_identity` varchar(50) NOT NULL,
  `bin_name` varchar(20) NOT NULL,
  `distance` decimal(10,2) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bin_fullness`
--

INSERT INTO `bin_fullness` (`id`, `device_identity`, `bin_name`, `distance`, `timestamp`) VALUES
(10000, 'sorter', 'Extra', 0.00, '2025-10-19 09:47:07'),
(10001, 'sorter', 'Non-Bio', 81.00, '2025-10-19 09:47:08'),
(10002, 'sorter', 'Bio', 0.00, '2025-10-19 09:47:08'),
(10003, 'sorter', 'Recyclable', 0.00, '2025-10-19 09:47:08'),
(10004, 'sorter', 'Extra', 0.00, '2025-10-19 09:47:08'),
(10005, 'sorter', 'Non-Bio', 82.00, '2025-10-19 09:47:08'),
(10006, 'sorter', 'Bio', 0.00, '2025-10-19 09:47:08'),
(10007, 'sorter', 'Recyclable', 0.00, '2025-10-19 09:47:08'),
(10008, 'sorter', 'Extra', 0.00, '2025-10-19 09:47:08'),
(10009, 'sorter', 'Non-Bio', 82.00, '2025-10-19 09:47:08'),
(10010, 'sorter', 'Bio', 0.00, '2025-10-19 09:47:08'),
(10011, 'sorter', 'Recyclable', 0.00, '2025-10-19 09:47:08'),
(10012, 'sorter', 'Extra', 0.00, '2025-10-19 09:47:17'),
(10013, 'sorter', 'Non-Bio', 81.00, '2025-10-19 09:47:17'),
(10014, 'sorter', 'Bio', 0.00, '2025-10-19 09:47:18'),
(10015, 'sorter', 'Recyclable', 0.00, '2025-10-19 09:47:18'),
(10016, 'sorter', 'Extra', 0.00, '2025-10-19 09:47:18'),
(10017, 'sorter', 'Non-Bio', 81.00, '2025-10-19 09:47:18'),
(10018, 'sorter', 'Bio', 0.00, '2025-10-19 09:47:18'),
(10019, 'sorter', 'Recyclable', 0.00, '2025-10-19 09:47:18'),
(10020, 'sorter', 'Extra', 0.00, '2025-10-19 09:47:18'),
(10021, 'sorter', 'Non-Bio', 82.00, '2025-10-19 09:47:18'),
(10022, 'sorter', 'Bio', 0.00, '2025-10-19 09:47:18'),
(10023, 'sorter', 'Recyclable', 0.00, '2025-10-19 09:47:18'),
(10024, 'sorter', 'Extra', 0.00, '2025-10-19 09:47:18'),
(10025, 'sorter', 'Non-Bio', 81.00, '2025-10-19 09:47:18'),
(10026, 'sorter', 'Bio', 0.00, '2025-10-19 09:47:27'),
(10027, 'sorter', 'Recyclable', 0.00, '2025-10-19 09:47:27'),
(10028, 'sorter', 'Extra', 0.00, '2025-10-19 09:47:27'),
(10029, 'sorter', 'Non-Bio', 82.00, '2025-10-19 09:47:28'),
(10030, 'sorter', 'Bio', 0.00, '2025-10-19 09:47:28'),
(10031, 'sorter', 'Recyclable', 0.00, '2025-10-19 09:47:28'),
(10032, 'sorter', 'Extra', 0.00, '2025-10-19 09:47:28'),
(10033, 'sorter', 'Non-Bio', 82.00, '2025-10-19 09:47:28'),
(10034, 'sorter', 'Bio', 0.00, '2025-10-19 09:47:28'),
(10035, 'sorter', 'Recyclable', 0.00, '2025-10-19 09:47:28'),
(10036, 'sorter', 'Extra', 0.00, '2025-10-19 09:47:28'),
(10037, 'sorter', 'Non-Bio', 82.00, '2025-10-19 09:47:28'),
(10038, 'sorter', 'Bio', 0.00, '2025-10-19 09:47:28'),
(10039, 'sorter', 'Recyclable', 0.00, '2025-10-19 09:47:28');

-- --------------------------------------------------------

--
-- Table structure for table `bin_notifications`
--

CREATE TABLE `bin_notifications` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_id` varchar(100) DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'normal',
  `bin_name` varchar(20) DEFAULT NULL,
  `fullness_level` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_commands`
--

CREATE TABLE `maintenance_commands` (
  `id` int(11) NOT NULL,
  `device_identity` varchar(100) NOT NULL,
  `command` varchar(50) NOT NULL,
  `executed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `executed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_mode`
--

CREATE TABLE `maintenance_mode` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sorters`
--

CREATE TABLE `sorters` (
  `id` int(11) NOT NULL,
  `device_name` varchar(100) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('online','offline') DEFAULT 'offline',
  `registration_token` varchar(64) DEFAULT NULL,
  `device_identity` varchar(100) DEFAULT NULL,
  `maintenance_mode` tinyint(1) DEFAULT 0,
  `last_active` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sorters`
--

INSERT INTO `sorters` (`id`, `device_name`, `location`, `status`, `registration_token`, `device_identity`, `maintenance_mode`, `last_active`, `created_at`) VALUES
(1, 'GS-sorter', '1stfoor', 'online', '1fa5a3ea415b538247524625e1838a20a79aa9b61424e3fc060b95c4a9199e76', 'sorter', 0, '2025-10-19 10:03:48', '2025-10-19 07:12:55');

-- --------------------------------------------------------

--
-- Table structure for table `sorter_mapping`
--

CREATE TABLE `sorter_mapping` (
  `device_identity` varchar(100) NOT NULL,
  `zdeg` varchar(10) NOT NULL,
  `ndeg` varchar(10) NOT NULL,
  `odeg` varchar(10) NOT NULL,
  `tdeg` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sorter_mapping`
--

INSERT INTO `sorter_mapping` (`device_identity`, `zdeg`, `ndeg`, `odeg`, `tdeg`) VALUES
('sorter', 'bio', 'nbio', 'hazardous', 'mixed');

-- --------------------------------------------------------

--
-- Table structure for table `sorting_history`
--

CREATE TABLE `sorting_history` (
  `id` int(11) NOT NULL,
  `device_identity` varchar(100) NOT NULL,
  `trash_type` enum('bio','nbio','hazardous','mixed') NOT NULL,
  `is_maintenance` tinyint(1) DEFAULT 0,
  `sorted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trash_sorted`
--

CREATE TABLE `trash_sorted` (
  `id` int(11) NOT NULL,
  `sorted` enum('biodegradable','non-biodegradable','hazardous','mixed') NOT NULL,
  `confidence` float DEFAULT NULL,
  `bin_location` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `sorting_history_id` int(11) DEFAULT NULL,
  `time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role` enum('admin','utility') NOT NULL,
  `userName` varchar(50) NOT NULL,
  `lastName` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `assigned_floor` varchar(50) DEFAULT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `userName`, `lastName`, `email`, `password`, `assigned_floor`, `registered_at`) VALUES
(1, 'admin', 'root', '', NULL, '$2y$10$w0f0evNBSmLHsrNjEGudtewUL7RUlbx6Nr62OIsANPdt02.XsOSRG', NULL, '2025-10-19 07:06:58'),
(2, 'utility', 'tae', 'tae', 'tae@da', '$2y$10$/cdsBh16kHAySk0DBInrfunnbCl/iki/5lyuIuzE5AMIbaICEQ21m', 'Floor1', '2025-10-19 07:13:25');

-- --------------------------------------------------------

--
-- Table structure for table `waiting_devices`
--

CREATE TABLE `waiting_devices` (
  `id` int(11) NOT NULL,
  `device_identity` varchar(100) DEFAULT NULL,
  `request_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assigned_sorters`
--
ALTER TABLE `assigned_sorters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`user_id`,`device_identity`),
  ADD KEY `device_identity` (`device_identity`);

--
-- Indexes for table `bin_fullness`
--
ALTER TABLE `bin_fullness`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bin_notifications`
--
ALTER TABLE `bin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`);

--
-- Indexes for table `maintenance_commands`
--
ALTER TABLE `maintenance_commands`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_identity` (`device_identity`);

--
-- Indexes for table `maintenance_mode`
--
ALTER TABLE `maintenance_mode`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sorters`
--
ALTER TABLE `sorters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_token` (`registration_token`),
  ADD UNIQUE KEY `device_identity` (`device_identity`);

--
-- Indexes for table `sorter_mapping`
--
ALTER TABLE `sorter_mapping`
  ADD PRIMARY KEY (`device_identity`);

--
-- Indexes for table `sorting_history`
--
ALTER TABLE `sorting_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_identity` (`device_identity`);

--
-- Indexes for table `trash_sorted`
--
ALTER TABLE `trash_sorted`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `sorting_history_id` (`sorting_history_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `waiting_devices`
--
ALTER TABLE `waiting_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_identity` (`device_identity`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assigned_sorters`
--
ALTER TABLE `assigned_sorters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bin_fullness`
--
ALTER TABLE `bin_fullness`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10040;

--
-- AUTO_INCREMENT for table `bin_notifications`
--
ALTER TABLE `bin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `maintenance_commands`
--
ALTER TABLE `maintenance_commands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_mode`
--
ALTER TABLE `maintenance_mode`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sorters`
--
ALTER TABLE `sorters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sorting_history`
--
ALTER TABLE `sorting_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trash_sorted`
--
ALTER TABLE `trash_sorted`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `waiting_devices`
--
ALTER TABLE `waiting_devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assigned_sorters`
--
ALTER TABLE `assigned_sorters`
  ADD CONSTRAINT `assigned_sorters_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assigned_sorters_ibfk_2` FOREIGN KEY (`device_identity`) REFERENCES `sorters` (`device_identity`) ON DELETE CASCADE;

--
-- Constraints for table `bin_notifications`
--
ALTER TABLE `bin_notifications`
  ADD CONSTRAINT `bin_notifications_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `sorters` (`device_identity`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_commands`
--
ALTER TABLE `maintenance_commands`
  ADD CONSTRAINT `maintenance_commands_ibfk_1` FOREIGN KEY (`device_identity`) REFERENCES `sorters` (`device_identity`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_mode`
--
ALTER TABLE `maintenance_mode`
  ADD CONSTRAINT `maintenance_mode_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sorter_mapping`
--
ALTER TABLE `sorter_mapping`
  ADD CONSTRAINT `sorter_mapping_ibfk_1` FOREIGN KEY (`device_identity`) REFERENCES `sorters` (`device_identity`) ON DELETE CASCADE;

--
-- Constraints for table `sorting_history`
--
ALTER TABLE `sorting_history`
  ADD CONSTRAINT `sorting_history_ibfk_1` FOREIGN KEY (`device_identity`) REFERENCES `sorters` (`device_identity`) ON DELETE CASCADE;

--
-- Constraints for table `trash_sorted`
--
ALTER TABLE `trash_sorted`
  ADD CONSTRAINT `trash_sorted_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `trash_sorted_ibfk_2` FOREIGN KEY (`sorting_history_id`) REFERENCES `sorting_history` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `check_inactive_sorters` ON SCHEDULE EVERY 30 SECOND STARTS '2025-10-19 18:03:24' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE sorters 
        SET status = 'offline'
        WHERE status = 'online' 
        AND maintenance_mode = 0
        AND last_active < NOW() - INTERVAL 60 SECOND$$

CREATE DEFINER=`root`@`localhost` EVENT `end_maintenance_mode_after_1_minute` ON SCHEDULE EVERY 1 MINUTE STARTS '2025-10-19 17:43:50' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE maintenance_mode
        SET active = FALSE, end_time = NOW()
        WHERE active = TRUE AND start_time < NOW() - INTERVAL 1 MINUTE$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
