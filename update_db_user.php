<?php
require_once 'config.php';
$conn = getDB();
if ($conn) {
    $check = $conn->query("SHOW COLUMNS FROM books LIKE 'user_id'");
    if ($check->num_rows == 0) {
        $sql = "ALTER TABLE books ADD COLUMN user_id INT AFTER cover_image";
        if ($conn->query($sql)) {
            echo "Column added successfully";
            $conn->query("ALTER TABLE books ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
        } else {
            echo "Error adding column: " . $conn->error;
        }
    } else {
        echo "Column already exists";
    }
    $conn->close();
}
?>
