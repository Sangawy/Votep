<?php
require_once 'config.php';

$conn = getDB();

if ($conn === null) {
    die("پەیوەندی بە داتابەیسەوە سەرنەکەوت\n");
}

echo "=== دەستپێکردنی ئینفۆرمایشنی داتابەیس ===\n\n";

$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$tables = ['authors', 'translators'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql)) {
            echo "✓ خشتەی $table دروستکرا\n";
        } else {
            echo "✗ هەڵە لە دروستکردنی $table: " . $conn->error . "\n";
        }
    } else {
        echo "✓ خشتەی $table هەیە\n";
    }
}

$columns = [
    'author_id' => 'INT AFTER title',
    'translator_id' => 'INT AFTER author_id',
    'shelf_code' => 'VARCHAR(100) AFTER available',
    'book_code' => 'VARCHAR(100) UNIQUE AFTER shelf_code',
    'language' => 'VARCHAR(50) AFTER publish_year'
];

foreach ($columns as $col => $def) {
    $result = $conn->query("SHOW COLUMNS FROM books LIKE '$col'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE books ADD COLUMN $col $def";
        if ($conn->query($sql)) {
            echo "✓ ستوونی $col زیادکرا\n";
        } else {
            echo "✗ هەڵە لە زیادکردنی $col: " . $conn->error . "\n";
        }
    } else {
        echo "✓ ستوونی $col هەیە\n";
    }
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1");

$conn->query("ALTER TABLE books ADD FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE SET NULL");
$conn->query("ALTER TABLE books ADD FOREIGN KEY (translator_id) REFERENCES translators(id) ON DELETE SET NULL");

echo "\n=== تەواوبوو ===\n";

$conn->close();
