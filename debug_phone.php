<?php
require_once 'config.php';
$conn = getDB();
$res = $conn->query("SELECT full_name, phone FROM members LIMIT 10");
echo "<h3>New Database (pirxdry_db) Phone Check:</h3><table border='1'>";
while($row = $res->fetch_assoc()) {
    echo "<tr><td>" . $row['full_name'] . "</td><td>" . ($row['phone'] ?: 'EMPTY') . "</td></tr>";
}
echo "</table>";
?>
