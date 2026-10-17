<?php
require_once 'config.php';
$old_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, 'election_system');
$res = $old_conn->query("SELECT COUNT(*) as total FROM members");
$row = $res->fetch_assoc();
echo "Total members in legacy DB: " . $row['total'] . "<br>";

$res_phone = $old_conn->query("SELECT COUNT(*) as total FROM members WHERE phone IS NOT NULL AND phone != ''");
$row_phone = $res_phone->fetch_assoc();
echo "Members with phone in legacy DB: " . $row_phone['total'] . "<br>";
?>
