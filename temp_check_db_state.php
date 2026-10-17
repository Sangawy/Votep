<?php
require 'config.php';
$conn = getDB();

echo "Current Members in DB:\n";
$res = $conn->query("SELECT id, full_name, normalized_name, parent_id, family_id FROM members ORDER BY id ASC");
while($r = $res->fetch_assoc()) {
    echo "- ID: {$r['id']} | Name: {$r['full_name']} | Norm: {$r['normalized_name']} | Parent ID: {$r['parent_id']} | Family: {$r['family_id']}\n";
}
?>
