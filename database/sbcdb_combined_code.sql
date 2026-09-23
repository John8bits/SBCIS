-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 23, 2026 at 05:17 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sbcdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','super_admin') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'shawngaldo@gmail.com', '$2y$10$XEVTVz.b46jiwBdfdIkOkej1DxC.UCvTm5RCh.bVrCcVzyE6HCQDe', 'admin', '2026-09-23 14:53:34'),
(2, 'jbitss@gmail.com', '$2y$10$JEpD/ne4IfQl2wK6fVb.Pe/f/LDYq1jmxM4NMdPdF.1lNLqHvQ3vu', 'admin', '2026-09-23 14:53:34');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `audit_id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` varchar(100) DEFAULT NULL,
  `details_json` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `municipality_id` int(10) UNSIGNED NOT NULL,
  `barangay_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `boreholes`
--

CREATE TABLE `boreholes` (
  `borehole_id` int(10) UNSIGNED NOT NULL,
  `borehole_code` varchar(50) NOT NULL,
  `municipality_id` int(10) UNSIGNED DEFAULT NULL,
  `barangay_id` int(10) UNSIGNED DEFAULT NULL,
  `borehole_depth_m` decimal(8,2) NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `elevation_m` decimal(8,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `lock_version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(10) UNSIGNED DEFAULT NULL
) ;

--
-- Triggers `boreholes`
--
DELIMITER $$
CREATE TRIGGER `trg_boreholes_interpolation_delete` AFTER DELETE ON `boreholes` FOR EACH ROW BEGIN UPDATE system_revisions SET revision_value=revision_value+1 WHERE revision_name='interpolation_source'; END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_boreholes_interpolation_insert` AFTER INSERT ON `boreholes` FOR EACH ROW BEGIN UPDATE system_revisions SET revision_value=revision_value+1 WHERE revision_name='interpolation_source'; END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_boreholes_interpolation_update` AFTER UPDATE ON `boreholes` FOR EACH ROW BEGIN UPDATE system_revisions SET revision_value=revision_value+1 WHERE revision_name='interpolation_source'; END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_key` char(64) NOT NULL,
  `failure_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `first_failed_at` datetime NOT NULL,
  `last_failed_at` datetime NOT NULL,
  `blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `municipalities`
--

CREATE TABLE `municipalities` (
  `municipality_id` int(10) UNSIGNED NOT NULL,
  `municipality_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `soil_layers`
--

