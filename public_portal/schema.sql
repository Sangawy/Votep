-- Public Website Database Schema (MySQL)
-- Create these tables on your www.pirxdry.org hosting database

-- 1. Table for Synced Books
CREATE TABLE IF NOT EXISTS `public_books` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `local_id` INT UNIQUE NOT NULL, -- ID from the local library computer
  `title` VARCHAR(255) NOT NULL,
  `author_name` VARCHAR(255),
  `translator_name` VARCHAR(255),
  `category_name` VARCH
  Agent
  pirxdry
  
  
  
  AR(255),
  `book_code` VARCHAR(50),
  `shelf_code` VARCHAR(50),
  `publish_year` VARCHAR(10),
  `cover_image` VARCHAR(255),
  `description` TEXT,
  `quantity` INT DEFAULT 1,
  `available` INT DEFAULT 1,
  `creator_name` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Table for Organization Activities (News/Blog)
CREATE TABLE IF NOT EXISTS `public_activities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT,
  `image_path` VARCHAR(255),
  `activity_date` DATE,
  `is_published` TINYINT(1) DEFAULT 1,
  `view_count` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Table for Admin Users (to login and post activities)
CREATE TABLE IF NOT EXISTS `public_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100)
);

-- 4. Table for Website Settings (Synced from Local)
CREATE TABLE IF NOT EXISTS `public_settings` (
  `id` INT PRIMARY KEY,
  `system_name` VARCHAR(255),
  `system_subtitle` VARCHAR(255),
  `logo_path` VARCHAR(255),
  `primary_color` VARCHAR(50),
  `description` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 5. Table for Activity Images (Multi-image galleries)
CREATE TABLE IF NOT EXISTS `public_activity_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `activity_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`activity_id`)
);

-- Insert default settings row
INSERT INTO `public_settings` (id, system_name, system_subtitle, primary_color) 
VALUES (1, 'مەڵبەندی پیرخدر', 'پیرخدر دۆت ئۆرگ', '#5a0202')
ON DUPLICATE KEY UPDATE id=1;

-- Default User: admin / pirx2026
-- (Password should be hashed in production, but here it matches the default auto-inserted one)
INSERT INTO `public_users` (username, password, full_name) 
VALUES ('admin', 'pirx2026', 'Admin')
ON DUPLICATE KEY UPDATE username=username;
