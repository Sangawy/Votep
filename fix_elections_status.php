<?php
require_once 'config.php';
$conn = getDB();

// Update status column to allow 'inactive'
$conn->query("ALTER TABLE elections MODIFY COLUMN status VARCHAR(20) DEFAULT 'inactive'");
// Set existing ones to inactive if they were default active but shouldn't be
echo "✓ دۆخی خشتەی هەڵبژاردن نوێکرایەوە.";
?>
