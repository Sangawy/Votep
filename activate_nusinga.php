<?php
require_once 'config.php';
$conn = getDB();

if (!$conn) {
    die("Database connection failed.");
}

// 1. Check if the section exists and update it
$update = $conn->query("UPDATE system_sections SET link = 'staff_dashboard.php', is_active = 1 WHERE section_key = 'nusinga' OR section_title LIKE '%نوسینگە%'");

if ($update && $conn->affected_rows > 0) {
    echo "بەشی نوسینگە بە سەرکەوتوویی ئەکتیڤ کرا.<br>";
} else {
    // If not found, maybe we need to insert it? Let's check first
    $check = $conn->query("SELECT id FROM system_sections WHERE section_key = 'nusinga'");
    if ($check->num_rows == 0) {
        $ins = $conn->query("INSERT INTO system_sections (section_title, section_description, section_key, icon, link, is_active) 
                             VALUES ('نوسینگە', 'بەڕێوەبردنی داتای کارمەندان، پلەدار و پێشمەرگە، دۆسیەی کەسی و پلەبەرزکردنەوە.', 'nusinga', '👮', 'staff_dashboard.php', 1)");
        if ($ins) echo "بەشی نوسینگە دروستکرا و ئەکتیڤ کرا.<br>";
    } else {
        echo "بەشەکە پێشتر هەبووە بەڵام گۆڕانکاری تێدا نەکرا (ڕەنگە پێشتر چاک کرابێت).<br>";
    }
}

// Also make sure the current user has permission to access it
// By default, super admin has access in portal.php logic: $has_access = (isset($user_data[$perm_key]) && $user_data[$perm_key]) || $is_super_admin;
// But let's add the column to users table just in case it's needed for other users later
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS can_access_nusinga TINYINT(1) DEFAULT 1");

echo "<br><a href='portal.php'>بگەڕێوە بۆ پۆرتاڵ</a>";
?>