CREATE TABLE `soil_layers` (
  `soil_layer_id` int(10) UNSIGNED NOT NULL,
  `borehole_id` int(10) UNSIGNED NOT NULL,
  `layer_number` int(10) UNSIGNED NOT NULL,
  `soil_type` varchar(100) NOT NULL,
  `soil_classification` varchar(100) DEFAULT NULL,
  `soil_description` text DEFAULT NULL,
  `depth_from_m` decimal(8,2) NOT NULL,
  `depth_to_m` decimal(8,2) NOT NULL,
  `spt_n_value` int(10) UNSIGNED DEFAULT NULL,
  `bearing_capacity_kpa` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Triggers `soil_layers`
--
DELIMITER $$
CREATE TRIGGER `trg_layers_interpolation_delete` AFTER DELETE ON `soil_layers` FOR EACH ROW BEGIN UPDATE system_revisions SET revision_value=revision_value+1 WHERE revision_name='interpolation_source'; END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_layers_interpolation_insert` AFTER INSERT ON `soil_layers` FOR EACH ROW BEGIN UPDATE system_revisions SET revision_value=revision_value+1 WHERE revision_name='interpolation_source'; END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_layers_interpolation_update` AFTER UPDATE ON `soil_layers` FOR EACH ROW BEGIN UPDATE system_revisions SET revision_value=revision_value+1 WHERE revision_name='interpolation_source'; END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `system_revisions`
--

CREATE TABLE `system_revisions` (
  `revision_name` varchar(50) NOT NULL,
  `revision_value` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_revisions`
--

INSERT INTO `system_revisions` (`revision_name`, `revision_value`, `updated_at`) VALUES
('interpolation_source', 0, '2026-09-23 14:52:17');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_geotechnical_map_data`
-- (See below for the actual view)
--
CREATE TABLE `v_geotechnical_map_data` (
`borehole_id` int(10) unsigned
,`borehole_code` varchar(50)
,`municipality_name` varchar(100)
,`barangay_name` varchar(100)
,`latitude` decimal(10,7)
,`longitude` decimal(10,7)
,`elevation_m` decimal(8,2)
,`borehole_depth_m` decimal(8,2)
,`soil_layer_id` int(10) unsigned
,`layer_number` int(10) unsigned
,`soil_type` varchar(100)
,`soil_classification` varchar(100)
,`soil_description` text
,`depth_from_m` decimal(8,2)
,`depth_to_m` decimal(8,2)
,`spt_n_value` int(10) unsigned
,`bearing_capacity_kpa` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Structure for view `v_geotechnical_map_data`
--
DROP TABLE IF EXISTS `v_geotechnical_map_data`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `v_geotechnical_map_data`  AS SELECT `b`.`borehole_id` AS `borehole_id`, `b`.`borehole_code` AS `borehole_code`, `m`.`municipality_name` AS `municipality_name`, `br`.`barangay_name` AS `barangay_name`, `b`.`latitude` AS `latitude`, `b`.`longitude` AS `longitude`, `b`.`elevation_m` AS `elevation_m`, `b`.`borehole_depth_m` AS `borehole_depth_m`, `sl`.`soil_layer_id` AS `soil_layer_id`, `sl`.`layer_number` AS `layer_number`, `sl`.`soil_type` AS `soil_type`, `sl`.`soil_classification` AS `soil_classification`, `sl`.`soil_description` AS `soil_description`, `sl`.`depth_from_m` AS `depth_from_m`, `sl`.`depth_to_m` AS `depth_to_m`, `sl`.`spt_n_value` AS `spt_n_value`, `sl`.`bearing_capacity_kpa` AS `bearing_capacity_kpa` FROM (((`boreholes` `b` left join `municipalities` `m` on(`b`.`municipality_id` = `m`.`municipality_id`)) left join `barangays` `br` on(`b`.`barangay_id` = `br`.`barangay_id`)) left join `soil_layers` `sl` on(`b`.`borehole_id` = `sl`.`borehole_id`)) WHERE `b`.`archived_at` is null ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `fk_audit_admin` (`admin_id`),
  ADD KEY `idx_audit_created` (`created_at`,`audit_id`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`barangay_id`),
  ADD UNIQUE KEY `unique_barangay` (`municipality_id`,`barangay_name`),
  ADD UNIQUE KEY `unique_barangay_parent` (`barangay_id`,`municipality_id`);

--
-- Indexes for table `boreholes`
--
ALTER TABLE `boreholes`
  ADD PRIMARY KEY (`borehole_id`),
  ADD UNIQUE KEY `borehole_code` (`borehole_code`),
  ADD KEY `idx_borehole_barangay_municipality` (`barangay_id`,`municipality_id`),
  ADD KEY `idx_boreholes_archive` (`archived_at`,`borehole_id`),
  ADD KEY `idx_borehole_coordinates` (`latitude`,`longitude`),
  ADD KEY `idx_borehole_municipality` (`municipality_id`),
  ADD KEY `idx_borehole_barangay` (`barangay_id`),
  ADD KEY `idx_borehole_recent` (`created_at`,`borehole_id`),
  ADD KEY `fk_borehole_archived_by` (`archived_by`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_key`),
  ADD KEY `idx_login_attempts_cleanup` (`last_failed_at`),
  ADD KEY `idx_login_attempts_blocked` (`blocked_until`);

--
-- Indexes for table `municipalities`
--
ALTER TABLE `municipalities`
  ADD PRIMARY KEY (`municipality_id`),
  ADD UNIQUE KEY `municipality_name` (`municipality_name`);

--
-- Indexes for table `soil_layers`
--
ALTER TABLE `soil_layers`
  ADD PRIMARY KEY (`soil_layer_id`),
  ADD UNIQUE KEY `unique_borehole_layer` (`borehole_id`,`layer_number`),
  ADD KEY `idx_bearing_capacity` (`bearing_capacity_kpa`),
  ADD KEY `idx_soil_type` (`soil_type`),
  ADD KEY `idx_soil_layer_recent` (`created_at`,`soil_layer_id`);

--
-- Indexes for table `system_revisions`
--
ALTER TABLE `system_revisions`
  ADD PRIMARY KEY (`revision_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `audit_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `barangay_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `boreholes`
--
ALTER TABLE `boreholes`
  MODIFY `borehole_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `municipalities`
--
ALTER TABLE `municipalities`
  MODIFY `municipality_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `soil_layers`
--
ALTER TABLE `soil_layers`
  MODIFY `soil_layer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `barangays`
--
ALTER TABLE `barangays`
  ADD CONSTRAINT `fk_barangay_municipality` FOREIGN KEY (`municipality_id`) REFERENCES `municipalities` (`municipality_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `boreholes`
--
ALTER TABLE `boreholes`
  ADD CONSTRAINT `fk_borehole_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_borehole_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`barangay_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_borehole_barangay_municipality` FOREIGN KEY (`barangay_id`,`municipality_id`) REFERENCES `barangays` (`barangay_id`, `municipality_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_borehole_municipality` FOREIGN KEY (`municipality_id`) REFERENCES `municipalities` (`municipality_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `soil_layers`
--
ALTER TABLE `soil_layers`
  ADD CONSTRAINT `fk_soil_layer_borehole` FOREIGN KEY (`borehole_id`) REFERENCES `boreholes` (`borehole_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
