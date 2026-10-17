<?php
require_once 'config.php';
$conn = getDB();

$res = $conn->query("SHOW COLUMNS FROM members");
while ($r = $res->fetch_assoc()) {
    echo "{$r['Field']} ({$r['Type']})\n";
}
