<?php
require 'config.php';
$conn = getDB();
$res = $conn->query("SELECT id, full_name, normalized_name, member_status FROM members WHERE full_name LIKE '%سامان%'");
echo "<pre>";
while($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";
?>
