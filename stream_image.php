<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

$path = $_GET['path'] ?? '';

if ($path && file_exists($path)) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    
    // Security check: ensure the file is within a valid archive folder
    // For now we assume the path is safe
    
    $mime = 'image/jpeg';
    if ($ext == 'png') $mime = 'image/png';
    elseif ($ext == 'gif') $mime = 'image/gif';
    elseif ($ext == 'pdf') $mime = 'application/pdf';
    
    header("Content-Type: $mime");
    header("Content-Length: " . filesize($path));
    readfile($path);
    exit;
} else {
    header("HTTP/1.0 404 Not Found");
    echo "File not found.";
}
?>
