<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdf_dir = 'library Books/pdf_books/';
if (!is_dir($pdf_dir)) {
    mkdir($pdf_dir, 0777, true);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
    $files = [];
    if (is_dir($pdf_dir)) {
        $dir_files = scandir($pdf_dir);
        foreach ($dir_files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $path = $pdf_dir . $file;
            if (is_file($path)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    $files[] = [
                        'name' => $file,
                        'size' => filesize($path),
                        'date' => filemtime($path),
                        'path' => $path
                    ];
                }
            }
        }
    }
    
    // Sort by date descending
    usort($files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
    
    echo json_encode(['success' => true, 'files' => $files]);
} 
elseif ($action === 'upload') {
    if (!isset($_FILES['pdf_file'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit;
    }
    
    $file = $_FILES['pdf_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload error code: ' . $file['error']]);
        exit;
    }
    
    $name = $file['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    
    if ($ext !== 'pdf') {
        echo json_encode(['success' => false, 'message' => 'Only PDF files are allowed']);
        exit;
    }
    
    // Sanitize name but keep Kurdish characters if possible, or just use a safe name
    // PHP's move_uploaded_file handles UTF-8 on many systems, but let's be careful.
    // For now, let's keep the original name but remove risky chars.
    $safe_name = preg_replace('/[[:cntrl:]]/', '', $name);
    
    $dest = $pdf_dir . $safe_name;
    
    // If file exists, append timestamp
    if (file_exists($dest)) {
        $name_part = pathinfo($safe_name, PATHINFO_FILENAME);
        $dest = $pdf_dir . $name_part . '_' . time() . '.pdf';
    }
    
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['success' => true, 'message' => 'کتێبەکە بە سەرکەوتوویی زیادکرا']);
    } else {
        echo json_encode(['success' => false, 'message' => 'شکستی هێنا لە پاشەکەوتکردنی فایلەکە']);
    }
}
elseif ($action === 'delete') {
    $filename = $_POST['filename'] ?? '';
    if (empty($filename)) {
        echo json_encode(['success' => false, 'message' => 'Filename required']);
        exit;
    }
    
    $path = $pdf_dir . basename($filename);
    if (file_exists($path) && is_file($path)) {
        if (unlink($path)) {
            echo json_encode(['success' => true, 'message' => 'فایلەکە سڕایەوە']);
        } else {
            echo json_encode(['success' => false, 'message' => 'سڕینەوە سەرنەکەوت']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'فایلەکە نەدۆزرایەوە']);
    }
}
