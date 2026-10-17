<?php
require_once 'config.php';
$old_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, 'election_system');
$res = $old_conn->query("SELECT * FROM members LIMIT 1");
$row = $res->fetch_assoc();
echo "<h3>Legacy Record Sample (Full Data):</h3><pre>";
print_r($row);
echo "</pre>";
?>
