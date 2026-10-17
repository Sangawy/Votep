<?php
require_once 'db_config.php';
$conn = getPublicDB();
$res = $conn->query("SELECT logo_path FROM public_settings WHERE id = 1");
$row = $res->fetch_assoc();
echo "Logo Path in DB: " . ($row['logo_path'] ?? 'EMPTY');
?>
