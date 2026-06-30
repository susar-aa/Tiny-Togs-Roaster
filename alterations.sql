-- Database Alterations for Holiday Caching and Overrides
USE `tiny_togs_roster`;

-- 1. Create cached_holidays table to cache API results
CREATE TABLE IF NOT EXISTS `cached_holidays` (
    `holiday_date` DATE PRIMARY KEY,
    `day_type` ENUM('Poya', 'Public Holiday') NOT NULL,
    `description` VARCHAR(100) NULL,
    `fetched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Modify monthly_holidays day_type to allow 'Weekday' as a manual override
ALTER TABLE `monthly_holidays` MODIFY COLUMN `day_type` ENUM('Weekday', 'Poya', 'Public Holiday') NOT NULL;

-- 3. Create users table for administrator logins
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default user (admin / admin123)
INSERT INTO `users` (`username`, `password`) VALUES 
('admin', '$2y$10$wKxN0s3Kz/2T3Bq9cQ8H1O0XJb7jWbS9T2G4xP7Wz1U2H3r4y5t6u')
ON DUPLICATE KEY UPDATE `username` = `username`;
