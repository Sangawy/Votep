<?php
require_once 'config.php';
$conn = getDB();

$fres = $conn->query("SELECT id, family_name FROM families WHERE family_name LIKE '%بانیمۆرد%' OR family_name LIKE '%باني مورد%'");
$fid = 1;
if ($f = $fres->fetch_assoc()) {
    $fid = $f['id'];
    echo "Family: {$f['family_name']} (ID: {$fid})\n";
}

$res = $conn->query("SELECT id, full_name, parent_id FROM members WHERE family_id = $fid");
echo "Total members in this family: " . $res->num_rows . "\n";

$roots = [];
$counts = [];
while ($row = $res->fetch_assoc()) {
    $p = $row['parent_id'] ?? 0;
    if ($p == 0) $roots[] = $row;
    $counts[$p] = ($counts[$p] ?? 0) + 1;
}

echo "Roots count: " . count($roots) . "\n";
foreach ($roots as $r) {
    echo "  Root: ID={$r['id']}, Name={$r['full_name']}\n";
}

// Find max children
arsort($counts);
echo "Top parents with most children:\n";
$i = 0;
foreach ($counts as $pId => $c) {
    if ($pId == 0) continue;
    $pName = $conn->query("SELECT full_name FROM members WHERE id = $pId")->fetch_assoc()['full_name'] ?? 'Unknown';
    echo "  Parent ID={$pId} ({$pName}): {$c} children\n";
    if (++$i >= 5) break;
}
