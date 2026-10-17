<?php
require_once 'config.php';
$conn = getDB();
$res = $conn->query("SELECT id, doc_number, file_path FROM archive_docs ORDER BY id DESC LIMIT 10");
echo "<table><tr><th>ID</th><th>Number</th><th>Path</th><th>Basename</th></tr>";
while($row = $res->fetch_assoc()) {
    $path = $row['file_path'];
    $normalized = str_replace('\\', '/', $path);
    echo "<tr><td>{$row['id']}</td><td>{$row['doc_number']}</td><td>" . htmlspecialchars($path) . "</td><td>" . basename($normalized) . "</td></tr>";
}
echo "</table>";
