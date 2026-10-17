<?php
/**
 * Peshmerga System - Database Migration & Self-Healing Script
 * This script ensures the database schema is up-to-date with the latest requirements.
 */
include 'config.php';
$conn = getDB();

if (!$conn) {
    die("Database connection failed. Check config.php");
}

header('Content-Type: text/html; charset=utf-8');
echo "<style>body{font-family:sans-serif; line-height:1.6; padding:20px; background:#f4f4f4;} .log{background:#fff; padding:15px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.1);}</style>";
echo "<h2>🛠️ Database Update & Migration Utility</h2>";
echo "<div class='log'>";

function addColumn($conn, $table, $column, $definition) {
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($check->num_rows == 0) {
        if ($conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition")) {
            echo "<span style='color:green;'>✓</span> Added column <b>$column</b> to <b>$table</b><br>";
        } else {
            echo "<span style='color:red;'>✗</span> Error adding $column to $table: " . $conn->error . "<br>";
        }
    } else {
        echo "<span style='color:gray;'>ℹ</span> Column $column already exists in $table<br>";
    }
}

// 1. Books Table Updates
echo "<h3>1. Syncing Books Schema...</h3>";
addColumn($conn, 'books', 'book_code', "VARCHAR(255) AFTER shelf_code");
addColumn($conn, 'books', 'summary', "TEXT AFTER description");
addColumn($conn, 'books', 'pdf_file', "VARCHAR(255) AFTER created_at");
addColumn($conn, 'books', 'pdf_link', "TEXT AFTER pdf_file");

// 2. Staff Members Updates
echo "<h3>2. Syncing Staff Schema...</h3>";
addColumn($conn, 'staff_members', 'sub_unit', "VARCHAR(255) AFTER unit_id");
addColumn($conn, 'staff_members', 'notes', "TEXT");
addColumn($conn, 'staff_members', 'gender', "ENUM('نێر','مێ') AFTER full_name");
addColumn($conn, 'staff_members', 'parent_id', "INT AFTER id");
addColumn($conn, 'staff_members', 'member_code', "VARCHAR(50) AFTER parent_id");
addColumn($conn, 'staff_members', 'last_promotion_date', "DATE NULL");
addColumn($conn, 'staff_members', 'promotion_due_date', "DATE NULL");

// 3. System Sections Synchronization
echo "<h3>3. Updating System Modules...</h3>";
$sections = [
    'office' => ['ئیدارە و کارگێڕی', 'بەڕێوەبردنی داتای کارمەندان، پلەدار و پێشمەرگە، دۆسیەی کەسی و پلەبەرزکردنەوە.', 'staff_dashboard.php'],
    'docs' => ['نوسینگە و نوسراوەکان', 'تۆمارکردن پەسەندکردنی نوسراوە فەرمییەکان و ئاڵوگۆڕی زانیاری ناوخۆیی.', 'docs_dashboard.php']
];

foreach ($sections as $key => $info) {
    $stmt = $conn->prepare("UPDATE system_sections SET section_title = ?, section_description = ?, link = ? WHERE section_key = ?");
    $stmt->bind_param("ssss", $info[0], $info[1], $info[2], $key);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo "<span style='color:green;'>✓</span> Module <b>$key</b> updated to: $info[0]<br>";
    }
}

// 4. Ensure Ranks are complete (only if table is empty or missing key ranks)
$check_ranks = $conn->query("SELECT COUNT(*) as total FROM staff_ranks");
$row = $check_ranks->fetch_assoc();
if ($row['total'] < 30) {
    echo "<h3>4. Restoring Staff Ranks (Healing)...</h3>";
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $conn->query("TRUNCATE TABLE staff_ranks");
    
    $ranks = [
        [1, "فريق أول ركن", 100, "Officer", 4], [2, "فريق أول", 99, "Officer", 4],
        [3, "فريق ركن", 98, "Officer", 4], [4, "فريق", 97, "Officer", 4],
        [5, "لواء ركن", 96, "Officer", 4], [6, "لواء", 95, "Officer", 4],
        [7, "عميد ركن", 94, "Officer", 4], [8, "عميد", 93, "Officer", 4],
        [9, "عقيد ركن", 92, "Officer", 4], [10, "عقيد", 91, "Officer", 4],
        [11, "مقدم ركن", 90, "Officer", 4], [12, "مقدم", 89, "Officer", 4],
        [13, "رائد ركن", 88, "Officer", 4], [14, "رائد", 87, "Officer", 4],
        [15, "نقيب", 86, "Officer", 4], [16, "ملازم أول", 85, "Officer", 3],
        [17, "ملازم", 84, "Officer", 3], [18, "تلميذ حربي", 83, "Officer", 3],
        [19, "ن.ض.د/ممتاز", 82, "NCO", 3], [20, "ن.ض.د/1", 81, "NCO", 3],
        [21, "ن.ض.د/2", 80, "NCO", 3], [22, "ن.ض.د/3", 79, "NCO", 3],
        [23, "ن.ض.د/4", 78, "NCO", 3], [24, "ن.ض.د/5", 77, "NCO", 3],
        [25, "ن.ض.د/6", 76, "NCO", 3], [26, "ن.ض.د/7", 75, "NCO", 3],
        [27, "ن.ض.د/8", 74, "NCO", 3], [28, "رئيس عرفاء", 73, "NCO", 3],
        [29, "عریف", 72, "NCO", 3], [30, "نائب عریف", 71, "NCO", 3],
        [31, "جندی أول", 70, "Soldier", 3], [32, "جندی", 69, "Soldier", 3]
    ];
    
    $stmt = $conn->prepare("INSERT INTO staff_ranks (id, rank_name, priority, category, promotion_years) VALUES (?, ?, ?, ?, ?)");
    foreach ($ranks as $r) {
        $stmt->bind_param("isssi", $r[0], $r[1], $r[2], $r[3], $r[4]);
        $stmt->execute();
    }
    echo "<span style='color:green;'>✓</span> Successfully restored 32 ranks.<br>";
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
}

echo "</div>";
echo "<br><p><b>✅ All systems are green. Database is synchronized.</b></p>";
echo "<a href='index.php' style='display:inline-block; padding:10px 20px; background:#333; color:#fff; text-decoration:none; border-radius:5px;'>Back to System</a>";
?>
