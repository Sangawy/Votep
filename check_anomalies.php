<?php
require_once 'config.php';
$conn = getDB();

echo "=== CHECKING FOR COMBINED NAMES WITH 'و' ===\n";
$res = $conn->query("SELECT id, full_name, parent_id FROM members WHERE full_name LIKE '% و %' OR full_name LIKE '%والسيد%' OR full_name LIKE '%والسید%' OR full_name LIKE '% و%'");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']} | Name: {$r['full_name']} | Parent: {$r['parent_id']}\n";
}
if ($res->num_rows === 0) {
    echo "None found.\n";
}

echo "\n=== CHECKING FOR MEMBERS WITH MORE THAN 2 WORDS IN FULL_NAME ===\n";
$res2 = $conn->query("SELECT id, full_name, parent_id FROM members WHERE family_id = 1");
$longNames = [];
while ($r = $res2->fetch_assoc()) {
    $words = explode(' ', trim($r['full_name']));
    // ignore titles like 'السيد' or epithets like 'الملقب ب...'
    if (count($words) >= 4 && !str_contains($r['full_name'], 'الملقب') && !str_contains($r['full_name'], 'الكبير')) {
        $longNames[] = $r;
    }
}
foreach ($longNames as $ln) {
    echo "ID: {$ln['id']} | Name: {$ln['full_name']} | Parent: {$ln['parent_id']}\n";
}
