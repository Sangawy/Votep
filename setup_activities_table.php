<?php
require_once 'config.php';
$conn = getDB();

if (!$conn) {
    die("Connection failed");
}

$sql = "CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    activity_date DATETIME NOT NULL,
    location VARCHAR(255),
    implemented_by VARCHAR(255),
    participants_count INT,
    folder_path VARCHAR(255),
    is_completed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql) === TRUE) {
    echo "Table 'activities' created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
