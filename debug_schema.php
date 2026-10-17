<?php
require_once 'config.php';
$conn = getDB();
echo "<h1>Table: candidates</h1>";
$res = $conn->query("DESCRIBE candidates");
if ($res) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th></tr>";
    while($row = $res->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "Table 'candidates' not found: " . $conn->error;
}

echo "<h1>Table: elections</h1>";
$res = $conn->query("DESCRIBE elections");
if ($res) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th></tr>";
    while($row = $res->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "Table 'elections' not found";
}
?>
