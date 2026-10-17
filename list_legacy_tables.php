<?php
require_once 'config.php';
$old_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, 'election_system');
$res = $old_conn->query("SHOW TABLES");
echo "<h3>Tables in election_system:</h3><ul>";
while($row = $res->fetch_array()) {
    echo "<li>" . $row[0] . "</li>";
}
echo "</ul>";
?>
