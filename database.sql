-- Create Database
CREATE DATABASE IF NOT EXISTS `tiny_togs_roster` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `tiny_togs_roster`;

-- Disable foreign key checks to allow dropping tables safely
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `monthly_roster`;
DROP TABLE IF EXISTS `leave_requests`;
DROP TABLE IF EXISTS `calendar_days`;
DROP TABLE IF EXISTS `shifts`;
DROP TABLE IF EXISTS `employees`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Employees Table
-- Stores details about employees, including their skill tier and special roles (Cashiers, Anchors)
CREATE TABLE IF NOT EXISTS `employees` (
    `emp_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `skill_level` ENUM('Good', 'Normal') NOT NULL DEFAULT 'Normal',
    `gender` ENUM('Male', 'Female') NOT NULL DEFAULT 'Female',
    `is_cashier` TINYINT(1) NOT NULL DEFAULT 0,
    `is_anchor` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Anchors work exclusively Full Day (F) shifts',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Shifts Table
-- Stores shift codes, names, timing, and color codes for visual rendering
CREATE TABLE IF NOT EXISTS `shifts` (
    `shift_code` VARCHAR(10) PRIMARY KEY,
    `shift_name` VARCHAR(50) NOT NULL,
    `start_time` TIME NULL COMMENT 'NULL for Off Day',
    `end_time` TIME NULL COMMENT 'NULL for Off Day',
    `color_hex` VARCHAR(7) NOT NULL COMMENT 'Hex code for visual rendering',
    `is_cashier_shift` TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Monthly Holidays Table
-- Stores manual admin overrides for calendar days (Weekday, Poya, or Public Holiday).
-- Overrides have higher priority than cached holidays or standard weekday/weekend logic.
CREATE TABLE IF NOT EXISTS `monthly_holidays` (
    `holiday_date` DATE PRIMARY KEY,
    `day_type` ENUM('Weekday', 'Poya', 'Public Holiday') NOT NULL,
    `description` VARCHAR(100) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3b. Cached Holidays Table
-- Caches holiday data retrieved from the external Sri Lankan holiday API to avoid redundant external requests.
CREATE TABLE IF NOT EXISTS `cached_holidays` (
    `holiday_date` DATE PRIMARY KEY,
    `day_type` ENUM('Poya', 'Public Holiday') NOT NULL,
    `description` VARCHAR(100) NULL,
    `fetched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 4. Leave Requests Table
-- Handles Phase 1 Pre-Generation leave planning
CREATE TABLE IF NOT EXISTS `leave_requests` (
    `request_id` INT AUTO_INCREMENT PRIMARY KEY,
    `emp_id` INT NOT NULL,
    `requested_date` DATE NOT NULL,
    `status` ENUM('Pending', 'Approved', 'Denied') NOT NULL DEFAULT 'Pending',
    FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_emp_date` (`emp_id`, `requested_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Monthly Roster Table
-- Holds the final generated timetable and records any post-generation emergency swaps (Phase 2)
CREATE TABLE IF NOT EXISTS `monthly_roster` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `emp_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `shift_code` VARCHAR(10) NOT NULL,
    `is_emergency_swap` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if modified post-generation',
    `swapped_with_emp_id` INT NULL COMMENT 'ID of employee replaced (if applicable)',
    FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE,
    FOREIGN KEY (`shift_code`) REFERENCES `shifts` (`shift_code`) ON UPDATE CASCADE,
    FOREIGN KEY (`swapped_with_emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_emp_date_roster` (`emp_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==========================================================
-- Seed Data Initialization
-- ==========================================================

-- Insert Shifts
INSERT INTO `shifts` (`shift_code`, `shift_name`, `start_time`, `end_time`, `color_hex`, `is_cashier_shift`) VALUES
('F',   'Full Day',         '08:30:00', '22:00:00', '#FF9800', 0), -- Orange/Yellow
('M',   'Morning',          '08:30:00', '17:30:00', '#03A9F4', 0), -- Light Blue
('N',   'Night',            '13:00:00', '22:00:00', '#F44336', 0), -- Red
('Mw',  'Morning Weekend',  '08:30:00', '20:30:00', '#00BCD4', 0), -- Cyan
('Nw',  'Night Weekend',    '11:00:00', '22:00:00', '#E91E63', 0), -- Magenta/Purple
('No',  'Normal Cashier',   '08:30:00', '19:30:00', '#FFFFFF', 1), -- White
('Nh',  'Night Cashier',    '10:30:00', '21:30:00', '#795548', 1), -- Dark Red/Brown
('Off', 'Off Day',          NULL,       NULL,       '#4CAF50', 0); -- Green

-- Insert Employees from the Manual Report Image
INSERT INTO `employees` (`name`, `skill_level`, `gender`, `is_cashier`, `is_anchor`) VALUES
('Kumara',    'Good',   'Male',   0, 0),
('Parami',    'Normal', 'Female', 1, 0), -- Cashier
('Rishani',   'Normal', 'Female', 0, 0),
('Nirasha',   'Good',   'Female', 0, 0),
('Sansala',   'Normal', 'Female', 0, 0),
('Pawani',    'Good',   'Female', 0, 1), -- Anchor
('Abdul',     'Good',   'Male',   0, 1), -- Anchor
('Deneth',    'Good',   'Male',   0, 0),
('Hiruni',    'Normal', 'Female', 0, 0),
('Thakshila', 'Normal', 'Female', 0, 0),
('Sanduni',   'Good',   'Female', 0, 0),
('Rashid',    'Good',   'Male',   0, 1), -- Anchor
('Dhanushka', 'Normal', 'Male',   0, 0),
('Anuhas',    'Good',   'Male',   0, 1); -- Anchor -- Anchor

-- Seed Exception Holidays for June 2026
-- Day 2 is a Public & Mercantile Holiday.
-- Day 29 is a Poya Day.
INSERT INTO `monthly_holidays` (`holiday_date`, `day_type`, `description`) VALUES
('2026-06-02', 'Public Holiday', 'Public & Mercantile Holiday'),
('2026-06-29', 'Poya', 'Poya Day');

-- 6. Users Table
-- Handles authentication & admin credentials
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default user (admin / admin123)
-- bcrypt hash for 'admin123'
INSERT INTO `users` (`username`, `password`) VALUES 
('admin', '$2y$10$wKxN0s3Kz/2T3Bq9cQ8H1O0XJb7jWbS9T2G4xP7Wz1U2H3r4y5t6u')
ON DUPLICATE KEY UPDATE `username` = `username`;
