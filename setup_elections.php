<?php
require_once 'config.php';
$conn = getDB();

echo "=== چاککردن و دامەزراندنی سیستەمی هەڵبژاردن ===<br><br>";

$conn->query("SET FOREIGN_KEY_CHECKS = 0");

// Drop old tables to ensure fresh start (since it's a new feature)
$conn->query("DROP TABLE IF EXISTS candidates");
$conn->query("DROP TABLE IF EXISTS election_positions");
$conn->query("DROP TABLE IF EXISTS elections");

// 1. Elections Table
$sql_elections = "CREATE TABLE elections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    election_date DATE,
    status ENUM('active', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_elections)) {
    echo "✓ خشتەی elections دروستکرا.<br>";
}

// 2. Election Positions Table
$sql_positions = "CREATE TABLE election_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    position_name VARCHAR(255) NOT NULL,
    max_winners INT DEFAULT 1,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_positions)) {
    echo "✓ خشتەی election_positions دروستکرا.<br>";
}

// 3. Candidates Table
$sql_candidates = "CREATE TABLE candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    position_id INT NOT NULL,
    member_id INT NOT NULL,
    votes_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (position_id) REFERENCES election_positions(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_candidates)) {
    echo "✓ خشتەی candidates دروستکرا.<br>";
}

// 4. Update Election Section in portal (Use Emoji for icon)
$conn->query("DELETE FROM system_sections WHERE section_key = 'elections'");
$conn->query("INSERT INTO system_sections (section_key, section_title, section_description, icon, link) 
              VALUES ('elections', 'هەڵبژاردنەکان', 'بەڕێوەبردنی پرۆسەی هەڵبژاردن و بەربژێرەکان', '<img src=\"assets/imgae/election.png\" style=\"width: 80%; height: 80%; object-fit: contain;\">', 'elections_dashboard.php')");
echo "✓ بەشی هەڵبژاردن لە پۆرتاڵ نوێکرایەوە.<br>";

// 5. Create a default election and positions
$conn->query("INSERT INTO elections (title, description, election_date) VALUES ('کۆنگرەی ٢٠٢٦', 'هەڵبژاردنی دەستەی کارگێڕی', CURDATE())");
$election_id = $conn->insert_id;

$conn->query("INSERT INTO election_positions (election_id, position_name, max_winners) VALUES 
    ($election_id, 'سەرۆکی دەستە', 1),
    ($election_id, 'ئەندامی دەستەی کارگێڕی', 7),
    ($election_id, 'ئەندامی یەدەگ', 3)");
echo "✓ هەڵبژاردنی کۆنگرە و پۆستەکان جێگیرکران.<br>";

// 6. Permissions
$check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'can_access_elections'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN can_access_elections TINYINT(1) DEFAULT 1");
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "<br><b style='color: green;'>هەموو کێشەکان چارەسەر کران!</b><br>";
echo "ئێستا دەتوانیت بگەڕێیتەوە بۆ لاپەڕەی سەرەکی.";
?>
