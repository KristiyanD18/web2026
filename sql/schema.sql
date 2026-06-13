-- Система за Входиране на Документи
-- Database Schema
-- Encoding: UTF-8

SET NAMES utf8mb4;
SET time_zone = '+02:00';

CREATE DATABASE IF NOT EXISTS `docreg`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `docreg`;

-- ─────────────────────────────────────────────────────────────────────────────
-- USERS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `username`      VARCHAR(50)  NOT NULL UNIQUE,
    `email`         VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name`     VARCHAR(100) NOT NULL,
    `role`          ENUM('admin','officer') NOT NULL DEFAULT 'officer',
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- CATEGORIES
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `name`            VARCHAR(100) NOT NULL,
    `description`     TEXT,
    `officer_user_id` INT DEFAULT NULL,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`officer_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- DOCUMENTS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `documents` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `incoming_number`   VARCHAR(30)  NOT NULL UNIQUE,
    `title`             VARCHAR(255) NOT NULL,
    `description`       TEXT,
    `original_filename` VARCHAR(255) NOT NULL,
    `stored_filename`   VARCHAR(255) NOT NULL,
    `file_type`         ENUM('pdf','zip') NOT NULL,
    `file_size`         BIGINT NOT NULL DEFAULT 0,
    `category_id`       INT DEFAULT NULL,
    `status`            ENUM('pending','in_progress','completed','paused','archived') NOT NULL DEFAULT 'pending',
    `priority`          ENUM('normal','high') NOT NULL DEFAULT 'normal',
    `access_code`       VARCHAR(12) NOT NULL,
    `qr_filename`       VARCHAR(255) DEFAULT NULL,
    `submitter_name`        VARCHAR(100) NOT NULL,
    `submitter_email`       VARCHAR(100) DEFAULT NULL,
    `submitter_phone`       VARCHAR(30)  DEFAULT NULL,
    `submitted_by_user_id`  INT DEFAULT NULL,
    `officer_notes`         TEXT DEFAULT NULL,
    `is_encrypted`      TINYINT(1) NOT NULL DEFAULT 0,
    `submitted_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`submitted_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- DOCUMENT STATUS HISTORY
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `document_history` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `document_id`      INT NOT NULL,
    `old_status`       VARCHAR(20) DEFAULT NULL,
    `new_status`       VARCHAR(20) NOT NULL,
    `changed_by_id`    INT DEFAULT NULL,
    `changed_by_name`  VARCHAR(100) DEFAULT NULL,
    `notes`            TEXT DEFAULT NULL,
    `changed_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`changed_by_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- ACCESS LOG
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `access_log` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `document_id`      INT NOT NULL,
    `user_id`          INT DEFAULT NULL,
    `session_token`    VARCHAR(64) DEFAULT NULL,
    `ip_address`       VARCHAR(45) DEFAULT NULL,
    `access_type`      ENUM('view','download','status_check','decrypt_attempt','decrypt_success') NOT NULL,
    `accessed_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `closed_at`        TIMESTAMP NULL DEFAULT NULL,
    `duration_seconds` INT DEFAULT 0,
    `success`          TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- ENCRYPTION
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `document_encryptions` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `document_id` INT NOT NULL UNIQUE,
    `num_parts`   INT NOT NULL DEFAULT 2,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `encryption_key_parts` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `encryption_id`  INT NOT NULL,
    `part_index`     INT NOT NULL,
    `holder_user_id` INT DEFAULT NULL,
    `holder_name`    VARCHAR(100) DEFAULT NULL,
    -- part is stored encrypted with SHA-256(holder_password) as key
    `encrypted_part` TEXT NOT NULL,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`encryption_id`) REFERENCES `document_encryptions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`holder_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- SETTINGS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key`   VARCHAR(50) PRIMARY KEY,
    `setting_value` TEXT,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- SEED DATA
-- ─────────────────────────────────────────────────────────────────────────────

-- Default admin: admin / Admin1234!
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role`) VALUES
('admin', 'admin@docreg.local', '$2y$12$YrNzjL0b7T0W.44utSen3upHUbsppd1vzrM/qoQYmfXTeWSTNmpfS', 'Системен Администратор', 'admin');

-- Hash above = password_hash("Admin1234!", PASSWORD_BCRYPT)

-- Categories
INSERT INTO `categories` (`name`, `description`, `is_active`) VALUES
('Отдел Студенти',          'Документи за студентски въпроси, уверения, преводи', 1),
('Учебен отдел Магистри',   'Документи за магистърски програми', 1),
('Кандидат-студенти',       'Документи от кандидат-студенти', 1),
('Сесия',                   'Документи свързани с изпитни сесии', 1),
('Без категория',           'Некатегоризирани документи', 1);

-- Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('doc_counter', '0'),
('doc_year',    YEAR(NOW())),
('site_title',  'Система за Входиране на Документи'),
('max_key_parts', '5');
