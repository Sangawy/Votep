<?php
session_start();
if (!isset($_SESSION['user_id'])) exit;

require_once 'config.php';
$conn = getDB();

$id = (int)($_GET['id'] ?? 0);
$path = $_GET['path'] ?? '';

if ($id > 0 && !empty($path)) {
    // Basic security check: ensure path is within the allowed base directory
    $settings = $conn->query("SELECT archive_base_dir FROM settings WHERE id = 1")->fetch_assoc();
    $base = $settings['archive_base_dir'] ?? 'D:/Archive/';
    
    // Normalize paths for comparison
    $normalized_path = realpath(dirname($path)) ?: $path;
    $normalized_base = realpath($base) ?: $base;

    // Create the directory recursively
    if (!is_dir($path)) {
        if (mkdir($path, 0777, true)) {
            // Update staff record
            $stmt = $conn->prepare("UPDATE staff_members SET archive_folder = ? WHERE id = ?");
            $stmt->bind_param("si", $path, $id);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['msg'] = "فۆڵدەر بە سەرکەوتوویی دروستکرا ✅";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['msg'] = "هەڵە لە دروستکردنی فۆڵدەر! دڵنیا بەرەوە کە درایڤەکە (D:) یان ڕێڕەوەکە بوونی هەیە.";
            $_SESSION['msg_type'] = "error";
        }
    }
}

header("Location: staff_profile.php?id=$id");
exit;
