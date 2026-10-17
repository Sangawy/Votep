<?php
require_once 'config.php';
$conn = getDB();
$res = $conn->query('DESCRIBE settings');
while($row = $res->fetch_assoc()){
    echo $row['Field'] . ': ' . $row['Type'] . "\n";
}
?>
