<?php
require_once 'config.php';
$conn = getDB();

if ($conn === null) {
    die("پەیوەندی داتابەیس نییە");
}

echo "Updating books table...<br>";

// Add pdf_file
$res = $conn->query("SHOW COLUMNS FROM books LIKE 'pdf_file'");
if ($res->num_rows == 0) {
    echo "Adding pdf_file column...<br>";
    $conn->query("ALTER TABLE books ADD COLUMN pdf_file VARCHAR(255) AFTER language");
}

// Add pdf_link
$res = $conn->query("SHOW COLUMNS FROM books LIKE 'pdf_link'");
if ($res->num_rows == 0) {
    echo "Adding pdf_link column...<br>";
    $conn->query("ALTER TABLE books ADD COLUMN pdf_link TEXT AFTER pdf_file");
}

// Add cover_image (it might exist but let's be sure)
$res = $conn->query("SHOW COLUMNS FROM books LIKE 'cover_image'");
if ($res->num_rows == 0) {
    echo "Adding cover_image column...<br>";
    $conn->query("ALTER TABLE books ADD COLUMN cover_image VARCHAR(255) AFTER pdf_link");
}

echo "Done.";
?>
