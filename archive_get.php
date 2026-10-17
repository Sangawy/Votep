<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

require_once 'config.php';
$conn = getDB();

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $res = $conn->query("SELECT * FROM activities WHERE id = $id");
    if ($res && $row = $res->fetch_assoc()) {
        $images = [];
        $folder = $row['folder_path'];
        if ($folder && is_dir($folder)) {
            $files = scandir($folder);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $images[] = $folder . '/' . $file;
                }
            }
        }
        $row['images'] = $images;
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Not found']);
    }
}
?>
