<?php
require_once 'config.php';
$conn = getDB();
$conn->query("ALTER TABLE staff_members ADD COLUMN IF NOT EXISTS notes TEXT AFTER village");
echo "Column 'notes' added to staff_members table.";
?>
