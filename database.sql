-- Settings Table

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    system_name VARCHAR(255) NOT NULL,
    description TEXT,
    logo VARCHAR(255) DEFAULT 'assets/logo.svg',
    owner_name VARCHAR(255) DEFAULT 'sangawy',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO settings (system_name, description, logo, owner_name) VALUES
('کتێبخانەی پێشمەرگە', 'سیستەمی بەڕێوەبردنی کتێبخانە', 'assets/logo.svg', 'sangawy');

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('owner', 'admin', 'user') DEFAULT 'user',
    full_name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default owner user (Password: Sabate 12@12)
INSERT INTO users (username, password, role, full_name) VALUES
('sangawy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'owner', 'سەناوی');

-- Insert admin user (Password: admin123)
INSERT INTO users (username, password, role, full_name) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'ئەدمین');

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT INTO categories (name, description) VALUES
('زانستی', 'کتێبە زانستییەکان'),
('وێژە', 'کتێبە وێژەییەکان'),
('مێژوو', 'کتێبە مێژووییەکان'),
('ئاین', 'کتێبە ئاینییەکان'),
('کۆمپیوتەر', 'کتێبە کۆمپیوتەرییەکان');

-- Books Table
CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255),
    category_id INT,
    isbn VARCHAR(50),
    publish_year YEAR,
    quantity INT DEFAULT 1,
    available INT DEFAULT 1,
    description TEXT,
    cover_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample books
INSERT INTO books (title, author, category_id, isbn, publish_year, quantity, available) VALUES
('مێژووی کورد', 'عەلی بەرەتانی', 3, '978-9954-123-45-6', 2020, 5, 3),
('فێربوونی کوردی', 'محەمەد ئەمین', 2, '978-9954-123-45-7', 2019, 3, 2),
('زانستی کۆمپیوتەر', 'ئاسە حەسەن', 5, '978-9954-123-45-8', 2021, 10, 8),
('قورئان', 'ناونیشان', 4, '978-9954-123-45-9', 2018, 2, 2),
('شاعربوون', 'کەمال سدیق', 2, '978-9954-123-46-0', 2017, 4, 4);

-- Borrow Records Table
CREATE TABLE IF NOT EXISTS borrow_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    borrow_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    status ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample borrow records
INSERT INTO borrow_records (user_id, book_id, borrow_date, due_date, status) VALUES
(2, 1, '2026-04-01', '2026-04-15', 'borrowed'),
(2, 3, '2026-04-02', '2026-04-16', 'borrowed');

-- Reports Table
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type ENUM('daily', 'weekly', 'monthly', 'yearly') DEFAULT 'daily',
    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
