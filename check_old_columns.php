<?php
require_once 'config.php';
$old_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, 'election_system');
$res = $old_conn->query("SELECT full_name, phone FROM members WHERE phone IS NOT NULL AND phone != '' LIMIT 10");
echo "<h3>Legacy Data Sample (election_system.members):</h3><table border='1'>";
while($row = $res->fetch_assoc()) {
    echo "<tr><td>" . $row['full_name'] . "</td><td>" . $row['phone'] . "</td></tr>";
}
echo "</table>";
?>
