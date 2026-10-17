<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Forbidden');
}

if (!isset($_GET['f'])) {
    http_response_code(400);
    die('Missing file parameter');
}

// Support both base64 and raw if needed, but we used base64 in the link
$file = base64_decode($_GET['f']);

if (strpos($file, '..') !== false || !file_exists($file)) {
    http_response_code(404);
    die('File not found');
}

$mime = mime_content_type($file);
header("Content-Type: $mime");
header("Content-Disposition: inline; filename=\"" . basename($file) . "\"");
header("Content-Length: " . filesize($file));

readfile($file);
exit;
