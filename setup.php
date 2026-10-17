<?php
require_once 'config.php';

$conn = getDB();

if ($conn === null) {
    die("پەیوەندی بە داتابەیسەوە سەرنەکەوت\n");
}

echo "===========================================\n";
echo "   سکرێپتی ئامادەکاری داتابەیس\n";
echo "===========================================\n\n";

$conn->query("SET FOREIGN_KEY_CHECKS = 0");

echo "--- دروستکردنی خشتەکان ---\n\n";

$tables = [
    'authors' => "CREATE TABLE IF NOT EXISTS authors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'translators' => "CREATE TABLE IF NOT EXISTS translators (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'categories' => "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($tables as $table => $sql) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        if ($conn->query($sql)) {
            echo "✓ خشتەی $table دروستکرا\n";
        } else {
            echo "✗ هەڵە لە دروستکردنی $table: " . $conn->error . "\n";
        }
    } else {
        echo "✓ خشتەی $table هەیە (تێپەڕ نەکرا)\n";
    }
}

echo "\n--- زیادکردنی ستوونەکان بۆ books ---\n\n";

$columns = [
    'author_id' => 'INT',
    'translator_id' => 'INT',
    'shelf_code' => 'VARCHAR(100)',
    'book_code' => 'VARCHAR(100)',
    'language' => 'VARCHAR(50)'
];

foreach ($columns as $col => $type) {
    $result = $conn->query("SHOW COLUMNS FROM books LIKE '$col'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE books ADD COLUMN $col $type";
        if ($conn->query($sql)) {
            echo "✓ ستوونی $col زیادکرا\n";
        } else {
            echo "✗ هەڵە لە زیادکردنی $col: " . $conn->error . "\n";
        }
    } else {
        echo "✓ ستوونی $col هەیە (تێپەڕ نەکرا)\n";
    }
}

echo "\n--- زیادکردنی Unique بۆ book_code ---\n\n";

$indexResult = $conn->query("SHOW INDEX FROM books WHERE Key_name = 'book_code'");
if ($indexResult->num_rows == 0) {
    if ($conn->query("ALTER TABLE books ADD UNIQUE (book_code)")) {
        echo "✓ Unique index بۆ book_code زیادکرا\n";
    } else {
        echo "✗ هەڵە: " . $conn->error . "\n";
    }
} else {
    echo "✓ Unique index هەیە (تێپەڕ نەکرا)\n";
}

echo "\n--- زیادکردنی Foreign Keys ---\n\n";

$foreignKeys = [
    'author_id' => 'REFERENCES authors(id) ON DELETE SET NULL',
    'translator_id' => 'REFERENCES translators(id) ON DELETE SET NULL',
    'category_id' => 'REFERENCES categories(id) ON DELETE SET NULL'
];

foreach ($foreignKeys as $col => $ref) {
    $result = $conn->query("
        SELECT COUNT(*) as cnt 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'books' 
        AND COLUMN_NAME = '$col'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $row = $result->fetch_assoc();
    
    if ($row['cnt'] == 0) {
        if ($conn->query("ALTER TABLE books ADD FOREIGN KEY ($col) $ref")) {
            echo "✓ Foreign Key بۆ $col زیادکرا\n";
        } else {
            echo "✗ هەڵە لۆ $col: " . $conn->error . "\n";
        }
    } else {
        echo "✓ Foreign Key بۆ $col هەیە (تێپەڕ نەکرا)\n";
    }
}

echo "\n--- زیادکردنی sample data ---\n\n";

$result = $conn->query("SELECT COUNT(*) as cnt FROM categories");
$row = $result->fetch_assoc();
if ($row['cnt'] == 0) {
    $categories = [
        ['زانستی', 'کتێبە زانستییەکان'],
        ['وێژە', 'کتێبە وێژەییەکان'],
        ['مێژوو', 'کتێبە مێژووییەکان'],
        ['ئاین', 'کتێبە ئاینییەکان'],
        ['کۆمپیوتەر', 'کتێبە کۆمپیوتەرییەکان']
    ];
    
    $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    foreach ($categories as $cat) {
        $stmt->bind_param("ss", $cat[0], $cat[1]);
        $stmt->execute();
    }
    echo "✓ 5 بەش زیادکرا\n";
} else {
    echo "✓ بەشەکان هەن (تێپەڕ نەکرا)\n";
}

$result = $conn->query("SELECT COUNT(*) as cnt FROM authors");
$row = $result->fetch_assoc();
if ($row['cnt'] == 0) {
    $authors = ['جەمال مەند', 'مەحموود ئەحمەد', 'عەبدوڵڵا پیپۆ', 'ئەنوەر ڕەزا'];
    $stmt = $conn->prepare("INSERT INTO authors (name) VALUES (?)");
    foreach ($authors as $name) {
        $stmt->bind_param("s", $name);
        $stmt->execute();
    }
    echo "✓ 4 نوسەر زیادکرا\n";
} else {
    echo "✓ نوسەرەکان هەن (تێپەڕ نەکرا)\n";
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "\n===========================================\n";
echo "   تەواوبوو! سیستەم ئامادەیە\n";
echo "===========================================\n";

$conn->close();
