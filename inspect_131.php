<?php
require_once 'config.php';
$conn = getDB();

$res = $conn->query("SELECT id, full_name, parent_id, family_id, created_at FROM members WHERE parent_id = 131 OR id = 131");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']} | Name: {$r['full_name']} | Parent: {$r['parent_id']}\n";
}
