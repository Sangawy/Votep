<?php
require 'config.php';
$conn = getDB();
$sql = "ALTER TABLE members 
        ADD COLUMN first_name VARCHAR(100) AFTER full_name,
        ADD COLUMN father_name VARCHAR(100) AFTER first_name,
        ADD COLUMN grandfather_name VARCHAR(100) AFTER father_name,
        ADD COLUMN great_grandfather_name VARCHAR(100) AFTER grandfather_name";

if ($conn->query($sql)) {
    echo "Columns added successfully!";
} else {
    echo "Error: " . $conn->error;
}
?>
