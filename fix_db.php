<?php
require_once 'config.php';
$conn = getDB();

if ($conn === null) {
    die("پەیوەندی داتابەیس نییە");
}

echo "دەستکرا بە پشکنینی داتابەیس...<br>";

// 1. Check/Add owner_name, description, and archive_base_dir to settings table
$res = $conn->query("SHOW COLUMNS FROM settings LIKE 'owner_name'");
if ($res->num_rows == 0) {
    echo "زیادکردنی ستوونی owner_name...<br>";
    $conn->query("ALTER TABLE settings ADD COLUMN owner_name VARCHAR(255) DEFAULT 'sangawy' AFTER logo");
}

$res = $conn->query("SHOW COLUMNS FROM activities LIKE 'summary'");
if ($res->num_rows == 0) {
    echo "زیادکردنی ستوونی summary...<br>";
    $conn->query("ALTER TABLE activities ADD COLUMN summary TEXT AFTER participants_count");
}

$res = $conn->query("SHOW COLUMNS FROM activities LIKE 'notes'");
if ($res->num_rows == 0) {
    echo "زیادکردنی ستوونی notes...<br>";
    $conn->query("ALTER TABLE activities ADD COLUMN notes TEXT AFTER summary");
}

$res = $conn->query("SHOW COLUMNS FROM activities LIKE 'is_deleted'");
if ($res->num_rows == 0) {
    echo "زیادکردنی ستوونی is_deleted...<br>";
    $conn->query("ALTER TABLE activities ADD COLUMN is_deleted TINYINT DEFAULT 0 AFTER status");
}

$res = $conn->query("SHOW COLUMNS FROM activities LIKE 'status'");
if ($res->num_rows == 0) {
    echo "زیادکردنی ستوونی status...<br>";
    $conn->query("ALTER TABLE activities ADD COLUMN status TINYINT DEFAULT 0 AFTER is_completed");
    $conn->query("UPDATE activities SET status = 1 WHERE is_completed = 1");
}

$res = $conn->query("SHOW COLUMNS FROM settings LIKE 'description'");
if ($res->num_rows == 0) {
    echo "زیادکردنی ستوونی description...<br>";
    $conn->query("ALTER TABLE settings ADD COLUMN description TEXT AFTER system_name");
}

$res = $conn->query("SHOW COLUMNS FROM settings LIKE 'system_subtitle'");
if ($res->num_rows == 0) {
    echo "زیادکردنی ستوونی system_subtitle...<br>";
    $conn->query("ALTER TABLE settings ADD COLUMN system_subtitle VARCHAR(255) DEFAULT 'وەزارەتی پێشمەرگە' AFTER system_name");
}

$res = $conn->query("SHOW COLUMNS FROM settings LIKE 'sidebar_color'");
if ($res->num_rows == 0) {
    echo "زیادکردنی ستوونی sidebar_color...<br>";
    $conn->query("ALTER TABLE settings ADD COLUMN sidebar_color VARCHAR(50) DEFAULT '#5a0202' AFTER logo");
}

$res = $conn->query("SHOW COLUMNS FROM settings LIKE 'archive_base_dir'");
if ($res->num_rows == 0) {
    echo "زیادکردنی ستوونی archive_base_dir...<br>";
    $conn->query("ALTER TABLE settings ADD COLUMN archive_base_dir VARCHAR(255) DEFAULT 'D:/Archive/' AFTER owner_name");
}

// 2. Ensure row #1 exists
$res = $conn->query("SELECT id FROM settings WHERE id = 1");
if ($res->num_rows == 0) {
    echo "دروستکردنی ڕیزبەندی یەکەم بۆ ڕێکخستنەکان...<br>";
    $conn->query("INSERT INTO settings (id, system_name, description) VALUES (1, 'کتێبخانەی پێشمەرگە', 'سیستەمی بەڕێوەبردنی کتێبخانە')");
}

echo "هەموو شتێک بە سەرکەوتوویی چاککرا. ئێستا دەتوانیت لاپەڕەی ڕێکخستنەکان لۆد بکەیتەوە.";
?>
