-- PollPHP Database Schema
-- Database: gvxphp
-- Character Set: utf8mb4 / Unicode

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `votes`;
DROP TABLE IF EXISTS `poll_options`;
DROP TABLE IF EXISTS `polls`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NULL,
    `auth_provider` ENUM('local', 'google', 'firebase') NOT NULL DEFAULT 'local',
    `firebase_uid` VARCHAR(128) NULL UNIQUE,
    `role` ENUM('creator', 'admin') NOT NULL DEFAULT 'creator',
    `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Polls Table
CREATE TABLE `polls` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `slug` VARCHAR(64) NOT NULL UNIQUE,
    `question` VARCHAR(500) NOT NULL,
    `description` TEXT NULL,
    `duration_days` TINYINT UNSIGNED NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `status` ENUM('active', 'suspended', 'closed') NOT NULL DEFAULT 'active',
    `results_published` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `total_votes` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_polls_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_polls_status_expires` (`status`, `expires_at`),
    INDEX `idx_polls_user` (`user_id`),
    INDEX `idx_polls_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Poll Options Table
CREATE TABLE `poll_options` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `poll_id` INT UNSIGNED NOT NULL,
    `option_text` VARCHAR(255) NOT NULL,
    `vote_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT `fk_options_poll` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE,
    INDEX `idx_options_poll` (`poll_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Votes Table (Anonymous Duplicate Protection)
CREATE TABLE `votes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `poll_id` INT UNSIGNED NOT NULL,
    `option_id` INT UNSIGNED NOT NULL,
    `voter_identifier` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_votes_poll` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_votes_option` FOREIGN KEY (`option_id`) REFERENCES `poll_options` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_poll_voter` (`poll_id`, `voter_identifier`),
    INDEX `idx_votes_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Seed Initial Default System Administrator
-- Email: admin@pollphp.local
-- Initial Password: Admin@PollPHP2026!
INSERT INTO `users` (`name`, `email`, `password_hash`, `auth_provider`, `role`, `status`)
VALUES (
    'System Admin',
    'admin@pollphp.local',
    '$2y$10$pu4nIZ4BXvfSEbziT9G52uFDgYZdh1WypFNTcv7XVUfUlLlg7w4cm',
    'local',
    'admin',
    'active'
);
