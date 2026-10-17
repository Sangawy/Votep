<?php
error_reporting(0);
ob_start();
require_once 'config.php';
$conn = getDB();
ob_clean();

$member_id = (int)($_GET['id'] ?? 0);
$ids = [];

if ($member_id > 0) {
    $res = $conn->query("SELECT election_id FROM congress_members WHERE member_id = $member_id");
    while ($row = $res->fetch_assoc()) {
        $ids[] = $row['election_id'];
    }
}

header('Content-Type: application/json');
echo json_encode($ids);
