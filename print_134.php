<?php
require_once 'config.php';
$conn = getDB();

$res = $conn->query("SELECT * FROM members WHERE id = 134");
$r = $res->fetch_assoc();
print_r($r);
