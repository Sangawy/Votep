<?php
require_once 'config.php';
$conn = getDB();

if (!$conn) {
    die("Connection failed!");
}

set_time_limit(600);
ini_set('memory_limit', '512M');
$conn->set_charset("utf8mb4");

echo "<h2>دەسترا بە هاوردەکردنی هەموو داتاکان...</h2>";

// ١. پاککردنەوە و ئامادەکردنی تەیبڵەکان
$conn->query("ALTER TABLE books MODIFY COLUMN book_code VARCHAR(255) NULL");
$conn->query("DELETE FROM books"); // Truncate might fail due to FK
$conn->query("ALTER TABLE authors IGNORE ADD UNIQUE INDEX (name)"); 
$conn->query("ALTER TABLE categories IGNORE ADD UNIQUE INDEX (name)");
$conn->query("ALTER TABLE translators IGNORE ADD UNIQUE INDEX (name)");

// ٢. خوێندنەوەی هەموو نوسەر و بەشەکان بۆ ناو مێمۆری (بۆ خێرایی و دڵنیایی)
$authors_map = [];
$res = $conn->query("SELECT id, name FROM authors");
while($row = $res->fetch_assoc()) $authors_map[trim($row['name'])] = $row['id'];

$cats_map = [];
$res = $conn->query("SELECT id, name FROM categories");
while($row = $res->fetch_assoc()) $cats_map[trim($row['name'])] = $row['id'];

$trans_map = [];
$res = $conn->query("SELECT id, name FROM translators");
while($row = $res->fetch_assoc()) $trans_map[trim($row['name'])] = $row['id'];

$success_count = 0;
$error_count = 0;

if (($handle = fopen("library Books/books.csv", "r")) !== FALSE) {
    fgetcsv($handle); // Skip header

    $stmt_book = $conn->prepare("INSERT INTO books (title, author_id, translator_id, category_id, quantity, book_code, description, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");

    while (($data = fgetcsv($handle, 5000, ",")) !== FALSE) {
        if (count($data) < 5) continue;

        $title = trim($data[1]);
        if (empty($title)) continue;

        $author_name = trim($data[2]);
        $translator_name = trim($data[3]);
        $cat_name = trim($data[4]);
        
        $quantity = isset($data[7]) ? intval($data[7]) : 1;
        $book_code = isset($data[8]) ? trim($data[8]) : null;
        $notes = isset($data[9]) ? trim($data[9]) : '';

        // --- نوسەر ---
        $auth_id = null;
        if (!empty($author_name)) {
            if (!isset($authors_map[$author_name])) {
                $conn->query("INSERT INTO authors (name) VALUES ('" . $conn->real_escape_string($author_name) . "')");
                $new_id = $conn->insert_id;
                $authors_map[$author_name] = $new_id;
            }
            $auth_id = $authors_map[$author_name];
        }

        // --- بەش ---
        $cat_id = null;
        if (!empty($cat_name)) {
            if (!isset($cats_map[$cat_name])) {
                $conn->query("INSERT INTO categories (name) VALUES ('" . $conn->real_escape_string($cat_name) . "')");
                $new_id = $conn->insert_id;
                $cats_map[$cat_name] = $new_id;
            }
            $cat_id = $cats_map[$cat_name];
        }

        // --- وەرگێڕ ---
        $trans_id = null;
        if (!empty($translator_name) && $translator_name != 'وەرگێڕی نیە') {
            if (!isset($trans_map[$translator_name])) {
                $conn->query("INSERT INTO translators (name) VALUES ('" . $conn->real_escape_string($translator_name) . "')");
                $new_id = $conn->insert_id;
                $trans_map[$translator_name] = $new_id;
            }
            $trans_id = $trans_map[$translator_name];
        }

        $stmt_book->bind_param("siiiiss", $title, $auth_id, $trans_id, $cat_id, $quantity, $book_code, $notes);
        if ($stmt_book->execute()) {
            $success_count++;
        } else {
            $error_count++;
            echo "Error: " . $stmt_book->error . "\n";
        }
    }
    fclose($handle);
}

echo "✅ $success_count کتێب بە سەرکەوتوویی هاوردەکران.<br>";
echo "<h3>پرۆسەکە تەواو بوو!</h3>";
?>