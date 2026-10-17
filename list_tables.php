<?php
require_once 'config.php';
$conn = getDB();
$res = $conn->query("SHOW TABLES");
while ($r = $res->fetch_row()) {
    echo $r[0] . "\n";
}
