-- Updated Migration script for Staff Management (Nusinga)
-- Creating ranks table with priority for sorting
CREATE TABLE IF NOT EXISTS staff_ranks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rank_name VARCHAR(255) NOT NULL,
    priority INT NOT NULL,
    category ENUM('Officer', 'NCO', 'Soldier') NOT NULL,
    promotion_years INT DEFAULT 3
);

-- Truncate and Re-insert Ranks by Seniority (Descending priority)
TRUNCATE TABLE staff_ranks;
INSERT INTO staff_ranks (rank_name, priority, category, promotion_years) VALUES 
('فريق أول ركن', 100, 'Officer', 6),
('فريق أول', 99, 'Officer', 6),
('فريق ركن', 98, 'Officer', 6),
('فريق', 97, 'Officer', 6),
('لواء ركن', 96, 'Officer', 6),
('لواء', 95, 'Officer', 6),
('عميد ركن', 94, 'Officer', 4),
('عميد', 93, 'Officer', 4),
('عقيد ركن', 92, 'Officer', 4),
('عقيد', 91, 'Officer', 4),
('مقدم ركن', 90, 'Officer', 4),
('مقدم', 89, 'Officer', 4),
('رائد ركن', 88, 'Officer', 4),
('رائد', 87, 'Officer', 4),
('نقيب', 86, 'Officer', 4),
('ملازم أول', 85, 'Officer', 3),
('ملازم', 84, 'Officer', 3),
('تلميذ حربي', 80, 'Officer', 3),
('ن.ض.د/ممتاز', 70, 'NCO', 3),
('ن.ض.د/1', 69, 'NCO', 3),
('ن.ض.د/2', 68, 'NCO', 3),
('ن.ض.د/3', 67, 'NCO', 3),
('ن.ض.د/4', 66, 'NCO', 3),
('ن.ض.د/5', 65, 'NCO', 3),
('ن.ض.d/6', 64, 'NCO', 3),
('ن.ض.د/7', 63, 'NCO', 3),
('ن.ض.د/8', 62, 'NCO', 3),
('رئيس عرفاء', 50, 'NCO', 3),
('عريف', 40, 'NCO', 3),
('نائب عريف', 30, 'NCO', 3),
('جندي أول', 20, 'Soldier', 3),
('جندي', 10, 'Soldier', 3);

-- Units table
CREATE TABLE IF NOT EXISTS staff_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_name VARCHAR(255) NOT NULL UNIQUE
);

-- Staff Members table
CREATE TABLE IF NOT EXISTS staff_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rank_id INT,
    full_name VARCHAR(255) NOT NULL,
    unit_id INT,
    position VARCHAR(255),
    birth_year INT,
    birth_place VARCHAR(255),
    branch VARCHAR(255),
    education VARCHAR(255),
    phone VARCHAR(50),
    last_promotion_date DATE,
    promotion_due_date DATE,
    id_number VARCHAR(100),
    city VARCHAR(100),
    town VARCHAR(100),
    district VARCHAR(100),
    village VARCHAR(100),
    profile_image VARCHAR(255),
    archive_folder VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rank_id) REFERENCES staff_ranks(id),
    FOREIGN KEY (unit_id) REFERENCES staff_units(id)
);

-- Courses table
CREATE TABLE IF NOT EXISTS staff_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT,
    course_name VARCHAR(255),
    place VARCHAR(255),
    course_date DATE,
    notes TEXT,
    FOREIGN KEY (staff_id) REFERENCES staff_members(id) ON DELETE CASCADE
);

-- Service History table
CREATE TABLE IF NOT EXISTS staff_service_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT,
    unit_name VARCHAR(255),
    position VARCHAR(255),
    service_period VARCHAR(255),
    order_number VARCHAR(255),
    FOREIGN KEY (staff_id) REFERENCES staff_members(id) ON DELETE CASCADE
);

-- Update system_sections to activate Nusinga
UPDATE system_sections SET link = 'staff_dashboard.php', is_active = 1 WHERE section_key = 'nusinga' OR section_title LIKE '%نوسینگە%';
