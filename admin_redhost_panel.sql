-- phpMyAdmin SQL Dump
-- version 5.0.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Erstellungszeit: 02. Apr 2021 um 02:11
-- Server-Version: 10.1.48-MariaDB-0+deb9u2
-- PHP-Version: 7.3.26

SET
SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET
time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `admin_redhost_panel`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `cashbox_clicks`
--

CREATE TABLE `cashbox_clicks`
(
    `id`         int(11) NOT NULL,
    `box_id`     varchar(255) NOT NULL,
    `ip_addr`    varchar(255) NOT NULL,
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `codes`
--
CREATE TABLE `codes`
(
    `id`      int            NOT NULL,
    `code`    varchar(512)   NOT NULL,
    `amount`  decimal(12, 2) NOT NULL,
    `useable` decimal(10, 0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `code_used`
--

CREATE TABLE `code_used`
(
    `id`         int          NOT NULL,
    `code`       varchar(512) NOT NULL,
    `user_id`    text         NOT NULL,
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


--
-- Tabellenstruktur für Tabelle `ip_addresses`
--

CREATE TABLE `ip_addresses`
(
    `id`           int(110) NOT NULL,
    `service_id`   int(11) DEFAULT NULL,
    `service_type` enum('VPS') DEFAULT NULL,
    `node_id`      varchar(512)          DEFAULT NULL,
    `ip`           varchar(255) NOT NULL,
    `cidr`         int(11) NOT NULL,
    `gateway`      varchar(255) NOT NULL,
    `mac_address`  varchar(255)          DEFAULT NULL,
    `rdns`         varchar(512)          DEFAULT NULL,
    `created_at`   datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `login_logs`
--

CREATE TABLE `login_logs`
(
    `id`         int(11) NOT NULL,
    `user_id`    int(11) NOT NULL,
    `user_addr`  varchar(255) NOT NULL,
    `user_agent` varchar(255) NOT NULL,
    `show`       int(11) NOT NULL DEFAULT '1',
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `news`
--

CREATE TABLE `news`
(
    `id`         int(11) NOT NULL,
    `icon`       varchar(255)          DEFAULT NULL,
    `title`      varchar(512) NOT NULL,
    `text`       text         NOT NULL,
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `password_resets`
--

CREATE TABLE `password_resets`
(
    `id`         int(11) NOT NULL,
    `user_info`  varchar(255) NOT NULL,
    `key`        varchar(255) NOT NULL,
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `products`
--

CREATE TABLE `products`
(
    `id`         int(11) NOT NULL,
    `name`       varchar(255) NOT NULL,
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `product_options`
--

CREATE TABLE `product_options`
(
    `id`         int(11) NOT NULL,
    `product_id` int(11) NOT NULL,
    `name`       varchar(255) NOT NULL,
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Daten für Tabelle `product_options`
--

INSERT INTO `product_options` (`id`, `product_id`, `name`, `created_at`, `updated_at`)
VALUES (1, 1, 'LXC_CORES', '2020-05-01 01:36:26', '2020-06-23 05:13:20'),
       (2, 1, 'LXC_MEMORY', '2020-05-01 01:36:26', '2020-06-23 05:13:21'),
       (3, 1, 'LXC_DISK', '2020-05-01 01:36:26', '2020-06-23 05:13:22'),
       (4, 1, 'LXC_ADDRESSES', '2020-05-01 01:36:26', '2020-06-23 05:13:23'),
       (5, 2, 'KVM_CORES', '2020-05-01 01:36:26', '2020-11-03 12:27:51'),
       (6, 2, 'KVM_MEMORY', '2020-05-01 01:36:26', '2020-06-23 05:13:21'),
       (7, 2, 'KVM_DISK', '2020-05-01 01:36:26', '2020-06-23 05:13:22'),
       (8, 2, 'KVM_ADDRESSES', '2020-05-01 01:36:26', '2020-06-23 05:13:23'),
       (9, 3, 'MC_MEMORY', '2020-05-01 01:36:26', '2020-06-23 05:13:23'),
       (10, 3, 'MC_CORES', '2020-05-01 01:36:26', '2020-06-23 05:13:23'),
       (11, 3, 'MC_DISK', '2020-05-01 01:36:26', '2020-06-23 05:13:23'),
       (14, 3, 'MC_DATABASES', '2021-03-15 04:42:08', '2021-03-15 04:42:08');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `product_option_entries`
--

CREATE TABLE `product_option_entries`
(
    `id`         int(11) NOT NULL,
    `option_id`  int(11) NOT NULL,
    `name`       varchar(255)   NOT NULL,
    `value`      varchar(255)   NOT NULL,
    `price`      decimal(43, 2) NOT NULL,
    `created_at` datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Daten für Tabelle `product_option_entries`
--

INSERT INTO `product_option_entries` (`id`, `option_id`, `name`, `value`, `price`, `created_at`, `updated_at`)
VALUES (1, 1, '1 Kern', '1', 0.90, '2020-06-23 14:08:43', '2021-02-21 10:40:10'),
       (2, 1, '2 Kerne', '2', 1.80, '2020-06-23 14:08:43', '2021-03-01 00:20:56'),
       (3, 1, '3 Kerne', '3', 2.70, '2020-06-23 14:08:43', '2021-03-01 00:21:21'),
       (4, 1, '4 Kerne', '4', 3.60, '2020-06-23 14:08:43', '2021-03-01 00:21:28'),
       (5, 1, '5 Kerne', '5', 4.50, '2020-06-23 14:08:43', '2021-03-01 00:21:34'),
       (6, 1, '6 Kerne', '6', 5.40, '2020-06-23 14:08:43', '2021-03-01 00:21:39'),
       (7, 1, '7 Kerne', '7', 6.30, '2020-06-23 14:08:43', '2021-03-01 01:46:33'),
       (8, 1, '8 Kerne', '8', 7.20, '2020-06-23 14:08:43', '2021-03-01 01:46:36'),
       (9, 1, '9 Kerne', '9', 8.10, '2021-03-01 01:46:09', '2021-03-01 01:46:25'),
       (10, 1, '10 Kerne', '10', 9.00, '2021-03-01 01:46:09', '2021-03-01 01:46:28'),
       (12, 12, 'bis 500MBit/s', '30', 0.00, '2021-02-21 10:39:03', '2021-02-21 10:39:30'),
       (19, 2, '1GB RAM', '1024', 0.60, '2020-06-23 14:08:43', '2021-02-21 10:42:54'),
       (21, 2, '2GB RAM', '2048', 1.20, '2020-06-23 14:08:43', '2021-03-01 00:24:02'),
       (22, 2, '3GB RAM', '3072', 1.80, '2020-06-23 14:08:43', '2021-03-01 00:24:08'),
       (23, 2, '4GB RAM', '4096', 2.40, '2020-06-23 14:08:43', '2021-03-01 00:25:14'),
       (24, 2, '5GB RAM', '5120', 3.00, '2020-06-23 14:08:43', '2021-03-01 00:25:19'),
       (25, 2, '6GB RAM', '6144', 3.60, '2020-06-23 14:08:43', '2021-03-01 00:25:22'),
       (26, 2, '7GB RAM', '7168', 4.20, '2020-06-23 14:08:43', '2021-03-01 00:26:48'),
       (27, 2, '8GB RAM', '8192', 4.80, '2021-03-01 00:28:10', '2021-03-01 00:28:10'),
       (28, 2, '9GB RAM', '9216', 5.40, '2020-06-23 14:08:43', '2021-03-01 00:28:20'),
       (29, 2, '10GB RAM', '10240', 6.00, '2021-03-01 00:30:11', '2021-03-01 00:30:11'),
       (30, 2, '11GB RAM', '11264', 6.40, '2021-03-01 00:30:37', '2021-03-01 00:30:37'),
       (31, 2, '12GB RAM', '12288', 7.00, '2020-06-23 14:08:43', '2021-03-01 00:30:49'),
       (32, 2, '13GB RAM', '13312', 7.60, '2021-03-01 00:31:18', '2021-03-01 00:31:18'),
       (33, 2, '14 GB RAM', '14336', 8.20, '2020-06-23 14:08:43', '2021-03-01 00:32:17'),
       (34, 2, '15 GB RAM', '15360', 8.80, '2020-06-23 14:08:43', '2021-03-01 00:32:26'),
       (35, 2, '16 GB RAM', '16384', 9.40, '2020-06-23 14:08:43', '2021-03-01 00:32:31'),
       (40, 3, '10 GB SSD', '10', 0.50, '2020-06-23 14:08:43', '2021-02-06 08:48:46'),
       (41, 3, '20 GB SSD', '20', 1.00, '2020-06-23 14:08:43', '2021-02-06 08:48:53'),
       (42, 3, '30 GB SSD', '30', 1.50, '2020-06-23 14:08:43', '2021-03-01 00:34:34'),
       (43, 3, '40 GB SSD', '40', 2.00, '2020-06-23 14:08:43', '2021-03-01 00:34:37'),
       (44, 3, '50 GB SSD', '50', 2.50, '2020-06-23 14:08:43', '2021-03-01 00:34:41'),
       (45, 3, '60 GB SSD', '60', 3.00, '2020-06-23 14:08:43', '2021-03-01 00:34:46'),
       (46, 3, '70 GB SSD', '70', 3.50, '2020-06-23 14:08:43', '2021-03-01 00:34:51'),
       (47, 3, '80 GB SSD', '80', 4.00, '2020-06-23 14:08:43', '2021-03-01 00:34:55'),
       (48, 3, '90 GB SSD', '90', 4.50, '2020-06-23 14:08:43', '2021-03-01 00:34:58'),
       (49, 3, '100 GB SSD', '100', 5.00, '2020-06-23 14:08:43', '2021-03-01 00:35:00'),
       (60, 4, '1 IPv4 Adresse', '1', 1.00, '2020-06-23 14:08:43', '2021-02-21 10:41:48'),
       (90, 5, '1 Kern', '1', 1.10, '2020-06-23 14:08:43', '2021-04-27 06:35:52'),
       (110, 6, '1 GB RAM', '1024', 0.90, '2020-06-23 14:08:43', '2021-04-27 06:37:57'),
       (181, 7, '10 GB SSD', '10', 1.00, '2020-06-23 14:08:43', '2021-03-01 01:30:37'),
       (210, 8, '1 IPv4 Adresse', '1', 0.00, '2020-06-23 14:08:43', '2021-05-03 16:52:34'),
       (215, 10, '1 Kern', '1', 1.00, '2020-06-23 14:08:43', '2021-03-29 22:27:53'),
       (220, 9, '1 GB RAM', '1024', 1.40, '2020-06-23 14:08:43', '2021-04-03 05:10:56'),
       (250, 11, '10 GB SSD', '10240', 0.50, '2020-06-23 14:08:43', '2021-03-15 03:27:11'),
       (1026, 5, '2 Kerne', '2', 2.20, '2021-03-01 00:36:51', '2021-04-27 06:35:54'),
       (1027, 5, '3 Kerne', '3', 3.30, '2021-03-01 00:37:21', '2021-04-27 06:35:57'),
       (1028, 5, '4 Kerne', '4', 4.40, '2021-03-01 00:37:40', '2021-04-27 06:36:00'),
       (1029, 5, '5 Kerne', '5', 5.50, '2021-03-01 00:37:59', '2021-04-27 06:36:03'),
       (1030, 5, '6 Kerne', '6', 6.60, '2021-03-01 00:38:33', '2021-04-27 06:36:07'),
       (1031, 5, '7 Kerne', '7', 7.70, '2021-03-01 00:38:41', '2021-04-27 06:36:10'),
       (1032, 5, '8 Kerne', '8', 8.80, '2021-03-01 00:38:56', '2021-04-27 06:36:13'),
       (1033, 5, '9 Kerne', '9', 9.90, '2021-03-01 00:39:03', '2021-04-27 06:36:17'),
       (1034, 5, '10 Kerne', '10', 11.00, '2021-03-01 00:39:12', '2021-04-27 06:36:21'),
       (1035, 5, '11 Kerne', '11', 12.10, '2021-03-01 00:39:23', '2021-04-27 06:36:26'),
       (1036, 5, '12 Kerne', '12', 13.20, '2021-03-01 00:39:32', '2021-04-27 06:36:31'),
       (1037, 5, '13 Kerne', '13', 14.30, '2021-03-01 00:39:40', '2021-04-27 06:36:36'),
       (1038, 5, '14 Kerne', '14', 15.40, '2021-03-01 00:39:55', '2021-04-27 06:36:41'),
       (1039, 5, '15 Kerne', '15', 16.50, '2021-03-01 00:40:04', '2021-04-27 06:36:44'),
       (1040, 5, '16 Kerne', '16', 17.60, '2021-03-01 00:40:13', '2021-04-27 06:36:53'),
       (1041, 6, '2GB RAM', '2048', 1.80, '2021-03-01 00:41:24', '2021-04-27 06:38:03'),
       (1042, 6, '3GB RAM', '3072', 2.70, '2021-03-01 00:41:44', '2021-04-27 06:38:14'),
       (1043, 6, '4GB RAM', '4096', 3.60, '2021-03-01 00:42:00', '2021-04-27 06:38:19'),
       (1044, 6, '5GB RAM', '5120', 4.50, '2021-03-01 00:42:53', '2021-04-27 06:38:34'),
       (1045, 6, '6GB RAM', '6144', 5.40, '2021-03-01 00:43:07', '2021-04-27 06:38:41'),
       (1046, 6, '7GB RAM', '7168', 6.30, '2021-03-01 00:43:20', '2021-04-27 06:38:49'),
       (1047, 6, '8GB RAM', '8192', 7.20, '2021-03-01 00:43:31', '2021-04-27 06:39:05'),
       (1048, 6, '9GB RAM', '9216', 9.10, '2021-03-01 00:43:43', '2021-03-01 00:43:43'),
       (1049, 6, '10GB RAM', '10240', 8.10, '2021-03-01 00:43:55', '2021-04-27 06:39:12'),
       (1050, 6, '11GB RAM', '11264', 9.00, '2021-03-01 00:44:17', '2021-04-27 06:39:26'),
       (1051, 6, '12GB RAM', '12288', 9.90, '2021-03-01 00:44:30', '2021-04-27 06:39:34'),
       (1052, 6, '13GB RAM', '13312', 10.80, '2021-03-01 00:44:44', '2021-04-27 06:39:41'),
       (1053, 6, '14GB RAM', '14336', 11.70, '2021-03-01 00:44:55', '2021-04-27 06:39:47'),
       (1054, 6, '15GB RAM', '15360', 12.60, '2021-03-01 00:45:09', '2021-04-27 06:39:53'),
       (1055, 6, '16GB RAM', '16384', 13.50, '2021-03-01 00:45:39', '2021-04-27 06:40:01'),
       (1056, 6, '17GB RAM', '17408', 14.40, '2021-03-01 00:45:57', '2021-04-27 06:40:08'),
       (1057, 6, '18GB RAM', '18432', 15.30, '2021-03-01 00:46:18', '2021-04-27 06:40:15'),
       (1058, 6, '19GB RAM', '19456', 16.20, '2021-03-01 00:46:32', '2021-04-27 06:40:25'),
       (1059, 6, '20GB RAM', '20480', 17.10, '2021-03-01 00:46:44', '2021-04-27 06:40:36'),
       (1060, 6, '21GB RAM', '21504', 18.00, '2021-03-01 00:51:48', '2021-04-27 06:40:43'),
       (1061, 6, '22GB RAM', '22528', 18.90, '2021-03-01 00:52:00', '2021-04-27 06:40:50'),
       (1062, 6, '23GB RAM', '23552', 19.80, '2021-03-01 00:52:12', '2021-04-27 06:40:57'),
       (1063, 6, '24GB RAM', '24576', 20.70, '2021-03-01 00:53:37', '2021-04-27 06:41:04'),
       (1064, 6, '25GB RAM', '25600', 21.60, '2021-03-01 00:53:51', '2021-04-27 06:41:10'),
       (1065, 6, '26GB RAM', '26624', 22.50, '2021-03-01 00:54:01', '2021-04-27 06:41:16'),
       (1066, 6, '27GB RAM', '27648', 23.40, '2021-03-01 00:54:46', '2021-04-27 06:41:22'),
       (1067, 6, '28GB RAM', '28672', 24.30, '2021-03-01 00:54:56', '2021-04-27 06:41:31'),
       (1068, 6, '29GB RAM', '29696', 25.20, '2021-03-01 00:55:10', '2021-04-27 06:41:41'),
       (1069, 6, '30GB RAM', '30720', 26.10, '2021-03-01 00:55:21', '2021-04-27 06:41:51'),
       (1070, 6, '31GB RAM', '31744', 27.00, '2021-03-01 00:55:36', '2021-04-27 06:42:00'),
       (1071, 6, '32GB RAM', '32768', 27.90, '2021-03-01 00:55:50', '2021-04-27 06:42:06'),
       (1072, 6, '33GB RAM', '33792', 28.80, '2021-03-01 00:56:01', '2021-04-27 06:42:12'),
       (1073, 6, '34GB RAM', '34816', 29.70, '2021-03-01 01:18:00', '2021-04-27 06:42:20'),
       (1074, 6, '35GB RAM', '35840', 30.60, '2021-03-01 01:18:20', '2021-04-27 06:42:27'),
       (1075, 6, '36GB RAM', '36864', 31.50, '2021-03-01 01:18:32', '2021-04-27 06:42:35'),
       (1076, 6, '37GB RAM', '37888', 32.40, '2021-03-01 01:18:44', '2021-04-27 06:42:43'),
       (1077, 6, '38GB RAM', '38912', 33.30, '2021-03-01 01:18:57', '2021-04-27 06:42:50'),
       (1078, 6, '39GB RAM', '39936', 34.20, '2021-03-01 01:19:12', '2021-04-27 06:42:56'),
       (1079, 6, '40GB RAM', '40960', 35.10, '2021-03-01 01:19:25', '2021-04-27 06:43:04'),
       (1080, 6, '41GB RAM', '41984', 36.00, '2021-03-01 01:19:38', '2021-04-27 06:43:10'),
       (1081, 6, '42GB RAM', '43008', 36.90, '2021-03-01 01:19:59', '2021-04-27 06:43:17'),
       (1082, 6, '43GB RAM', '44032', 37.80, '2021-03-01 01:20:11', '2021-04-27 06:43:24'),
       (1083, 6, '44GB RAM', '45056', 38.70, '2021-03-01 01:20:26', '2021-04-27 06:43:32'),
       (1084, 6, '45GB RAM', '46080', 39.60, '2021-03-01 01:20:38', '2021-04-27 06:43:39'),
       (1085, 6, '46GB RAM', '47104', 40.50, '2021-03-01 01:20:52', '2021-04-27 06:43:46'),
       (1086, 6, '47GB RAM', '48128', 41.40, '2021-03-01 01:21:05', '2021-04-27 06:43:53'),
       (1087, 6, '48GB RAM', '49152', 42.30, '2021-03-01 01:21:18', '2021-04-27 06:43:58'),
       (1088, 6, '49GB RAM', '50176', 43.20, '2021-03-01 01:21:30', '2021-04-27 06:44:06'),
       (1089, 6, '50GB RAM', '51200', 44.10, '2021-03-01 01:21:48', '2021-04-27 06:44:12'),
       (1090, 6, '51GB RAM', '52224', 45.00, '2021-03-01 01:22:01', '2021-04-27 06:44:19'),
       (1091, 6, '52GB RAM', '53248', 45.90, '2021-03-01 01:22:14', '2021-04-27 06:44:25'),
       (1092, 6, '53GB RAM', '54272', 46.80, '2021-03-01 01:22:29', '2021-04-27 06:44:32'),
       (1093, 6, '54GB RAM', '55296', 47.70, '2021-03-01 01:22:42', '2021-04-27 06:44:39'),
       (1094, 6, '55GB RAM', '56320', 48.60, '2021-03-01 01:22:55', '2021-04-27 06:44:46'),
       (1095, 6, '56GB RAM', '57344', 49.50, '2021-03-01 01:23:06', '2021-04-27 06:45:23'),
       (1096, 6, '57GB RAM', '58368', 50.40, '2021-03-01 01:23:21', '2021-04-27 06:45:29'),
       (1097, 6, '58GB RAM', '59392', 51.30, '2021-03-01 01:23:45', '2021-04-27 06:45:36'),
       (1098, 6, '59GB RAM', '60416', 52.20, '2021-03-01 01:24:02', '2021-04-27 06:45:42'),
       (1099, 6, '60GB RAM', '61440', 53.10, '2021-03-01 01:24:15', '2021-04-27 06:45:48'),
       (1100, 6, '61GB RAM', '62464', 54.00, '2021-03-01 01:24:35', '2021-04-27 06:45:55'),
       (1101, 6, '62GB RAM', '63488', 54.90, '2021-03-01 01:24:46', '2021-04-27 06:46:01'),
       (1102, 6, '63GB RAM', '64512', 55.80, '2021-03-01 01:24:58', '2021-04-27 06:46:13'),
       (1103, 6, '64GB RAM', '65536', 56.70, '2021-03-01 01:25:12', '2021-04-27 06:46:20'),
       (1104, 8, '2 IPv4 Adressen', '2', 1.00, '2021-03-01 01:29:24', '2021-05-03 16:52:37'),
       (1105, 8, '3 IPv4 Adressen', '3', 2.00, '2021-03-01 01:29:56', '2021-05-03 16:52:40'),
       (1106, 7, '20GB SSD', '20', 2.00, '2021-03-01 01:31:10', '2021-03-01 01:31:10'),
       (1107, 7, '30GB SSD', '30', 3.00, '2021-03-01 01:31:21', '2021-03-01 01:31:21'),
       (1108, 7, '40GB SSD', '40', 4.00, '2021-03-01 01:31:28', '2021-03-01 01:31:28'),
       (1109, 7, '50GB SSD', '50', 5.00, '2021-03-01 01:31:36', '2021-03-01 01:31:36'),
       (1110, 7, '60GB SSD', '60', 6.00, '2021-03-01 01:31:43', '2021-03-01 01:31:43'),
       (1111, 7, '70GB SSD', '70', 7.00, '2021-03-01 01:31:59', '2021-03-01 01:31:59'),
       (1112, 7, '80GB SSD', '80', 8.00, '2021-03-01 01:32:11', '2021-03-01 01:32:11'),
       (1113, 7, '90GB SSD', '90', 9.00, '2021-03-01 01:32:18', '2021-03-01 01:32:18'),
       (1114, 7, '100GB SSD', '100', 10.00, '2021-03-01 01:32:26', '2021-03-01 01:32:26'),
       (1115, 7, '110GB SSD', '110', 11.00, '2021-03-01 01:32:35', '2021-03-01 01:32:35'),
       (1116, 7, '120GB SSD', '120', 12.00, '2021-03-01 01:33:12', '2021-03-01 01:33:12'),
       (1117, 7, '130GB SSD', '130', 13.00, '2021-03-01 01:33:19', '2021-03-01 01:33:19'),
       (1118, 7, '140GB SSD', '140', 14.00, '2021-03-01 01:33:26', '2021-03-01 01:33:26'),
       (1119, 7, '150GB SSD', '150', 15.00, '2021-03-01 01:33:34', '2021-03-01 01:33:34'),
       (1120, 7, '160GB SSD', '160', 16.00, '2021-03-01 01:33:46', '2021-03-01 01:33:46'),
       (1121, 7, '170GB SSD', '170', 17.00, '2021-03-01 01:33:54', '2021-03-01 01:33:54'),
       (1122, 7, '180GB SSD', '180', 18.00, '2021-03-01 01:34:02', '2021-03-01 01:34:02'),
       (1123, 7, '190GB SSD', '190', 19.00, '2021-03-01 01:34:21', '2021-03-01 01:34:21'),
       (1124, 7, '200GB SSD', '200', 20.00, '2021-03-01 01:34:33', '2021-03-01 01:34:33'),
       (1125, 7, '210GB SSD', '210', 21.00, '2021-03-01 01:34:42', '2021-03-01 01:34:42'),
       (1126, 7, '220GB SSD', '220', 22.00, '2021-03-01 01:34:51', '2021-03-01 01:34:51'),
       (1127, 7, '230GB SSD', '230', 23.00, '2021-03-01 01:34:59', '2021-03-01 01:34:59'),
       (1128, 7, '240GB SSD', '240', 24.00, '2021-03-01 01:35:19', '2021-03-01 01:35:19'),
       (1129, 7, '250GB SSD', '250', 25.00, '2021-03-01 01:35:27', '2021-03-01 01:35:27'),
       (1130, 1, '11 Kerne', '11', 9.90, '2021-03-01 01:47:16', '2021-03-01 01:47:16'),
       (1133, 1, '12 Kerne', '12', 10.80, '2021-03-01 01:47:32', '2021-05-22 22:12:22'),
       (1134, 2, '17GB RAM', '17408', 10.00, '2021-03-01 01:48:52', '2021-03-01 01:48:52'),
       (1135, 2, '18GB RAM', '18432', 10.60, '2021-03-01 01:48:52', '2021-03-01 01:48:52'),
       (1136, 2, '19GB RAM', '19456', 11.20, '2021-03-01 01:49:55', '2021-03-01 01:49:55'),
       (1137, 2, '20GB RAM', '20480', 11.80, '2021-03-01 01:49:55', '2021-03-01 01:49:55'),
       (1138, 2, '21GB RAM', '21504', 12.40, '2021-03-01 01:50:33', '2021-03-01 01:50:33'),
       (1139, 2, '22GB RAM', '22528', 13.00, '2021-03-01 01:50:33', '2021-03-01 01:50:33'),
       (1140, 2, '23GB RAM', '23552', 13.60, '2021-03-01 01:51:09', '2021-03-01 01:51:09'),
       (1141, 2, '24GB RAM', '24576', 14.20, '2021-03-01 01:51:09', '2021-03-01 01:51:09'),
       (1142, 2, '25GB RAM', '25600', 14.80, '2021-03-01 01:51:53', '2021-03-01 01:51:53'),
       (1143, 2, '26GB RAM', '26624', 15.40, '2021-03-01 01:51:53', '2021-03-01 01:51:53'),
       (1144, 2, '27GB RAM', '27648', 16.00, '2021-03-01 01:52:31', '2021-03-01 01:52:31'),
       (1145, 2, '28GB RAM', '28672', 16.60, '2021-03-01 01:52:31', '2021-03-01 01:52:31'),
       (1146, 2, '29GB RAM', '29696', 17.20, '2021-03-01 01:53:09', '2021-03-01 01:53:09'),
       (1147, 2, '30GB RAM', '30720', 17.80, '2021-03-01 01:53:09', '2021-03-01 01:53:09'),
       (1148, 2, '31GB RAM', '31744', 18.40, '2021-03-01 01:53:46', '2021-03-01 01:53:46'),
       (1149, 2, '32GB RAM', '32768', 19.00, '2021-03-01 01:53:46', '2021-03-01 01:53:46'),
       (1150, 9, '2GB RAM', '2048', 2.80, '2021-03-15 03:36:18', '2021-04-03 05:11:10'),
       (1151, 9, '3GB RAM', '3072', 4.20, '2021-03-15 03:28:31', '2021-04-03 05:11:33'),
       (1152, 9, '4GB RAM', '4096', 5.60, '2021-03-15 03:28:54', '2021-04-03 05:11:47'),
       (1153, 9, '5GB RAM', '5120', 7.00, '2021-03-15 03:28:54', '2021-04-03 05:11:54'),
       (1154, 9, '6GB RAM', '6144', 8.40, '2021-03-15 03:29:22', '2021-04-03 05:12:00'),
       (1155, 9, '7GB RAM', '7168', 9.80, '2021-03-15 03:29:22', '2021-04-03 05:12:06'),
       (1156, 9, '8GB RAM', '8192', 11.20, '2021-03-15 03:29:52', '2021-04-03 05:12:16'),
       (1157, 9, '9GB RAM', '9216', 12.60, '2021-03-15 03:29:52', '2021-04-03 05:12:26'),
       (1158, 9, '10GB RAM', '10240', 14.00, '2021-03-15 03:30:20', '2021-04-03 05:12:37'),
       (1159, 9, '11GB RAM', '11264', 15.40, '2021-03-15 03:30:20', '2021-04-03 05:12:44'),
       (1160, 9, '12GB RAM', '12288', 16.80, '2021-03-15 03:30:48', '2021-04-03 05:12:50'),
       (1161, 9, '13GB RAM', '13312', 18.20, '2021-03-15 03:30:48', '2021-04-03 05:12:58'),
       (1162, 9, '14GB RAM', '14336', 19.60, '2021-03-15 03:31:19', '2021-04-03 05:13:05'),
       (1163, 9, '15GB RAM', '15360', 21.00, '2021-03-15 03:31:19', '2021-04-03 05:13:11'),
       (1164, 9, '16GB RAM', '16384', 22.40, '2021-03-15 03:31:41', '2021-04-03 05:13:17'),
       (1165, 9, '17GB RAM', '17408', 23.80, '2021-03-15 03:31:41', '2021-04-03 05:13:23'),
       (1166, 9, '18GB RAM', '18432', 25.20, '2021-03-15 03:32:08', '2021-04-03 05:13:42'),
       (1167, 9, '19GB RAM', '19456', 26.60, '2021-03-15 03:32:08', '2021-04-03 05:13:48'),
       (1168, 9, '20GB RAM', '20480', 28.00, '2021-03-15 03:32:28', '2021-04-03 05:13:54'),
       (1169, 9, '21GB RAM', '21504', 29.40, '2021-03-15 03:32:28', '2021-04-03 05:14:00'),
       (1170, 9, '22GB RAM', '22528', 30.80, '2021-03-15 03:32:53', '2021-04-03 05:14:09'),
       (1171, 9, '23GB RAM', '23552', 32.20, '2021-03-15 03:32:53', '2021-04-03 05:14:17'),
       (1172, 9, '24GB RAM', '24576', 33.60, '2021-03-15 03:33:16', '2021-04-03 05:14:24'),
       (1173, 9, '25GB RAM', '25600', 35.00, '2021-03-15 03:33:16', '2021-04-03 05:14:30'),
       (1174, 9, '26GB RAM', '26624', 36.40, '2021-03-15 03:33:38', '2021-04-03 05:14:37'),
       (1175, 9, '27GB RAM', '27648', 37.80, '2021-03-15 03:33:38', '2021-04-03 05:14:44'),
       (1176, 9, '28GB RAM', '28672', 39.20, '2021-03-15 03:33:59', '2021-04-03 05:14:50'),
       (1177, 9, '29GB RAM', '29696', 40.60, '2021-03-15 03:33:59', '2021-04-03 05:14:57'),
       (1178, 9, '30GB RAM', '30720', 42.00, '2021-03-15 03:34:24', '2021-04-03 05:15:03'),
       (1179, 9, '31GB RAM', '31744', 43.40, '2021-03-15 03:34:24', '2021-04-03 05:15:10'),
       (1180, 9, '32GB RAM', '32768', 44.80, '2021-03-15 03:34:37', '2021-04-03 05:15:16'),
       (1181, 11, '20GB SSD', '20480', 1.00, '2021-03-15 03:36:54', '2021-03-15 03:36:54'),
       (1182, 11, '30GB SSD', '30720', 1.50, '2021-03-15 03:39:51', '2021-03-15 03:39:51'),
       (1183, 11, '40GB SSD', '40960', 2.00, '2021-03-15 03:39:51', '2021-03-15 03:39:51'),
       (1184, 11, '50GB SSD', '51200', 2.50, '2021-03-15 03:39:51', '2021-03-15 03:39:51'),
       (1185, 11, '60GB SSD', '61440', 3.00, '2021-03-15 03:39:51', '2021-03-15 03:39:51'),
       (1186, 11, '70GB SSD', '71680', 3.50, '2021-03-15 03:39:51', '2021-03-15 03:39:51'),
       (1187, 11, '80GB SSD', '81920', 4.00, '2021-03-15 03:39:51', '2021-03-15 03:39:51'),
       (1188, 11, '90GB SSD', '92160', 4.50, '2021-03-15 03:39:51', '2021-03-15 03:39:51'),
       (1189, 11, '100GB SSD', '102400', 5.00, '2021-03-15 03:39:51', '2021-03-15 03:39:51'),
       (1190, 11, '110GB SSD', '112640', 5.50, '2021-03-15 03:39:51', '2021-03-15 03:39:51'),
       (1191, 11, '120GB SSD', '122880', 6.00, '2021-03-15 03:39:51', '2021-03-15 03:39:51'),
       (1192, 11, '130GB SSD', '133120', 6.50, '2021-03-15 03:39:51', '2021-03-15 03:39:51'),
       (1193, 11, '140GB SSD', '143360', 7.00, '2021-03-15 03:39:51', '2021-03-15 03:39:51'),
       (1194, 11, '150GB SSD', '153600', 7.50, '2021-03-15 03:39:51', '2021-03-15 03:39:51'),
       (1195, 10, '2 Kerne', '2', 2.00, '2021-03-15 03:42:51', '2021-03-29 22:27:55'),
       (1196, 10, '3 Kerne', '3', 3.00, '2021-03-15 03:42:51', '2021-03-29 22:27:57'),
       (1197, 10, '4 Kerne', '4', 4.00, '2021-03-15 03:42:51', '2021-03-29 22:27:58'),
       (1198, 10, '5 Kerne', '5', 5.00, '2021-03-15 03:42:51', '2021-03-29 22:28:02'),
       (1199, 10, '6 Kerne', '6', 6.00, '2021-03-15 03:42:51', '2021-03-29 22:28:05'),
       (1200, 10, '7 Kerne', '7', 7.00, '2021-03-15 03:42:51', '2021-03-29 22:28:09'),
       (1201, 10, '8 Kerne', '8', 8.00, '2021-03-15 03:42:51', '2021-03-29 22:28:13'),
       (1202, 10, '9 Kerne', '9', 9.00, '2021-03-15 03:42:51', '2021-03-29 22:28:20'),
       (1203, 10, '10 Kerne', '10', 10.00, '2021-03-15 03:42:51', '2021-03-29 22:28:24'),
       (1204, 10, '11 Kerne', '11', 11.00, '2021-03-15 03:42:51', '2021-03-29 22:28:27'),
       (1205, 10, '12 Kerne', '12', 12.00, '2021-03-15 03:42:51', '2021-03-29 22:28:30');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `product_prices`
--

CREATE TABLE `product_prices`
(
    `id`          int(11) NOT NULL,
    `name`        varchar(255)   NOT NULL,
    `price`       decimal(12, 2) NOT NULL,
    `created_at`  datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updadted_at` datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Daten für Tabelle `product_prices`
--

INSERT INTO `product_prices` (`id`, `name`, `price`, `created_at`, `updadted_at`) VALUES
(1, 'TEAMSPEAK', 0.16, '2021-05-26 01:49:18', '2021-10-31 06:55:11');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `pterodactyl_servers`
--

CREATE TABLE `pterodactyl_servers`
(
    `id`            int(11) NOT NULL,
    `user_id`       int(11) NOT NULL,
    `service_id`    varchar(255)   NOT NULL,
    `uuid`          varchar(255)   NOT NULL,
    `identifier`    varchar(255)   NOT NULL,
    `state`         enum('active','suspended','deleted') NOT NULL,
    `memory`        int(255) NOT NULL,
    `cpu`           varchar(255)   NOT NULL,
    `disk`          varchar(255)   NOT NULL,
    `allocation_id` varchar(255)   NOT NULL,
    `price`         decimal(12, 2) NOT NULL,
    `locked`        text,
    `custom_name`   text,
    `expire_at`     datetime       NOT NULL,
    `created_at`    datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    datetime                DEFAULT NULL,
    `days`          int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `queue`
--

CREATE TABLE `queue`
(
    `id`         int(11) NOT NULL,
    `user_id`    int(11) DEFAULT NULL,
    `payload`    longtext,
    `retries`    int(11) NOT NULL DEFAULT '0',
    `error_log`  longtext,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `settings`
--

CREATE TABLE `settings`
(
    `login`                 int(11) NOT NULL DEFAULT '1',
    `register`              int(11) NOT NULL DEFAULT '1',
    `webspace`              int(11) NOT NULL DEFAULT '1',
    `teamspeak`             int(11) NOT NULL DEFAULT '1',
    `vps`                   int(11) NOT NULL DEFAULT '1',
    `psc_fees`              int(5) NOT NULL DEFAULT '0',
    `default_traffic_limit` int(11) NOT NULL DEFAULT '1000',
    `rootserver`            enum('own','venocix') NOT NULL DEFAULT 'venocix'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `settings` (`login`, `register`, `webspace`, `teamspeak`, `vps`, `psc_fees`, `default_traffic_limit`,
                        `rootserver`)
VALUES (1, 1, 1, 1, 1, 0, 1000, 'venocix');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `teamspeaks`
--

CREATE TABLE `teamspeaks`
(
    `id`             int(11) NOT NULL,
    `user_id`        int(11) NOT NULL,
    `slots`          int(11) NOT NULL,
    `node_id`        int(11) NOT NULL,
    `teamspeak_ip`   varchar(255)   NOT NULL,
    `teamspeak_port` varchar(255)   NOT NULL,
    `sid`            int(11) NOT NULL,
    `expire_at`      datetime       NOT NULL,
    `price`          decimal(12, 2) NOT NULL,
    `state`          enum('ACTIVE','SUSPENDED','DELETED') NOT NULL,
    `custom_name`    varchar(255)            DEFAULT NULL,
    `locked`         text,
    `created_at`     datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     datetime                DEFAULT NULL,
    `days`           int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `teamspeak_backups`
--

CREATE TABLE `teamspeak_backups`
(
    `id`           int(11) NOT NULL,
    `user_id`      int(11) NOT NULL,
    `teamspeak_id` int(11) NOT NULL,
    `files`        longtext NOT NULL,
    `desc`         text,
    `created_at`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `teamspeak_hosts`
--

CREATE TABLE `teamspeak_hosts`
(
    `id`             int(11) NOT NULL,
    `name`           varchar(255) NOT NULL,
    `login_ip`       varchar(255) NOT NULL,
    `login_port`     varchar(255) NOT NULL,
    `login_name`     varchar(255) NOT NULL,
    `login_passwort` varchar(255) NOT NULL,
    `status`         enum('ACTIVE','DISABLED') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tickets`
--

CREATE TABLE `tickets`
(
    `id`         int(11) NOT NULL,
    `user_id`    int(11) NOT NULL,
    `categorie`  enum('ALLGEMEIN','TECHNIK','BUCHHALTUNG','PARTNER','FEEDBACK','AUSFALL','BUGS') NOT NULL,
    `priority`   enum('NIEDRIG','MITTEL','HOCH') NOT NULL,
    `title`      varchar(255) NOT NULL,
    `state`      enum('OPEN','CLOSED') NOT NULL,
    `last_msg`   enum('CUSTOMER','SUPPORT') NOT NULL,
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `ticket_message`
--

CREATE TABLE `ticket_message`
(
    `id`         int(11) NOT NULL,
    `ticket_id`  int(11) NOT NULL,
    `writer_id`  int(11) NOT NULL,
    `message`    longtext NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `transactions`
--

CREATE TABLE `transactions`
(
    `id`         int(11) NOT NULL,
    `user_id`    int(11) NOT NULL,
    `gateway`    varchar(255)   NOT NULL,
    `state`      enum('pending','success','abort') NOT NULL,
    `amount`     decimal(12, 2) NOT NULL,
    `desc`       varchar(255)   NOT NULL,
    `tid`        varchar(255)            DEFAULT NULL,
    `created_at` datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE `users`
(
    `id`                   int(11) NOT NULL,
    `username`             varchar(255)   NOT NULL,
    `email`                varchar(255)   NOT NULL,
    `password`             varchar(255)   NOT NULL,
    `state`                enum('pending','active','banned') NOT NULL,
    `role`                 enum('customer','support','admin') NOT NULL,
    `amount`               decimal(12, 2) NOT NULL DEFAULT '0.00',
    `session_token`        varchar(255)            DEFAULT NULL,
    `verify_code`          varchar(255)            DEFAULT NULL,
    `user_addr`            varchar(255)            DEFAULT NULL,
    `plesk_uid`            varchar(255)            DEFAULT NULL,
    `plesk_password`       varchar(255)            DEFAULT NULL,
    `s_pin`                varchar(255)            DEFAULT NULL,
    `datasavingmode`       int(11) NOT NULL DEFAULT '0',
    `darkmode`             int(11) NOT NULL DEFAULT '1',
    `notes`                longtext,
    `livechat`             int(11) NOT NULL DEFAULT '1',
    `preloader`            int(11) NOT NULL DEFAULT '1',
    `legal_accepted`       int(11) NOT NULL DEFAULT '0',
    `firstname`            varchar(255)            DEFAULT NULL,
    `lastname`             varchar(255)            DEFAULT NULL,
    `street`               varchar(255)            DEFAULT NULL,
    `number`               varchar(255)            DEFAULT NULL,
    `postcode`             varchar(255)            DEFAULT NULL,
    `city`                 varchar(255)            DEFAULT NULL,
    `country`              varchar(255)            DEFAULT NULL,
    `discord_id`           varchar(255)            DEFAULT NULL,
    `cashbox`              enum('active','inactive') NOT NULL DEFAULT 'inactive',
    `projectname`          varchar(512)            DEFAULT NULL,
    `projectlogo`          varchar(512)            DEFAULT NULL,
    `mail_ticket`          int(11) NOT NULL DEFAULT '1',
    `mail_runtime`         int(11) NOT NULL DEFAULT '1',
    `mail_suspend`         int(11) NOT NULL DEFAULT '1',
    `mail_order`           int(11) NOT NULL DEFAULT '1',
    `pterodactyl_id`       varchar(255)            DEFAULT NULL,
    `pterodactyl_password` varchar(255)            DEFAULT NULL,
    `created_at`           datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_transactions`
--

CREATE TABLE `user_transactions`
(
    `id`         int(11) NOT NULL,
    `user_id`    int(11) NOT NULL,
    `amount`     decimal(12, 2) NOT NULL,
    `desc`       varchar(255)   NOT NULL,
    `created_at` datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_host_nodes`
--

CREATE TABLE `vm_host_nodes`
(
    `id`            int(11) NOT NULL,
    `hostname`      varchar(255) NOT NULL,
    `name`          varchar(255) NOT NULL,
    `username`      varchar(255) NOT NULL,
    `password`      varchar(255) NOT NULL,
    `root_password` varchar(512)          DEFAULT NULL,
    `realm`         varchar(255) NOT NULL,
    `state`         enum('ACTIVE','DISABLED') NOT NULL,
    `disc_name`     varchar(255) NOT NULL,
    `disc_type`     enum('ssd','hdd') NOT NULL,
    `api_name`      enum('NO_API','PLOCIC','VENOCIX','GAME') NOT NULL,
    `active`        enum('yes','no') NOT NULL,
    `type`          enum('LXC','KVM') NOT NULL DEFAULT 'LXC',
    `created_at`    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_servers`
--

CREATE TABLE `vm_servers`
(
    `id`           int(11) NOT NULL,
    `user_id`      int(11) NOT NULL,
    `hostname`     varchar(255)            DEFAULT NULL,
    `password`     varchar(255)            DEFAULT NULL,
    `template_id`  varchar(512)   NOT NULL,
    `node_id`      int(11) NOT NULL,
    `cores`        int(11) NOT NULL,
    `memory`       int(11) NOT NULL,
    `disc`         int(11) NOT NULL,
    `addresses`    int(11) NOT NULL,
    `network`      varchar(255)            DEFAULT NULL,
    `price`        decimal(43, 2) NOT NULL,
    `state`        enum('ACTIVE','DISABLED','SUSPENDED','DELETED','PENDING') NOT NULL,
    `custom_name`  varchar(255)            DEFAULT NULL,
    `locked`       text,
    `expire_at`    datetime       NOT NULL,
    `disc_name`    varchar(255)            DEFAULT NULL,
    `traffic`      int(11) DEFAULT NULL,
    `curr_traffic` varchar(255)            DEFAULT NULL,
    `api_name`     enum('NO_API','PLOCIC','VENOCIX','GAME') DEFAULT NULL,
    `pack_name`    varchar(255)            DEFAULT NULL,
    `created_at`   datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   datetime                DEFAULT NULL,
    `days`         int(11) DEFAULT NULL,
    `type`         enum('LXC','KVM') NOT NULL DEFAULT 'LXC',
    `notes`        text,
    `job_id`       int(11) DEFAULT NULL,
    `venocix_id`   text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_server_command_presets`
--

CREATE TABLE `vm_server_command_presets`
(
    `id`         int(11) NOT NULL,
    `server_id`  int(11) NOT NULL,
    `desc`       text         NOT NULL,
    `command`    text         NOT NULL,
    `icon`       varchar(255) NOT NULL,
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_server_os`
--

CREATE TABLE `vm_server_os`
(
    `id`         int(11) NOT NULL,
    `name`       varchar(255) NOT NULL,
    `template`   varchar(255) NOT NULL,
    `type`       enum('LXC','KVM','VENOCIX') NOT NULL DEFAULT 'LXC',
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_server_packs`
--

CREATE TABLE `vm_server_packs`
(
    `id`         int(11) NOT NULL,
    `type`       enum('normal','game') NOT NULL DEFAULT 'normal',
    `name`       varchar(255)   NOT NULL,
    `cores`      varchar(255)   NOT NULL,
    `memory`     varchar(255)   NOT NULL,
    `disk`       varchar(255)   NOT NULL,
    `price`      decimal(12, 2) NOT NULL,
    `created_at` datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_software`
--

CREATE TABLE `vm_software`
(
    `id`         int(11) NOT NULL,
    `name`       varchar(512) NOT NULL,
    `url`        varchar(512) NOT NULL,
    `file_name`  varchar(512) NOT NULL,
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_software_tasks`
--

CREATE TABLE `vm_software_tasks`
(
    `id`         int(11) NOT NULL,
    `vm_id`      int(11) NOT NULL,
    `type`       varchar(512) NOT NULL,
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_tasks`
--

CREATE TABLE `vm_tasks`
(
    `id`         int(11) NOT NULL,
    `service_id` int(11) NOT NULL,
    `task`       text,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `webspace`
--

CREATE TABLE `webspace`
(
    `id`           int(11) NOT NULL,
    `plan_id`      varchar(255)   NOT NULL,
    `user_id`      int(11) NOT NULL,
    `ftp_name`     varchar(255)   NOT NULL,
    `ftp_password` varchar(255)   NOT NULL,
    `domainName`   varchar(255)   NOT NULL,
    `webspace_id`  int(11) NOT NULL,
    `state`        enum('active','suspended','deleted') NOT NULL,
    `expire_at`    datetime       NOT NULL,
    `price`        decimal(12, 2) NOT NULL,
    `custom_name`  varchar(255)            DEFAULT NULL,
    `locked`       text,
    `created_at`   datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   datetime                DEFAULT NULL,
    `days`         int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `webspace_host`
--

CREATE TABLE `webspace_host`
(
    `id`         int(11) NOT NULL,
    `domainName` varchar(255) NOT NULL,
    `ip`         varchar(255) NOT NULL,
    `name`       varchar(255) NOT NULL,
    `password`   varchar(255) NOT NULL,
    `state`      enum('offline','online') NOT NULL,
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `webspace_packs`
--

CREATE TABLE `webspace_packs`
(
    `id`           int(11) NOT NULL,
    `plesk_id`     varchar(255)   NOT NULL,
    `name`         varchar(255)   NOT NULL,
    `desc`         text,
    `price`        decimal(12, 2) NOT NULL,
    `disc`         varchar(255)   NOT NULL,
    `domains`      varchar(255)   NOT NULL,
    `subdomains`   varchar(255)   NOT NULL,
    `databases`    varchar(255)   NOT NULL,
    `ftp_accounts` varchar(255)   NOT NULL,
    `emails`       varchar(255)   NOT NULL,
    `created_at`   datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `codes`
--
ALTER TABLE `codes`
    ADD PRIMARY KEY (`id`);
--
-- Indizes für die Tabelle `code_used`
--
ALTER TABLE `code_used`
    ADD PRIMARY KEY (`id`);
--
-- Indizes für die Tabelle `cashbox_clicks`
--
ALTER TABLE `cashbox_clicks`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `ip_addresses`
--
ALTER TABLE `ip_addresses`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mac` (`mac_address`);

--
-- Indizes für die Tabelle `login_logs`
--
ALTER TABLE `login_logs`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `news`
--
ALTER TABLE `news`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `password_resets`
--
ALTER TABLE `password_resets`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `products`
--
ALTER TABLE `products`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `product_options`
--
ALTER TABLE `product_options`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `product_option_entries`
--
ALTER TABLE `product_option_entries`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `product_prices`
--
ALTER TABLE `product_prices`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `pterodactyl_servers`
--
ALTER TABLE `pterodactyl_servers`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `queue`
--
ALTER TABLE `queue`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `teamspeaks`
--
ALTER TABLE `teamspeaks`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `teamspeak_backups`
--
ALTER TABLE `teamspeak_backups`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `teamspeak_hosts`
--
ALTER TABLE `teamspeak_hosts`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `tickets`
--
ALTER TABLE `tickets`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_2` (`id`),
  ADD KEY `id` (`id`);

--
-- Indizes für die Tabelle `ticket_message`
--
ALTER TABLE `ticket_message`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `transactions`
--
ALTER TABLE `transactions`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `user_transactions`
--
ALTER TABLE `user_transactions`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `vm_host_nodes`
--
ALTER TABLE `vm_host_nodes`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `vm_servers`
--
ALTER TABLE `vm_servers`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `vm_server_command_presets`
--
ALTER TABLE `vm_server_command_presets`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `vm_server_os`
--
ALTER TABLE `vm_server_os`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `vm_server_packs`
--
ALTER TABLE `vm_server_packs`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `vm_software`
--
ALTER TABLE `vm_software`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `vm_software_tasks`
--
ALTER TABLE `vm_software_tasks`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `vm_tasks`
--
ALTER TABLE `vm_tasks`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `webspace`
--
ALTER TABLE `webspace`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `webspace_host`
--
ALTER TABLE `webspace_host`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `webspace_packs`
--
ALTER TABLE `webspace_packs`
    ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `cashbox_clicks`
--
ALTER TABLE `cashbox_clicks`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `ip_addresses`
--
ALTER TABLE `ip_addresses`
    MODIFY `id` int (110) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `login_logs`
--
ALTER TABLE `login_logs`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `news`
--
ALTER TABLE `news`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `password_resets`
--
ALTER TABLE `password_resets`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `products`
--
ALTER TABLE `products`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `product_options`
--
ALTER TABLE `product_options`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `product_option_entries`
--
ALTER TABLE `product_option_entries`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `product_prices`
--
ALTER TABLE `product_prices`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `pterodactyl_servers`
--
ALTER TABLE `pterodactyl_servers`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `queue`
--
ALTER TABLE `queue`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `teamspeaks`
--
ALTER TABLE `teamspeaks`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `teamspeak_backups`
--
ALTER TABLE `teamspeak_backups`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `teamspeak_hosts`
--
ALTER TABLE `teamspeak_hosts`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tickets`
--
ALTER TABLE `tickets`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `ticket_message`
--
ALTER TABLE `ticket_message`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `transactions`
--
ALTER TABLE `transactions`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `user_transactions`
--
ALTER TABLE `user_transactions`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_host_nodes`
--
ALTER TABLE `vm_host_nodes`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_servers`
--
ALTER TABLE `vm_servers`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_server_command_presets`
--
ALTER TABLE `vm_server_command_presets`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_server_os`
--
ALTER TABLE `vm_server_os`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_server_packs`
--
ALTER TABLE `vm_server_packs`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_software`
--
ALTER TABLE `vm_software`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_software_tasks`
--
ALTER TABLE `vm_software_tasks`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_tasks`
--
ALTER TABLE `vm_tasks`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `webspace`
--
ALTER TABLE `webspace`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `webspace_host`
--
ALTER TABLE `webspace_host`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `webspace_packs`
--
ALTER TABLE `webspace_packs`
    MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
