<?php
require_once 'config.php';
$conn = getDB();

$res = $conn->query("DESCRIBE elections");
echo "<pre>";
while($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";
?>
