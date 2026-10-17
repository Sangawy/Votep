-- Database Setup for Library Management System
-- Run this SQL to create the necessary tables

-- Authors table
CREATE TABLE IF NOT EXISTS authors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);

-- Translators table
CREATE TABLE IF NOT EXISTS translators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);

-- Books table
CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author_id INT,
    translator_id INT,
    category_id INT,
    quantity INT DEFAULT 1,
    shelf_code VARCHAR(100),
    book_code VARCHAR(100) UNIQUE,
    publish_year YEAR,
    language VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE SET NULL,
    FOREIGN KEY (translator_id) REFERENCES translators(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Insert sample data
INSERT INTO categories (name) VALUES 
    ('زانستی'),
    ('ئەدەبیات'),
    ('مێژوو'),
    ('ئاین'),
    ('فەلسەفە'),
    ('تەکنەلۆجیا'),
    ('پزیشکی'),
    ('قوناغا'),
    ('فێربوون');

INSERT INTO authors (name) VALUES 
    ('جەمال مەند'),
    ('مەحموود ئەحمەد'),
    ('عەبدوڵڵا پیپۆ'),
    ('ئەنوەر ڕەزا'),
    ('سەیید شەهێد');
