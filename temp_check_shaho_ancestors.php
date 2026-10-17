<?php
require_once 'config.php';
$conn = getDB();
$curr = 28;
echo "SHAHO ANCESTORS:\n";
while ($curr > 0) {
    $res = $conn->query("SELECT m.id, m.full_name, m.parent_id, m.family_id, f.family_name 
                         FROM members m 
                         LEFT JOIN families f ON m.family_id = f.id 
                         WHERE m.id = $curr");
    if ($res && $row = $res->fetch_assoc()) {
        echo "- ID: {$row['id']} | Name: {$row['full_name']} | Parent: {$row['parent_id']} | Family: {$row['family_name']} (ID: {$row['family_id']})\n";
        $curr = (int)$row['parent_id'];
    } else {
        break;
    }
}
