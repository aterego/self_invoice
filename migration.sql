SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "-04:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
#/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
#/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
 /*!40101 SET NAMES utf8mb4 */;

-- Create & use DB with a 5.7/MariaDB-friendly collation
CREATE DATABASE IF NOT EXISTS `pre_trip`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `pre_trip`;

SET FOREIGN_KEY_CHECKS=0;

-- -------------------------
-- charge_levels
-- -------------------------
DROP TABLE IF EXISTS `charge_levels`;
CREATE TABLE `charge_levels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `level` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `charge_levels` (`id`, `level`) VALUES
(1, '123123');

-- -------------------------
-- companies
-- -------------------------
DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `color` varchar(7) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `companies` (`id`, `name`, `color`) VALUES
(28, '9516-1337 Quebec', '#f9a8d4'),
(29, '1416366BC LTD', '#e9d5ff'),
(30, 'Angel Movers', '#bfdbfe'),
(31, 'Divine City Link', '#ddd6fe'),
(32, 'Hawkeye', '#bbf7d0'),
(33, 'K23 Logistics', '#fde68a'),
(34, 'Metro', '#fed7aa'),
(35, 'Pro Moving Solutions', '#bae6fd'),
(36, 'Soheil Trading Inc', '#a7f3d0');

-- -------------------------
-- drivers
-- -------------------------
DROP TABLE IF EXISTS `drivers`;
CREATE TABLE `drivers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- sites
-- -------------------------
DROP TABLE IF EXISTS `sites`;
CREATE TABLE `sites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- units
-- -------------------------
DROP TABLE IF EXISTS `units`;
CREATE TABLE `units` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- license_plates
-- -------------------------
DROP TABLE IF EXISTS `license_plates`;
CREATE TABLE `license_plates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plate` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plate` (`plate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `license_plates` (`id`, `plate`) VALUES
(1, 'WWW12');

-- -------------------------
-- inspections (matches the app)
-- -------------------------
DROP TABLE IF EXISTS `inspections`;
CREATE TABLE `inspections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `inspection_date` date NOT NULL,
  `site` varchar(255) NOT NULL,
  `license_plate` varchar(100) NOT NULL,
  `unit` varchar(100) NOT NULL,
  `company` varchar(255) NOT NULL,
  `driver_name` varchar(255) NOT NULL,

  `vehicle_odometer` decimal(10,2) NOT NULL,
  `vehicle_charge_level` varchar(50) NOT NULL,

  `tires_condition` tinyint(1) NOT NULL DEFAULT '0',
  `rims_condition` tinyint(1) NOT NULL DEFAULT '0',
  `brakes_condition` tinyint(1) NOT NULL DEFAULT '0',
  `parking_brake` tinyint(1) NOT NULL DEFAULT '0',
  `frame_condition` tinyint(1) NOT NULL DEFAULT '0',
  `hood_secure` tinyint(1) NOT NULL DEFAULT '0',
  `doors_cvor_visible` tinyint(1) NOT NULL DEFAULT '0',
  `box_condition` tinyint(1) NOT NULL DEFAULT '0',
  `mirrors_adjusted` tinyint(1) NOT NULL DEFAULT '0',
  `windshield_clean` tinyint(1) NOT NULL DEFAULT '0',
  `wipers_operational` tinyint(1) NOT NULL DEFAULT '0',
  `hv_cables_condition` tinyint(1) NOT NULL DEFAULT '0',
  `lights_indicators` tinyint(1) NOT NULL DEFAULT '0',
  `horn_functional` tinyint(1) NOT NULL DEFAULT '0',
  `fire_extinguisher_equipment` tinyint(1) NOT NULL DEFAULT '0',
  `safety_cvor` tinyint(1) NOT NULL DEFAULT '0',
  `annual_sticker` tinyint(1) NOT NULL DEFAULT '0',

  `defects_damages` text,
  `photos` json DEFAULT NULL,                -- OK on MySQL 5.7+/MariaDB (alias to LONGTEXT on MariaDB)
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
-- /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
-- /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
