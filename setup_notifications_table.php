<?php
require_once 'config.php';
$conn = getDB();

if (!$conn) {
    die("Connection failed");
}

$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    message TEXT,
    activity_id INT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql) === TRUE) {
    echo "Table 'notifications' created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
