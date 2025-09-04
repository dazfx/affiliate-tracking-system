-- Affiliate Tracking System Database Schema
-- Version: 2.0.0
-- Date: 2025-09-01

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `affiliate_tracking` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `affiliate_tracking`;

-- --------------------------------------------------------

--
-- Table structure for table `partners`
--

CREATE TABLE `partners` (
  `id` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `target_domain` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `clickid_keys` json DEFAULT NULL,
  `sum_keys` json DEFAULT NULL,
  `sum_mapping` json DEFAULT NULL,
  `logging_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `telegram_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `telegram_whitelist_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `telegram_whitelist_keywords` json DEFAULT NULL,
  `ip_whitelist_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `allowed_ips` json DEFAULT NULL,
  `partner_telegram_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `partner_telegram_bot_token` varchar(255) DEFAULT NULL,
  `partner_telegram_channel_id` varchar(255) DEFAULT NULL,
  `google_sheet_name` varchar(255) DEFAULT NULL,
  `google_spreadsheet_id` varchar(255) DEFAULT NULL,
  `google_service_account_json` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detailed_stats`
--

CREATE TABLE `detailed_stats` (
  `id` int(11) NOT NULL,
  `partner_id` varchar(100) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `click_id` varchar(255) DEFAULT NULL,
  `url` text DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `response` text DEFAULT NULL,
  `sum` decimal(10,2) DEFAULT NULL,
  `sum_mapping` decimal(10,2) DEFAULT NULL,
  `extra_params` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `summary_stats`
--

CREATE TABLE `summary_stats` (
  `partner_id` varchar(100) NOT NULL,
  `total_requests` int(11) NOT NULL DEFAULT 0,
  `successful_redirects` int(11) NOT NULL DEFAULT 0,
  `errors` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `postback_queue`
--

CREATE TABLE `postback_queue` (
  `id` int(11) NOT NULL,
  `partner_id` varchar(100) NOT NULL,
  `data` json NOT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Indexes for dumped tables
--

--
-- Indexes for table `partners`
--
ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `detailed_stats`
--
ALTER TABLE `detailed_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `partner_id` (`partner_id`),
  ADD KEY `timestamp` (`timestamp`),
  ADD KEY `click_id` (`click_id`);

--
-- Indexes for table `summary_stats`
--
ALTER TABLE `summary_stats`
  ADD PRIMARY KEY (`partner_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `postback_queue`
--
ALTER TABLE `postback_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `partner_id` (`partner_id`),
  ADD KEY `status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `detailed_stats`
--
ALTER TABLE `detailed_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `postback_queue`
--
ALTER TABLE `postback_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `detailed_stats`
--
ALTER TABLE `detailed_stats`
  ADD CONSTRAINT `detailed_stats_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `summary_stats`
--
ALTER TABLE `summary_stats`
  ADD CONSTRAINT `summary_stats_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `postback_queue`
--
ALTER TABLE `postback_queue`
  ADD CONSTRAINT `postback_queue_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE;
COMMIT;