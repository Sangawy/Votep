<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Unauthorized access. Please login.']);
    exit;
}

if (!isset($_GET['f'])) {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'No file path provided.']);
    exit;
}

// The JS encodes via btoa(unescape(encodeURIComponent(path))) so decode is just base64_decode
// which gives correct UTF-8 bytes natively in PHP
$file = base64_decode($_GET['f']);

// Normalize slashes for both Windows and Unix paths
$file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);

// Log for debugging
error_log('[stream_doc] Requested path: ' . $file);

if (strpos($file, '..') !== false) {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Invalid path detected.']);
    exit;
}

if (!file_exists($file)) {
    header("Content-Type: application/json");
    echo json_encode([
        'error' => 'File does not exist on server.',
        'debug_path' => $file
    ]);
    exit;
}

$content = @file_get_contents($file);
if ($content === false) {
    header("Content-Type: application/json");
    echo json_encode([
        'error' => 'Failed to read file content.',
        'debug_path' => $file
    ]);
    exit;
}

// Return as JSON base64. 
header("Content-Type: application/json");
echo json_encode([
    'data' => base64_encode($content),
    'name' => basename($file),
    'debug_path' => $file   // temporary - remove after debugging
]);
exit;

