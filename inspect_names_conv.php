<?php
require_once 'config.php';
$conn = getDB();

$res = $conn->query("SELECT id, full_name, first_name, father_name FROM members WHERE family_id = 1 LIMIT 25");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']} | Full: {$r['full_name']} | First: {$r['first_name']} | Father: {$r['father_name']}\n";
}
