-- phpMyAdmin SQL Dump
-- version 5.0.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Erstellungszeit: 02. Apr 2021 um 02:11
-- Server-Version: 10.1.48-MariaDB-0+deb9u2
-- PHP-Version: 7.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


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

CREATE TABLE `cashbox_clicks` (
  `id` int(11) NOT NULL,
  `box_id` varchar(255) NOT NULL,
  `ip_addr` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `ip_addresses`
--

CREATE TABLE `ip_addresses` (
  `id` int(110) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `service_type` enum('VPS') DEFAULT NULL,
  `node_id` varchar(512) DEFAULT NULL,
  `ip` varchar(255) NOT NULL,
  `cidr` int(11) NOT NULL,
  `gateway` varchar(255) NOT NULL,
  `mac_address` varchar(255) DEFAULT NULL,
  `rdns` varchar(512) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_addr` varchar(255) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `show` int(11) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `title` varchar(512) NOT NULL,
  `text` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_info` varchar(255) NOT NULL,
  `key` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `product_options`
--

CREATE TABLE `product_options` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `product_option_entries`
--

CREATE TABLE `product_option_entries` (
  `id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `price` decimal(43,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `product_prices`
--

CREATE TABLE `product_prices` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updadted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `pterodactyl_servers`
--

CREATE TABLE `pterodactyl_servers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `service_id` varchar(255) NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `state` enum('active','suspended','deleted') NOT NULL,
  `memory` int(255) NOT NULL,
  `cpu` varchar(255) NOT NULL,
  `disk` varchar(255) NOT NULL,
  `allocation_id` varchar(255) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `locked` text,
  `custom_name` text,
  `expire_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `days` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `queue`
--

CREATE TABLE `queue` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `payload` longtext,
  `retries` int(11) NOT NULL DEFAULT '0',
  `error_log` longtext,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `settings`
--

CREATE TABLE `settings` (
  `login` int(11) NOT NULL,
  `register` int(11) NOT NULL,
  `webspace` int(11) NOT NULL DEFAULT '1',
  `teamspeak` int(11) NOT NULL DEFAULT '1',
  `vps` int(11) NOT NULL DEFAULT '1',
  `psc_fees` int(5) NOT NULL DEFAULT '0',
  `default_traffic_limit` int(11) NOT NULL DEFAULT '1000',
  `rootserver` enum('own','venocix') NOT NULL DEFAULT 'venocix'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `teamspeaks`
--

CREATE TABLE `teamspeaks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `slots` int(11) NOT NULL,
  `node_id` int(11) NOT NULL,
  `teamspeak_ip` varchar(255) NOT NULL,
  `teamspeak_port` varchar(255) NOT NULL,
  `sid` int(11) NOT NULL,
  `expire_at` datetime NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `state` enum('ACTIVE','SUSPENDED','DELETED') NOT NULL,
  `custom_name` varchar(255) DEFAULT NULL,
  `locked` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `days` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `teamspeak_backups`
--

CREATE TABLE `teamspeak_backups` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `teamspeak_id` int(11) NOT NULL,
  `files` longtext NOT NULL,
  `desc` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `teamspeak_hosts`
--

CREATE TABLE `teamspeak_hosts` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `login_ip` varchar(255) NOT NULL,
  `login_port` varchar(255) NOT NULL,
  `login_name` varchar(255) NOT NULL,
  `login_passwort` varchar(255) NOT NULL,
  `status` enum('ACTIVE','DISABLED') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `categorie` enum('ALLGEMEIN','TECHNIK','BUCHHALTUNG','PARTNER','FEEDBACK','AUSFALL','BUGS') NOT NULL,
  `priority` enum('NIEDRIG','MITTEL','HOCH') NOT NULL,
  `title` varchar(255) NOT NULL,
  `state` enum('OPEN','CLOSED') NOT NULL,
  `last_msg` enum('CUSTOMER','SUPPORT') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `ticket_message`
--

CREATE TABLE `ticket_message` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `writer_id` int(11) NOT NULL,
  `message` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `gateway` varchar(255) NOT NULL,
  `state` enum('pending','success','abort') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `desc` varchar(255) NOT NULL,
  `tid` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `state` enum('pending','active','banned') NOT NULL,
  `role` enum('customer','support','admin') NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `session_token` varchar(255) DEFAULT NULL,
  `verify_code` varchar(255) DEFAULT NULL,
  `user_addr` varchar(255) DEFAULT NULL,
  `plesk_uid` varchar(255) DEFAULT NULL,
  `plesk_password` varchar(255) DEFAULT NULL,
  `s_pin` varchar(255) DEFAULT NULL,
  `datasavingmode` int(11) NOT NULL DEFAULT '0',
  `darkmode` int(11) NOT NULL DEFAULT '1',
  `notes` longtext,
  `livechat` int(11) NOT NULL DEFAULT '1',
  `preloader` int(11) NOT NULL DEFAULT '1',
  `legal_accepted` int(11) NOT NULL DEFAULT '0',
  `firstname` varchar(255) DEFAULT NULL,
  `lastname` varchar(255) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `number` varchar(255) DEFAULT NULL,
  `postcode` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `discord_id` varchar(255) DEFAULT NULL,
  `cashbox` enum('active','inactive') NOT NULL DEFAULT 'inactive',
  `projectname` varchar(512) DEFAULT NULL,
  `projectlogo` varchar(512) DEFAULT NULL,
  `mail_ticket` int(11) NOT NULL DEFAULT '1',
  `mail_runtime` int(11) NOT NULL DEFAULT '1',
  `mail_suspend` int(11) NOT NULL DEFAULT '1',
  `mail_order` int(11) NOT NULL DEFAULT '1',
  `pterodactyl_id` varchar(255) DEFAULT NULL,
  `pterodactyl_password` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_transactions`
--

CREATE TABLE `user_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `desc` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_host_nodes`
--

CREATE TABLE `vm_host_nodes` (
  `id` int(11) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `root_password` varchar(512) DEFAULT NULL,
  `realm` varchar(255) NOT NULL,
  `state` enum('ACTIVE','DISABLED') NOT NULL,
  `disc_name` varchar(255) NOT NULL,
  `disc_type` enum('ssd','hdd') NOT NULL,
  `api_name` enum('NO_API','PLOCIC','VENOCIX','GAME') NOT NULL,
  `active` enum('yes','no') NOT NULL,
  `type` enum('LXC','KVM') NOT NULL DEFAULT 'LXC',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_servers`
--

CREATE TABLE `vm_servers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `hostname` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `template_id` varchar(512) NOT NULL,
  `node_id` int(11) NOT NULL,
  `cores` int(11) NOT NULL,
  `memory` int(11) NOT NULL,
  `disc` int(11) NOT NULL,
  `addresses` int(11) NOT NULL,
  `network` varchar(255) DEFAULT NULL,
  `price` decimal(43,2) NOT NULL,
  `state` enum('ACTIVE','DISABLED','SUSPENDED','DELETED','PENDING') NOT NULL,
  `custom_name` varchar(255) DEFAULT NULL,
  `locked` text,
  `expire_at` datetime NOT NULL,
  `disc_name` varchar(255) DEFAULT NULL,
  `traffic` int(11) DEFAULT NULL,
  `curr_traffic` varchar(255) DEFAULT NULL,
  `api_name` enum('NO_API','PLOCIC','VENOCIX','GAME') DEFAULT NULL,
  `pack_name` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `days` int(11) DEFAULT NULL,
  `type` enum('LXC','KVM') NOT NULL DEFAULT 'LXC',
  `notes` text,
  `job_id` int(11) DEFAULT NULL,
  `venocix_id` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_server_command_presets`
--

CREATE TABLE `vm_server_command_presets` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL,
  `desc` text NOT NULL,
  `command` text NOT NULL,
  `icon` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_server_os`
--

CREATE TABLE `vm_server_os` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `template` varchar(255) NOT NULL,
  `type` enum('LXC','KVM','VENOCIX') NOT NULL DEFAULT 'LXC',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_server_packs`
--

CREATE TABLE `vm_server_packs` (
  `id` int(11) NOT NULL,
  `type` enum('normal','game') NOT NULL DEFAULT 'normal',
  `name` varchar(255) NOT NULL,
  `cores` varchar(255) NOT NULL,
  `memory` varchar(255) NOT NULL,
  `disk` varchar(255) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_software`
--

CREATE TABLE `vm_software` (
  `id` int(11) NOT NULL,
  `name` varchar(512) NOT NULL,
  `url` varchar(512) NOT NULL,
  `file_name` varchar(512) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_software_tasks`
--

CREATE TABLE `vm_software_tasks` (
  `id` int(11) NOT NULL,
  `vm_id` int(11) NOT NULL,
  `type` varchar(512) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vm_tasks`
--

CREATE TABLE `vm_tasks` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `task` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `webspace`
--

CREATE TABLE `webspace` (
  `id` int(11) NOT NULL,
  `plan_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ftp_name` varchar(255) NOT NULL,
  `ftp_password` varchar(255) NOT NULL,
  `domainName` varchar(255) NOT NULL,
  `webspace_id` int(11) NOT NULL,
  `state` enum('active','suspended','deleted') NOT NULL,
  `expire_at` datetime NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `custom_name` varchar(255) DEFAULT NULL,
  `locked` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `days` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `webspace_host`
--

CREATE TABLE `webspace_host` (
  `id` int(11) NOT NULL,
  `domainName` varchar(255) NOT NULL,
  `ip` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `state` enum('offline','online') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `webspace_packs`
--

CREATE TABLE `webspace_packs` (
  `id` int(11) NOT NULL,
  `plesk_id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `desc` text,
  `price` decimal(12,2) NOT NULL,
  `disc` varchar(255) NOT NULL,
  `domains` varchar(255) NOT NULL,
  `subdomains` varchar(255) NOT NULL,
  `databases` varchar(255) NOT NULL,
  `ftp_accounts` varchar(255) NOT NULL,
  `emails` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indizes der exportierten Tabellen
--

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `ip_addresses`
--
ALTER TABLE `ip_addresses`
  MODIFY `id` int(110) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `product_options`
--
ALTER TABLE `product_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `product_option_entries`
--
ALTER TABLE `product_option_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `product_prices`
--
ALTER TABLE `product_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `pterodactyl_servers`
--
ALTER TABLE `pterodactyl_servers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `queue`
--
ALTER TABLE `queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `teamspeaks`
--
ALTER TABLE `teamspeaks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `teamspeak_backups`
--
ALTER TABLE `teamspeak_backups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `teamspeak_hosts`
--
ALTER TABLE `teamspeak_hosts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `ticket_message`
--
ALTER TABLE `ticket_message`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `user_transactions`
--
ALTER TABLE `user_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_host_nodes`
--
ALTER TABLE `vm_host_nodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_servers`
--
ALTER TABLE `vm_servers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_server_command_presets`
--
ALTER TABLE `vm_server_command_presets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_server_os`
--
ALTER TABLE `vm_server_os`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_server_packs`
--
ALTER TABLE `vm_server_packs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_software`
--
ALTER TABLE `vm_software`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_software_tasks`
--
ALTER TABLE `vm_software_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vm_tasks`
--
ALTER TABLE `vm_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `webspace`
--
ALTER TABLE `webspace`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `webspace_host`
--
ALTER TABLE `webspace_host`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `webspace_packs`
--
ALTER TABLE `webspace_packs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
