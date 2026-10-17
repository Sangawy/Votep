<?php
require_once 'config.php';

// Disable timeout for large updates
set_time_limit(0);

$conn = getDB();

if ($conn === null) {
    // Try to connect without database to create it
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($conn->connect_error) {
        die("پەیوەندی داتابەیس نییە: " . $conn->connect_error);
    }
    echo "دروستکردنی داتابەیس: " . DB_NAME . "...<br>";
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db(DB_NAME);
}

echo "=== دەستکرا بە پشکنین و چاککردنی داتابەیس ===<br><br>";

$conn->query("SET FOREIGN_KEY_CHECKS = 0");

// 1. Core Tables
$tables = [
    'settings' => "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        system_name VARCHAR(255) NOT NULL,
        system_subtitle VARCHAR(255) DEFAULT 'وەزارەتی پێشمەرگە',
        description TEXT,
        logo VARCHAR(255) DEFAULT 'assets/logo.svg',
        sidebar_color VARCHAR(50) DEFAULT '#5a0202',
        owner_name VARCHAR(255) DEFAULT 'Peshmerga Admin',
        archive_base_dir VARCHAR(255) DEFAULT 'D:/Archive/',
        current_version VARCHAR(20) DEFAULT '1.0.0',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'users' => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('owner', 'admin', 'user') DEFAULT 'user',
        full_name VARCHAR(255),
        email VARCHAR(255),
        phone VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'system_sections' => "CREATE TABLE IF NOT EXISTS system_sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_key VARCHAR(50) UNIQUE,
        section_title VARCHAR(255),
        section_description TEXT,
        icon VARCHAR(100),
        link VARCHAR(255),
        is_active TINYINT(1) DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'staff_ranks' => "CREATE TABLE IF NOT EXISTS staff_ranks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rank_name VARCHAR(255) NOT NULL,
        priority INT NOT NULL,
        category ENUM('Officer', 'NCO', 'Soldier') NOT NULL,
        promotion_years INT DEFAULT 3
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'staff_units' => "CREATE TABLE IF NOT EXISTS staff_units (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unit_name VARCHAR(255) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'staff_members' => "CREATE TABLE IF NOT EXISTS staff_members (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'categories' => "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'authors' => "CREATE TABLE IF NOT EXISTS authors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'translators' => "CREATE TABLE IF NOT EXISTS translators (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'books' => "CREATE TABLE IF NOT EXISTS books (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(255),
        author_id INT,
        translator_id INT,
        category_id INT,
        isbn VARCHAR(50),
        publish_year YEAR,
        language VARCHAR(50),
        quantity INT DEFAULT 1,
        available INT DEFAULT 1,
        shelf_code VARCHAR(100),
        book_code VARCHAR(100) UNIQUE,
        description TEXT,
        cover_image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE SET NULL,
        FOREIGN KEY (translator_id) REFERENCES translators(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'activities' => "CREATE TABLE IF NOT EXISTS activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255),
        activity_date DATE,
        participants_count INT,
        summary TEXT,
        notes TEXT,
        status TINYINT DEFAULT 0,
        is_completed TINYINT DEFAULT 0,
        is_deleted TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'staff_assets' => "CREATE TABLE IF NOT EXISTS staff_assets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id INT,
        category ENUM('Weapon', 'Vehicle', 'Property', 'Tech', 'Other') DEFAULT 'Other',
        item_name VARCHAR(255) NOT NULL,
        serial_number VARCHAR(100),
        physical_condition VARCHAR(100),
        received_date DATE,
        order_number VARCHAR(100),
        status ENUM('Held', 'Returned') DEFAULT 'Held',
        return_date DATE,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (staff_id) REFERENCES staff_members(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'staff_attendance' => "CREATE TABLE IF NOT EXISTS staff_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id INT NOT NULL,
        leave_type ENUM('Annual Leave', 'Sick Leave', 'Absence', 'Official Mission') NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        duration_days INT NOT NULL,
        order_number VARCHAR(100) DEFAULT NULL,
        reason TEXT DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (staff_id) REFERENCES staff_members(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($tables as $name => $sql) {
    if ($conn->query($sql)) {
        echo "✓ خشتەی $name پشکێنرا/دروستکرا.<br>";
    } else {
        echo "✗ هەڵە لە خشتەی $name: " . $conn->error . "<br>";
    }
}

// 2. Check and Add Missing Columns for existing tables
$updates = [
    'settings' => [
        'system_subtitle' => "VARCHAR(255) DEFAULT 'وەزارەتی پێشمەرگە' AFTER system_name",
        'sidebar_color' => "VARCHAR(50) DEFAULT '#5a0202' AFTER logo",
        'owner_name' => "VARCHAR(255) DEFAULT 'sangawy' AFTER sidebar_color",
        'archive_base_dir' => "VARCHAR(255) DEFAULT 'D:/Archive/' AFTER owner_name",
        'description' => "TEXT AFTER system_subtitle"
    ],
    'activities' => [
        'summary' => "TEXT AFTER participants_count",
        'notes' => "TEXT AFTER summary",
        'status' => "TINYINT DEFAULT 0 AFTER is_completed",
        'is_deleted' => "TINYINT DEFAULT 0 AFTER status"
    ],
    'books' => [
        'author_id' => "INT AFTER title",
        'translator_id' => "INT AFTER author_id",
        'shelf_code' => "VARCHAR(100) AFTER available",
        'book_code' => "VARCHAR(100) UNIQUE AFTER shelf_code",
        'summary' => "TEXT AFTER description",
        'language' => "VARCHAR(50) AFTER publish_year"
    ]
];

foreach ($updates as $table => $cols) {
    foreach ($cols as $col => $def) {
        $check = $conn->query("SHOW COLUMNS FROM $table LIKE '$col'");
        if ($check->num_rows == 0) {
            if ($conn->query("ALTER TABLE $table ADD COLUMN $col $def")) {
                echo "✓ ستوونی $col زیادکرا بۆ $table.<br>";
            } else {
                echo "✗ هەڵە لە زیادکردنی $col بۆ $table: " . $conn->error . "<br>";
            }
        }
    }
}

// 3. Ensure Default Data
// Settings
$res = $conn->query("SELECT id FROM settings WHERE id = 1");
if ($res->num_rows == 0) {
    $conn->query("INSERT INTO settings (id, system_name, system_subtitle, description) VALUES (1, 'سیستەمی دیجیتاڵی پێشمەرگە', 'وەزارەتی پێشمەرگە', 'سیستەمی بەڕێوەبردنی ئەرشیف و کتێبخانە')");
    echo "✓ زانیاریە سەرەتاییەکانی سیستم زیادکرا.<br>";
}

// Admin User
$res = $conn->query("SELECT id FROM users WHERE username = 'admin' OR username = 'sangawy'");
if ($res->num_rows == 0) {
    $pass = password_hash('Sabate 12@12', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, password, role, full_name) VALUES ('sangawy', '$pass', 'owner', 'بەڕێوەبەر')");
    echo "✓ بەکارهێنەری سەرەکی دروستکرا.<br>";
}

// System Sections
$sections = [
    ['office', 'ئیدارە و کارگێڕی', 'بەڕێوەبردنی زانیاری و پلەبەرزکردنەوەی کارمەندان', 'fas fa-users-cog', 'staff_dashboard.php'],
    ['docs', 'نوسینگە و نوسراوەکان', 'تۆمارکردن پەسەندکردنی نوسراوە فەرمییەکان و ئاڵوگۆڕی زانیاری ناوخۆیی.', 'fas fa-archive', 'docs_dashboard.php'],
    ['archive', 'ئەرشیف', 'پاراستنی دۆکیومێنتە کۆنەکان و ئەرشیفکردنی فایلە گرنگەکان', 'fas fa-file-archive', 'archive_list.php'],
    ['library', 'کتێبخانە', 'سەرچاوە و کتێبە سەربازییەکان', 'fas fa-book', 'dashboard.php']
];

foreach ($sections as $s) {
    $check = $conn->query("SELECT id FROM system_sections WHERE section_key = '{$s[0]}'");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO system_sections (section_key, section_title, section_description, icon, link) VALUES ('{$s[0]}', '{$s[1]}', '{$s[2]}', '{$s[3]}', '{$s[4]}')");
    } else {
        $conn->query("UPDATE system_sections SET section_title = '{$s[1]}', section_description = '{$s[2]}', link = '{$s[4]}' WHERE section_key = '{$s[0]}'");
    }
}
echo "✓ بەشەکانی سیستم نوێکرانەوە.<br>";

// Staff Ranks (Reset and Re-insert to ensure correct priority)
$res = $conn->query("SELECT COUNT(*) as cnt FROM staff_ranks");
$row = $res->fetch_assoc();
if ($row['cnt'] < 30) { // If not full list (32 expected)
    $conn->query("TRUNCATE TABLE staff_ranks");
    $ranks = [
        [1, "فريق أول ركن", 100, "Officer", 4],
        [2, "فريق أول", 99, "Officer", 4],
        [3, "فريق ركن", 98, "Officer", 4],
        [4, "فريق", 97, "Officer", 4],
        [5, "لواء ركن", 96, "Officer", 4],
        [6, "لواء", 95, "Officer", 4],
        [7, "عميد ركن", 94, "Officer", 4],
        [8, "عميد", 93, "Officer", 4],
        [9, "عقيد ركن", 92, "Officer", 4],
        [10, "عقيد", 91, "Officer", 4],
        [11, "مقدم ركن", 90, "Officer", 4],
        [12, "مقدم", 89, "Officer", 4],
        [13, "رائد ركن", 88, "Officer", 4],
        [14, "رائد", 87, "Officer", 4],
        [15, "نقيب", 86, "Officer", 4],
        [16, "ملازم أول", 85, "Officer", 3],
        [17, "ملازم", 84, "Officer", 3],
        [18, "تلميذ حربي", 83, "Officer", 3],
        [19, "ن.ض.د/ممتاز", 82, "NCO", 3],
        [20, "ن.ض.د/1", 81, "NCO", 3],
        [21, "ن.ض.د/2", 80, "NCO", 3],
        [22, "ن.ض.د/3", 79, "NCO", 3],
        [23, "ن.ض.د/4", 78, "NCO", 3],
        [24, "ن.ض.د/5", 77, "NCO", 3],
        [25, "ن.ض.د/6", 76, "NCO", 3],
        [26, "ن.ض.د/7", 75, "NCO", 3],
        [27, "ن.ض.د/8", 74, "NCO", 3],
        [28, "رئيس عرفاء", 73, "NCO", 3],
        [29, "عريف", 72, "NCO", 3],
        [30, "نائب عريف", 71, "NCO", 3],
        [31, "جندي أول", 70, "Soldier", 3],
        [32, "جندي", 69, "Soldier", 3]
    ];
    foreach ($ranks as $r) {
        $conn->query("INSERT INTO staff_ranks (id, rank_name, priority, category, promotion_years) VALUES ({$r[0]}, '{$r[1]}', {$r[2]}, '{$r[3]}', {$r[4]})");
    }
    echo "✓ لیستەی پلەکان چاککرایەوە (32 پلە).<br>";
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "<br><b>هەموو شتێک بە سەرکەوتوویی چاککرا!</b><br>";
echo "ئێستا دەتوانیت لاپەڕەی سەرەکی لۆد بکەیتەوە.";
?>
