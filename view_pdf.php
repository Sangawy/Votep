<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$file = $_GET['file'] ?? '';
if (empty($file)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file specified']);
    exit;
}

// Allow files from library Books/pdf_books or uploads/books/pdfs/
$allowed_dirs = ['library Books/pdf_books/', 'uploads/books/pdfs/'];
$found_path = null;

foreach ($allowed_dirs as $dir) {
    $path = $dir . basename($file);
    if (file_exists($path) && is_file($path)) {
        $found_path = $path;
        break;
    }
    if (strpos($file, $dir) === 0) {
        if (file_exists($file) && is_file($file)) {
            $found_path = $file;
            break;
        }
    }
}

if ($found_path) {
    // Return as JSON with Base64 data. 
    // This is the ONLY way to be 100% sure download managers like IDM won't intercept.
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'filename' => basename($found_path),
        'data' => base64_encode(file_get_contents($found_path))
    ]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'File not found']);
}
