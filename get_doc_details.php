<?php
require_once 'config.php';
$conn = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$response = ['success' => false];

if ($id > 0) {
    $res = $conn->query("SELECT * FROM archive_docs WHERE id = $id");
    if ($res && $doc = $res->fetch_assoc()) {
        $response['success'] = true;
        $response['doc'] = $doc;
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
