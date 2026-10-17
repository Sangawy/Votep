<?php
require_once 'config.php';
$conn = getDB();
if ($conn) {
    // Add unit to users table
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'unit'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN unit VARCHAR(255) AFTER full_name");
        echo "Unit column added to users\n";
    }
    
    // Check borrow_records for status 'overdue'
    // This status exists in the ENUM according to database.sql: ENUM('borrowed', 'returned', 'overdue')
    
    echo "Database check complete";
    $conn->close();
}
?>
