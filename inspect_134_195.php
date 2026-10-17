<?php
require_once 'config.php';
$conn = getDB();

$res = $conn->query("SELECT id, full_name, parent_id, family_id FROM members WHERE id IN (134, 194, 195)");
while ($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']}\n";
    echo "  full_name: {$row['full_name']}\n";
    echo "  parent_id: {$row['parent_id']}\n";
    echo "  family_id: {$row['family_id']}\n";
}
