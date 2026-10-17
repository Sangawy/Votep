<?php
/**
 * Public Website Database Configuration
 * Includes Auto-Healing to create tables if they are missing.
 */

function getPublicDB() {
    // Disable mysqli exceptions (prevents HTTP 500 on PHP 8.1+ if connection fails)
    mysqli_report(MYSQLI_REPORT_OFF); 

    // Detect if we are running locally (Laragon) or on the online server
    $is_local = false;
    if (isset($_SERVER['HTTP_HOST'])) {
        $host_name = $_SERVER['HTTP_HOST'];
        if ($host_name === 'localhost' || 
            $host_name === '127.0.0.1' || 
            $host_name === '::1' || 
            strpos($host_name, '.test') !== false ||
            strpos($host_name, '.local') !== false) {
            $is_local = true;
        }
    }

    if ($is_local) {
        // LOCAL ENVIRONMENT (Laragon)
        $host = 'localhost'; 
        $user = 'root';      
        $pass = '';          
        $db   = 'pirxdry_db';
    } else {
        // ONLINE SÊRVER (cPanel)
        $host = 'localhost'; 
        $user = 'pirxdry_sangawy';      
        $pass = 'Sabate  12@12';          
        $db   = 'pirxdry_web';
    }

    $conn = new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        die("Database Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");

    // AUTO-HEALING: Create missing public tables automatically
    static $public_setup_done = false;
    if (!$public_setup_done) {
        // 1. public_settings
        $conn->query("CREATE TABLE IF NOT EXISTS `public_settings` (
          `id` INT PRIMARY KEY,
          `system_name` VARCHAR(255),
          `system_subtitle` VARCHAR(255),
          `logo_path` VARCHAR(255),
          `primary_color` VARCHAR(50),
          `description` TEXT,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Insert default if empty
        $check = $conn->query("SELECT id FROM public_settings WHERE id = 1");
        if ($check && $check->num_rows == 0) {
            $conn->query("INSERT INTO public_settings (id, system_name, system_subtitle, primary_color) 
                          VALUES (1, 'مەڵبەندی پیرخدر', 'پیرخدر دۆت ئۆرگ', '#5a0202')");
        }

        $conn->query("CREATE TABLE IF NOT EXISTS `public_books` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `local_id` INT UNIQUE NOT NULL,
          `title` VARCHAR(255) NOT NULL,
          `author_name` VARCHAR(255),
          `translator_name` VARCHAR(255),
          `category_name` VARCHAR(255),
          `book_code` VARCHAR(50),
          `shelf_code` VARCHAR(50),
          `publish_year` YEAR,
          `cover_image` VARCHAR(255),
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // 3. public_activities
        $conn->query("CREATE TABLE IF NOT EXISTS `public_activities` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `title` VARCHAR(255) NOT NULL,
          `content` TEXT,
          `image_path` VARCHAR(255),
          `activity_date` DATE,
          `is_published` TINYINT(1) DEFAULT 1,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // 4. public_users
        $conn->query("CREATE TABLE IF NOT EXISTS `public_users` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `username` VARCHAR(50) UNIQUE NOT NULL,
          `password` VARCHAR(255) NOT NULL,
          `full_name` VARCHAR(100)
        )");

        // Insert default admin if missing
        $check_u = $conn->query("SELECT id FROM public_users WHERE username = 'admin'");
        if ($check_u && $check_u->num_rows == 0) {
            $conn->query("INSERT INTO public_users (username, password, full_name) VALUES ('admin', 'pirx2026', 'Admin')");
        }

        // Additional columns for public_books (Upgrade Path)
        $existing = [];
        $cols = $conn->query("SHOW COLUMNS FROM public_books");
        if ($cols) while ($col = $cols->fetch_assoc()) $existing[] = $col['Field'];
        if (!in_array('translator_name', $existing)) $conn->query("ALTER TABLE public_books ADD COLUMN translator_name VARCHAR(255)");
        if (!in_array('publish_year', $existing)) $conn->query("ALTER TABLE public_books ADD COLUMN publish_year VARCHAR(10)");
        if (!in_array('description', $existing)) $conn->query("ALTER TABLE public_books ADD COLUMN description TEXT");
        if (!in_array('quantity', $existing)) $conn->query("ALTER TABLE public_books ADD COLUMN quantity INT DEFAULT 1");
        if (!in_array('available', $existing)) $conn->query("ALTER TABLE public_books ADD COLUMN available INT DEFAULT 1");
        if (!in_array('creator_name', $existing)) $conn->query("ALTER TABLE public_books ADD COLUMN creator_name VARCHAR(255)");

        // 5. public_activity_images (For multiple images like Facebook)
        $conn->query("CREATE TABLE IF NOT EXISTS `public_activity_images` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `activity_id` INT NOT NULL,
          `image_path` VARCHAR(255) NOT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX (`activity_id`)
        )");

        // Additional columns for public_activities
        $existing_act = [];
        $cols_act = $conn->query("SHOW COLUMNS FROM public_activities");
        if ($cols_act) while ($col = $cols_act->fetch_assoc()) $existing_act[] = $col['Field'];
        if (!in_array('view_count', $existing_act)) $conn->query("ALTER TABLE public_activities ADD COLUMN view_count INT DEFAULT 0");

        // Additional columns for public_activity_images
        $existing_img = [];
        $cols_img = $conn->query("SHOW COLUMNS FROM public_activity_images");
        if ($cols_img) while ($col = $cols_img->fetch_assoc()) $existing_img[] = $col['Field'];
        if (!in_array('sort_order', $existing_img)) $conn->query("ALTER TABLE public_activity_images ADD COLUMN sort_order INT DEFAULT 0");

        $public_setup_done = true;
    }

    return $conn;
}
?>
