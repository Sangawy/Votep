<?php
require_once 'config.php';
$conn = getDB();
echo "<h1>Debug Ranks</h1>";
$res = $conn->query("SELECT member_rank, COUNT(*) as count FROM members GROUP BY member_rank");
echo "<ul>";
while($row = $res->fetch_assoc()) {
    echo "<li>Rank: [" . htmlspecialchars($row['member_rank']) . "] - Count: " . $row['count'] . "</li>";
}
echo "</ul>";

echo "<h1>Debug Families</h1>";
$res = $conn->query("SELECT family_name, COUNT(*) as count FROM families f JOIN members m ON f.id = m.family_id GROUP BY family_name");
echo "<ul>";
while($row = $res->fetch_assoc()) {
    echo "<li>Family: [" . htmlspecialchars($row['family_name']) . "] - Count: " . $row['count'] . "</li>";
}
echo "</ul>";
?>
