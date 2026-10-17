<?php
require_once 'config.php';
$conn = getDB();

$res = $conn->query("SELECT id, full_name, parent_id FROM members WHERE parent_id = 134");
echo "Children of 134:\n";
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']} | Name: {$r['full_name']}\n";
}
if ($res->num_rows === 0) {
    echo "134 has NO children in DB.\n";
}
